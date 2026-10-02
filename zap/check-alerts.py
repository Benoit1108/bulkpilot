"""Fails on any Medium (2) or High (3) ZAP alert left in the reports, or on a scan that did not reach
the API.

Alerts justified in rules.tsv are IGNOREd by ZAP and never reach the reports; a missing report
(ZAP could not run) is a failure too. With --spec, the API scan must have reached most operations:
ZAP reports every 4xx answer as an informational alert (100000) with its status: an operation
answered 401, 403 or 404 never reached its use case (a 400 or 422 did: the input was judged).
"""
import argparse
import json
import sys

MIN_SCANNED_SHARE = 0.6

parser = argparse.ArgumentParser()
parser.add_argument('--spec')
parser.add_argument('reports', nargs='+')
arguments = parser.parse_args()

failed = False
client_errors = 0  # answers that never reached a use case
for path in arguments.reports:
    try:
        with open(path, encoding='utf-8') as report:
            sites = json.load(report).get('site', [])
    except (OSError, ValueError) as error:
        print(f'{path}: no report ({error})')
        failed = True
        continue
    for site in sites:
        for alert in site.get('alerts', []):
            risk = int(alert.get('riskcode', 0))
            instances = len(alert.get('instances', []))
            label = f"[{alert.get('pluginid')}] {alert.get('alert')} ({alert.get('riskdesc')}) — {instances} URL(s)"
            if path.endswith('api.json') and str(alert.get('pluginid')) == '100000':
                client_errors += sum(1 for instance in alert.get('instances', []) if (instance.get('evidence') or '').strip()[:3] in ('401', '403', '404'))
            if risk >= 2:
                failed = True
                print(f'{path}: FAIL {label}')
            else:
                print(f'{path}: info {label}')

if arguments.spec:
    with open(arguments.spec, encoding='utf-8') as file:
        spec = json.load(file)
    operations = sum(1 for methods in spec['paths'].values() for method in methods if method in ('get', 'post', 'put', 'patch', 'delete'))
    scanned = operations - client_errors
    print(f'ZAP: {scanned} of {operations} API operations reached their use case (not 401, 403 or 404).')
    if scanned < operations * MIN_SCANNED_SHARE:
        print(f'ZAP: under {int(MIN_SCANNED_SHARE * 100)} %: the scan barely reached the API (session, Origin or ids lost).')
        failed = True

print('ZAP: unjustified Medium/High alerts found, or too little scanned.' if failed else 'ZAP: no Medium or High alert.')
sys.exit(1 if failed else 0)
