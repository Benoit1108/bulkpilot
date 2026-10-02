#!/bin/sh
# OWASP ZAP against the E2E stack (production image), from inside its network: run by `make zap`.
# 1. a passive baseline of the app (spider + passive rules);
# 2. a scan of every API operation from the OpenAPI spec, signed in as a fresh account that owns
#    one resource of each kind: the spec's paths name them, so that requests reach the handlers
#    instead of answering 404, and every request carries the session cookie and the app's Origin
#    (writes from elsewhere are refused before anything runs, ADR-0008);
# then fails on any Medium or High alert that `rules.tsv` does not justify, or when too few
# operations were really scanned.
# Passive by default (CI); ZAP_ACTIVE=1 (`make zap-active`) attacks the API: never against real data.
set -eu
TARGET=${ZAP_TARGET:-https://php}
cd /zap/wrk
rm -rf report && mkdir report

# A fresh account with one resource of each kind, and the spec ZAP reads: OpenAPI 3.0 (API Platform
# writes 3.2, which its parser mostly skips), its server being this stack, its paths naming those
# resources, without the operations that would end the scan's own session.
COOKIE=$(python3 - "$TARGET" <<'PY'
import http.cookiejar, json, ssl, sys, time, urllib.request
target = sys.argv[1]
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ssl._create_unverified_context()))
def call(method, path, body=None, accept='application/json'):
    request = urllib.request.Request(target + path, method=method, data=None if body is None else json.dumps(body).encode(),
        headers={'Content-Type': 'application/json', 'Accept': accept, 'Origin': target})
    with opener.open(request) as response:
        content = response.read()
        return json.loads(content) if content else None
call('POST', '/api/auth/register', {'firstName': 'Zap', 'email': 'zap-%d@example.com' % int(time.time()), 'password': 'correct horse battery'})
day = time.strftime('%Y-%m-%d', time.gmtime(time.time() - 86400))
food = call('POST', '/api/foods', {'name': 'Gainer zap', 'energyKcal': 400, 'protein': 20, 'carbohydrate': 60, 'fat': 8})['id']
recipe = call('POST', '/api/recipes', {'name': 'Recette zap', 'servings': 1, 'ingredients': [{'foodId': food, 'grams': 100}]})['id']
exercise = call('POST', '/api/exercises', {'name': 'Exercice zap', 'primaryMuscle': 'chest', 'type': 'compound', 'equipment': 'bodyweight', 'measurement': 'weight', 'repMin': 8, 'repMax': 12})['id']
planned = {'exerciseId': exercise, 'sets': 3, 'repMin': 8, 'repMax': 12, 'targetRir': 2, 'restSeconds': 120}
program = call('POST', '/api/programs', {'name': 'Programme zap', 'days': [{'name': 'A', 'blocks': [{'exercises': [planned]}]}]})['id']
call('PUT', '/api/progression/weigh-ins/' + day, {'weightKg': 80})
measure = call('POST', '/api/progression/measures', {'name': 'Zap'})['key']
call('PUT', '/api/progression/measurements/' + day, {'centimetres': {'waist': 80}})
logged = call('POST', '/api/nutrition/entries', {'day': day, 'slot': 'lunch', 'foodId': food, 'grams': 100})
entry = next(e['id'] for meal in logged['meals'] for e in meal['entries'])
favorite = call('POST', '/api/nutrition/favorite-meals', {'name': 'Midi zap', 'day': day, 'slot': 'lunch'})['id']
workout = '0199a0b1-0000-7000-8000-0000000a2a01'
started = time.strftime('%Y-%m-%dT%H:%M:%S+00:00', time.gmtime(time.time() - 3600))
call('PUT', '/api/workouts/' + workout, {'startedAt': started})
call('PUT', '/api/workouts/' + workout + '/exercises/0', {'exerciseId': exercise})
values = {'{day}': day, '{key}': measure, '{index}': '0', '{workoutId}': workout}
by_prefix = [('/api/foods/', food), ('/api/recipes/', recipe), ('/api/exercises/', exercise), ('/api/programs/', program),
    ('/api/nutrition/entries/', entry), ('/api/nutrition/favorite-meals/', favorite), ('/api/workouts/', workout)]
spec = call('GET', '/api/docs.jsonopenapi', accept='application/vnd.openapi+json')
spec['openapi'] = '3.0.3'
spec['servers'] = [{'url': target}]
paths = {}
for path, operations in spec['paths'].items():
    # What would end the scan's own session (signing out, closing the account, changing its password).
    if path in ['/api/auth/sign-out', '/api/me/deletion', '/api/me/password', '/api/me/sessions/{id}']:
        continue
    concrete = path
    for placeholder, value in values.items():
        concrete = concrete.replace(placeholder, value)
    for prefix, value in by_prefix:
        if concrete.startswith(prefix):
            concrete = concrete.replace('{id}', value)
    for operation in operations.values():
        if isinstance(operation, dict) and 'parameters' in operation:
            operation['parameters'] = [p for p in operation['parameters'] if p.get('in') != 'path' or '{' + p.get('name', '') + '}' in concrete]
    paths[concrete] = operations
spec['paths'] = paths
with open('report/api-spec.json', 'w', encoding='utf-8') as file:
    json.dump(spec, file)
print('; '.join('%s=%s' % (cookie.name, cookie.value) for cookie in jar))
PY
)
[ -n "$COOKIE" ] || { echo 'No session cookie: the account could not be created.'; exit 1; }

zap-baseline.py -t "$TARGET" -c rules.tsv -J report/baseline.json -I -s -m 2 || true
SAFE=-S
[ "${ZAP_ACTIVE:-0}" = '1' ] && SAFE=
zap-api-scan.py -t /zap/wrk/report/api-spec.json -f openapi $SAFE -c rules.tsv -J report/api.json -I -s \
	-z "-config replacer.full_list(0).description=session -config replacer.full_list(0).enabled=true -config replacer.full_list(0).matchtype=REQ_HEADER -config replacer.full_list(0).matchstr=Cookie -config replacer.full_list(0).regex=false -config replacer.full_list(0).replacement=$COOKIE -config replacer.full_list(1).description=origin -config replacer.full_list(1).enabled=true -config replacer.full_list(1).matchtype=REQ_HEADER -config replacer.full_list(1).matchstr=Origin -config replacer.full_list(1).regex=false -config replacer.full_list(1).replacement=$TARGET" || true

exec python3 check-alerts.py --spec report/api-spec.json report/baseline.json report/api.json
