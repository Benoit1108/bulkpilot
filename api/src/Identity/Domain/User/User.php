<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\Exception\EmailAlreadyVerified;
use App\Identity\Domain\Exception\EmailNotVerified;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\Exception\TwoFactorAlreadyEnabled;
use App\Identity\Domain\Exception\TwoFactorNotEnabled;
use App\Identity\Domain\Exception\TwoFactorSetupNotStarted;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

class User extends AggregateRoot
{
    public const int RECOVERY_CODE_COUNT = 10;
    /** Days between asking to delete the account and its erasure: signing in again cancels. */
    public const int DELETION_DELAY_DAYS = 7;

    /** Optimistic lock (persistence concern): single-use values cannot be spent twice by parallel requests. */
    private int $version = 1;

    private ?\DateTimeImmutable $emailVerifiedAt = null;

    /** Seed being enrolled: becomes active once the user proves their app generates valid codes. */
    private ?EncryptedSecret $pendingTotpSecret = null;
    private ?EncryptedSecret $totpSecret = null;
    private ?\DateTimeImmutable $twoFactorEnabledAt = null;
    /** Last accepted TOTP time step: a code is never accepted twice (replay protection). */
    private ?int $lastTotpStep = null;
    /** @var list<string> keyed hashes of the unused recovery codes */
    private array $recoveryCodeHashes = [];

    /** When the user asked to delete the account, and when it is erased (null: not asked). */
    private ?\DateTimeImmutable $deletionRequestedAt = null;
    private ?\DateTimeImmutable $deletionDueAt = null;

    private function __construct(
        private readonly UserId $id,
        private readonly EmailAddress $email,
        /** Null for accounts created through an external provider until a password is set. */
        private ?HashedPassword $password,
        private Profile $profile,
        private readonly \DateTimeImmutable $registeredAt,
    ) {
    }

    public static function register(UserId $id, EmailAddress $email, HashedPassword $password, Profile $profile, \DateTimeImmutable $now): self
    {
        $user = new self($id, $email, $password, $profile, $now);
        $user->recordThat(new UserRegistered($id, $email, $now));

        return $user;
    }

    public function verifyEmail(\DateTimeImmutable $now): void
    {
        if ($this->isEmailVerified()) {
            throw EmailAlreadyVerified::forUser($this->id);
        }

        $this->emailVerifiedAt = $now;
        $this->recordThat(new EmailVerified($this->id, $now));
    }

    /**
     * Account created from a provider that vouches for the email: verified, no password yet
     * (one can be set through the password reset flow).
     */
    public static function registerExternally(UserId $id, EmailAddress $email, Profile $profile, \DateTimeImmutable $now): self
    {
        $user = new self($id, $email, null, $profile, $now);
        $user->emailVerifiedAt = $now;
        $user->recordThat(new UserRegistered($id, $email, $now));

        return $user;
    }

    public function changePassword(HashedPassword $password, \DateTimeImmutable $now): void
    {
        $this->password = $password;
        $this->recordThat(new PasswordChanged($this->id, $now));
    }

    public function updateProfile(Profile $profile): void
    {
        $this->profile = $profile;
    }

    /**
     * Two-factor authentication protects the account; it requires a verified email so that
     * the account can still be recovered.
     */
    public function startTwoFactorEnrollment(EncryptedSecret $secret): void
    {
        if (!$this->isEmailVerified()) {
            throw EmailNotVerified::create();
        }
        if ($this->isTwoFactorEnabled()) {
            throw TwoFactorAlreadyEnabled::create();
        }

        $this->pendingTotpSecret = $secret;
    }

    /**
     * @param int          $step               time step of the first valid code entered by the user
     * @param list<string> $recoveryCodeHashes
     */
    public function confirmTwoFactor(int $step, array $recoveryCodeHashes, \DateTimeImmutable $now): void
    {
        if (null === $this->pendingTotpSecret) {
            throw TwoFactorSetupNotStarted::create();
        }

        $this->totpSecret = $this->pendingTotpSecret;
        $this->pendingTotpSecret = null;
        $this->twoFactorEnabledAt = $now;
        $this->lastTotpStep = $step;
        $this->recoveryCodeHashes = $recoveryCodeHashes;
        $this->recordThat(new TwoFactorEnabled($this->id, $now));
    }

    public function disableTwoFactor(\DateTimeImmutable $now): void
    {
        if (!$this->isTwoFactorEnabled()) {
            throw TwoFactorNotEnabled::create();
        }

        $this->totpSecret = null;
        $this->pendingTotpSecret = null;
        $this->twoFactorEnabledAt = null;
        $this->lastTotpStep = null;
        $this->recoveryCodeHashes = [];
        $this->recordThat(new TwoFactorDisabled($this->id, $now));
    }

    /**
     * @param list<string> $recoveryCodeHashes
     */
    public function replaceRecoveryCodes(array $recoveryCodeHashes): void
    {
        if (!$this->isTwoFactorEnabled()) {
            throw TwoFactorNotEnabled::create();
        }

        $this->recoveryCodeHashes = $recoveryCodeHashes;
    }

    /**
     * @throws InvalidSecondFactorCode when the code's time step was already used (or is older)
     */
    public function acceptTotpStep(int $step): void
    {
        if (null !== $this->lastTotpStep && $step <= $this->lastTotpStep) {
            throw InvalidSecondFactorCode::create();
        }

        $this->lastTotpStep = $step;
    }

    public function consumeRecoveryCode(string $hash, \DateTimeImmutable $now): void
    {
        $index = array_search($hash, $this->recoveryCodeHashes, true);
        if (false === $index) {
            throw InvalidSecondFactorCode::create();
        }

        $remaining = $this->recoveryCodeHashes;
        unset($remaining[$index]);
        $this->recoveryCodeHashes = array_values($remaining);
        $this->recordThat(new RecoveryCodeUsed($this->id, \count($this->recoveryCodeHashes), $now));
    }

    /**
     * The account is closed at once (the caller revokes every session) and erased after the
     * delay. Asking again keeps the first date.
     */
    public function scheduleDeletion(\DateTimeImmutable $now): void
    {
        if (null !== $this->deletionDueAt) {
            return;
        }
        $this->deletionRequestedAt = $now;
        $this->deletionDueAt = $now->modify(\sprintf('+%d days', self::DELETION_DELAY_DAYS));
        $this->recordThat(new AccountDeletionScheduled($this->id, $this->deletionDueAt, $now));
    }

    /** Signing in during the delay keeps the account; without a deletion asked, nothing happens. */
    public function cancelDeletion(\DateTimeImmutable $now): void
    {
        if (null === $this->deletionDueAt) {
            return;
        }
        $this->deletionRequestedAt = null;
        $this->deletionDueAt = null;
        $this->recordThat(new AccountDeletionCancelled($this->id, $now));
    }

    public function isDeletionScheduled(): bool
    {
        return null !== $this->deletionDueAt;
    }

    public function isDueForErasure(\DateTimeImmutable $now): bool
    {
        return null !== $this->deletionDueAt && $this->deletionDueAt <= $now;
    }

    public function deletionDueAt(): ?\DateTimeImmutable
    {
        return $this->deletionDueAt;
    }

    public function isTwoFactorEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function totpSecret(): ?EncryptedSecret
    {
        return $this->totpSecret;
    }

    public function pendingTotpSecret(): ?EncryptedSecret
    {
        return $this->pendingTotpSecret;
    }

    /**
     * @return list<string>
     */
    public function recoveryCodeHashes(): array
    {
        return $this->recoveryCodeHashes;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function password(): ?HashedPassword
    {
        return $this->password;
    }

    public function profile(): Profile
    {
        return $this->profile;
    }

    public function hasPassword(): bool
    {
        return null !== $this->password;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function registeredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
