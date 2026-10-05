#!/usr/bin/env bash
# Prepares a fresh Ubuntu 24.04 server (Oracle Cloud Always Free Ampere A1 by default, any Docker
# host otherwise) to run BulkPilot (docs/DEPLOYMENT.md, ADR-0033). Run as root from a copy of the
# repository's deploy/ directory; running it again installs the new scripts and changes nothing else
# that is already in place (that is also how a change to deploy/ reaches the server):
#   sudo IMAGE_REPO=ghcr.io/<owner>/bulkpilot-php GITHUB_REPO=<owner>/<repo> \
#        STAGING_KEY="ssh-ed25519 AAAA… ci-staging" PRODUCTION_KEY="ssh-ed25519 AAAA… ci-production" \
#        TAILSCALE_AUTHKEY=tskey-auth-… ./bootstrap.sh
#
# It installs Docker, Tailscale and automatic security updates (Docker's and Tailscale's own
# repositories included), adds swap and log rotation, hardens SSH, creates the `deploy` user and
# /opt/bulkpilot (scripts owned by root), lets each CI key run one gated command only, closes every
# inbound port except over the tailnet, publishes production and Uptime Kuma with `tailscale serve`,
# installs the external heartbeat timer and starts Uptime Kuma.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="${BULKPILOT_ROOT:-/opt/bulkpilot}"
deploy_user="${DEPLOY_USER:-deploy}"
admin_user="${ADMIN_USER:-${SUDO_USER:-ubuntu}}"
hostname_on_tailnet="${TAILSCALE_HOSTNAME:-bulkpilot}"
swap_size="${SWAP_SIZE:-4G}"

log() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == 0 ]] || die "run as root (sudo)"
[[ -f "$here/compose.yaml" && -f "$here/deploy.sh" && -f "$here/gate.sh" ]] || die "run from the repository's deploy/ directory"
# shellcheck source=/dev/null
. /etc/os-release
[[ "${ID:-}" == ubuntu ]] || die "written for Ubuntu (found ${ID:-unknown})"
export DEBIAN_FRONTEND=noninteractive

log "System packages and automatic security updates"
apt-get update -q
apt-get install -y -q ca-certificates curl gnupg jq iptables-persistent unattended-upgrades
cat > /etc/apt/apt.conf.d/52bulkpilot-unattended <<'EOF'
// Security updates every night, Docker and Tailscale included (their own repositories); a reboot
// when one needs it, early in the morning UTC (containers restart by themselves).
Unattended-Upgrade::Origins-Pattern { "origin=Docker"; "origin=Tailscale"; };
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "05:00";
EOF
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF

log "SSH: keys only, no root, named users only"
cat > /etc/ssh/sshd_config.d/10-bulkpilot.conf <<EOF
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
AllowUsers $admin_user $deploy_user
EOF
sshd -t && systemctl reload ssh

log "Docker engine and compose (Docker's own repository)"
if ! command -v docker > /dev/null; then
	install -m 0755 -d /etc/apt/keyrings
	curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
	chmod a+r /etc/apt/keyrings/docker.asc
	echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" > /etc/apt/sources.list.d/docker.list
	apt-get update -q
	apt-get install -y -q docker-ce docker-ce-cli containerd.io docker-compose-plugin
fi
# Every container's logs rotate, whatever its compose file says (10 MB × 5).
if [[ ! -f /etc/docker/daemon.json ]]; then
	cat > /etc/docker/daemon.json <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "5" },
  "live-restore": true
}
EOF
	systemctl restart docker
fi
systemctl enable --now docker

log "Swap ($swap_size)"
if ! swapon --show | grep -q /swapfile; then
	fallocate -l "$swap_size" /swapfile
	chmod 600 /swapfile
	mkswap /swapfile
	swapon /swapfile
	grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
sysctl -q -w vm.swappiness=10
echo 'vm.swappiness=10' > /etc/sysctl.d/90-bulkpilot.conf

log "Deploy user, $root and the scripts (owned by root)"
if ! id "$deploy_user" > /dev/null 2>&1; then
	useradd --create-home --shell /bin/bash "$deploy_user"
fi
usermod -aG docker "$deploy_user"
install -d -m 755 -o root -g root "$root" "$root/bin" "$root/ops"
install -d -m 755 -o "$deploy_user" -g "$deploy_user" "$root/state"
install -d -m 700 -o "$deploy_user" -g "$deploy_user" "$root/env"
# Atomically (a new file, then rename): a deployment running a script never reads half of the new one.
install_file() {
	local mode="$1" source="$2" target="$3" temporary
	temporary="$(mktemp "$(dirname "$target")/.install-XXXXXX")"
	install -m "$mode" -o root -g root "$source" "$temporary"
	mv -f "$temporary" "$target"
}
for script in deploy.sh rollback.sh restore.sh prune.sh bp.sh lib.sh; do
	install_file 755 "$here/$script" "$root/bin/$script"
done
install_file 644 "$here/compose.yaml" "$root/bin/compose.yaml"
install_file 644 "$here/ops.compose.yaml" "$root/ops/compose.yaml"
install_file 755 "$here/gate.sh" /usr/local/sbin/bulkpilot-gate
install_file 755 "$here/heartbeat.sh" /usr/local/sbin/bulkpilot-heartbeat

# The server's settings (not secret): where images come from and who must have signed them.
if [[ ! -f "$root/env/server.env" ]]; then
	[[ -n "${IMAGE_REPO:-}" && -n "${GITHUB_REPO:-}" ]] || die "IMAGE_REPO and GITHUB_REPO are needed the first time (ghcr.io/<owner>/bulkpilot-php, <owner>/<repo>)"
	cat > "$root/env/server.env" <<EOF
# Read by the server scripts (lib.sh) and the heartbeat timer.
BULKPILOT_IMAGE=$IMAGE_REPO
# Only images signed by this workflow, on main, are deployed (cosign keyless, GitHub OIDC).
BULKPILOT_SIGNER=https://github.com/$GITHUB_REPO/.github/workflows/ci.yml@refs/heads/main
# healthchecks.io check "server" (https://hc-ping.com/<uuid>): pinged every 5 minutes (docs/MONITORING.md).
HEALTHCHECKS_HOST_URL=
EOF
	chown "$deploy_user:$deploy_user" "$root/env/server.env"
	chmod 600 "$root/env/server.env"
fi
for env in production staging; do
	if [[ ! -f "$root/env/$env.env" ]]; then
		install -m 600 -o "$deploy_user" -g "$deploy_user" "$here/environment.env.example" "$root/env/$env.env"
		echo "   $root/env/$env.env created from the example: fill it in before the first deployment"
	fi
done

log "SSH keys of the deploy user"
keys="/home/$deploy_user/.ssh/authorized_keys"
install -d -m 700 -o "$deploy_user" -g "$deploy_user" "/home/$deploy_user/.ssh"
touch "$keys"
# The CI keys run the gate only, for one environment each; the admin's own keys (the one used to
# create the VM) get a normal shell as deploy for day-to-day operations.
add_key() { grep -qxF "$1" "$keys" || echo "$1" >> "$keys"; }
[[ -n "${STAGING_KEY:-}" ]] && add_key "restrict,command=\"/usr/local/sbin/bulkpilot-gate staging\" $STAGING_KEY"
[[ -n "${PRODUCTION_KEY:-}" ]] && add_key "restrict,command=\"/usr/local/sbin/bulkpilot-gate production\" $PRODUCTION_KEY"
if [[ -r "/home/$admin_user/.ssh/authorized_keys" ]]; then
	while read -r line; do
		[[ -n "$line" && "$line" != \#* ]] && add_key "$line"
	done < "/home/$admin_user/.ssh/authorized_keys"
fi
chown "$deploy_user:$deploy_user" "$keys"
chmod 600 "$keys"

log "Tailscale"
if ! command -v tailscale > /dev/null; then
	curl -fsSL https://tailscale.com/install.sh | sh
fi
if ! tailscale status > /dev/null 2>&1; then
	[[ -n "${TAILSCALE_AUTHKEY:-}" ]] || die "TAILSCALE_AUTHKEY is needed (a pre-approved key tagged tag:server, docs/DEPLOYMENT.md §1)"
	tailscale up --authkey="$TAILSCALE_AUTHKEY" --hostname="$hostname_on_tailnet" --advertise-tags=tag:server ||
		die "tailscale up failed: check TAILSCALE_AUTHKEY (pre-approved, tag:server) and the tagOwners of the ACL"
fi
tailscale status > /dev/null 2>&1 || die "Tailscale is not connected: the firewall is left as it was"

log "Firewall: nothing comes in but over the tailnet"
# Applied now and at every boot by bulkpilot-firewall.service, after the rules saved by
# iptables-persistent (Oracle's images reject everything but SSH there). Docker and Tailscale
# rebuild their own chains themselves: they are never saved.
cat > /usr/local/sbin/bulkpilot-firewall <<EOF
#!/usr/bin/env bash
set -euo pipefail
ensure_rule() { "\$1" -C "\${@:2}" 2> /dev/null || "\$1" -I "\${@:2}"; }
for ipt in iptables ip6tables; do
	ensure_rule "\$ipt" INPUT -i tailscale0 -j ACCEPT
	ensure_rule "\$ipt" INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
	ensure_rule "\$ipt" INPUT -i lo -j ACCEPT
	if [[ "${KEEP_PUBLIC_SSH:-0}" != 1 ]]; then
		while "\$ipt" -C INPUT -p tcp -m state --state NEW -m tcp --dport 22 -j ACCEPT 2> /dev/null; do
			"\$ipt" -D INPUT -p tcp -m state --state NEW -m tcp --dport 22 -j ACCEPT
		done
		while "\$ipt" -C INPUT -p tcp --dport 22 -j ACCEPT 2> /dev/null; do
			"\$ipt" -D INPUT -p tcp --dport 22 -j ACCEPT
		done
	fi
done
# Whatever is left is refused (Oracle's rules end with a REJECT already); ICMP stays (IPv6
# neighbour discovery needs it).
if ! iptables -S INPUT | grep -qE -- '-j (REJECT|DROP)( |\$)'; then
	iptables -A INPUT -p icmp -j ACCEPT
	iptables -A INPUT -j DROP
fi
if ! ip6tables -S INPUT | grep -qE -- '-j (REJECT|DROP)( |\$)'; then
	ip6tables -A INPUT -p ipv6-icmp -j ACCEPT
	ip6tables -A INPUT -j DROP
fi
EOF
chmod 755 /usr/local/sbin/bulkpilot-firewall
cat > /etc/systemd/system/bulkpilot-firewall.service <<'EOF'
[Unit]
Description=BulkPilot firewall: nothing comes in but over the tailnet
After=netfilter-persistent.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/bulkpilot-firewall
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF
# The saved rules no longer let public SSH in (Oracle's image saves an ACCEPT for port 22).
if [[ "${KEEP_PUBLIC_SSH:-0}" != 1 ]]; then
	for saved in /etc/iptables/rules.v4 /etc/iptables/rules.v6; do
		[[ -f "$saved" ]] && sed -i -E '/--dport 22 -j ACCEPT/d' "$saved"
	done
fi
systemctl daemon-reload
systemctl enable --now bulkpilot-firewall.service
systemctl restart bulkpilot-firewall.service

log "External heartbeat (healthchecks.io), every 5 minutes"
cat > /etc/systemd/system/bulkpilot-heartbeat.service <<EOF
[Unit]
Description=BulkPilot heartbeat to healthchecks.io

[Service]
Type=oneshot
User=$deploy_user
ExecStart=/usr/local/sbin/bulkpilot-heartbeat
EOF
cat > /etc/systemd/system/bulkpilot-heartbeat.timer <<'EOF'
[Unit]
Description=BulkPilot heartbeat to healthchecks.io, every 5 minutes

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min

[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now bulkpilot-heartbeat.timer

log "HTTPS on the tailnet (tailscale serve)"
# Needs MagicDNS and HTTPS certificates enabled in the Tailscale admin console (DNS page) first.
# Staging has its own node (deploy/compose.yaml, service tailscale): only production and Uptime Kuma here.
tailscale serve --bg --https=443 http://127.0.0.1:8080 ||
	die "tailscale serve failed: enable MagicDNS and HTTPS certificates in the Tailscale console (DNS), then run this again"
tailscale serve --bg --https=10000 http://127.0.0.1:3001
tailscale serve --https=8443 off > /dev/null 2>&1 || true
tailscale serve status

log "Uptime Kuma (create its account right away: docs/DEPLOYMENT.md §2)"
sudo -u "$deploy_user" docker compose -f "$root/ops/compose.yaml" up -d

name="$(tailscale status --json | jq -r '.Self.DNSName' | sed 's/\.$//')"
cat <<EOF

Server ready. Next (docs/DEPLOYMENT.md):
  1. keep this session open and check, from another terminal: ssh $admin_user@$hostname_on_tailnet and ssh $deploy_user@$hostname_on_tailnet;
  2. remove the 0.0.0.0/0 TCP 22 ingress rule of the VM's security list (Oracle console);
  3. open https://$name:10000 now to create the Uptime Kuma account;
  4. fill in $root/env/production.env (PUBLIC_HOST=$name) and staging.env (its own node);
  5. pin the host key for GitHub: ssh-keyscan -t ed25519 $hostname_on_tailnet | gh variable set DEPLOY_KNOWN_HOSTS
EOF
