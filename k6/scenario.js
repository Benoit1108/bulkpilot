// Load test of the demo accounts (`make perf`, docs/TESTING.md): each reader is signed in as its own
// account (several users' data in every table, as in production), reading the dashboard, the
// analyses the app runs when it opens and the main pages; a writer logs foods, a weigh-in and the
// sets of a workout, new rows every time (the data grows during the test). Everything stays within
// the API's per-account rate limits, so a 429 is a failure like any other.
import http from 'k6/http'
import { check, fail, group, sleep } from 'k6'

const BASE_URL = __ENV.BASE_URL || 'https://php'
const EMAIL = __ENV.DEMO_EMAIL || 'demo@bulkpilot.local'
const PASSWORD = __ENV.DEMO_PASSWORD || 'demo-bulkpilot-2026'
const ACCOUNTS = Number(__ENV.ACCOUNTS || 5)
// The 95th percentile allowed, in ms: 300 on a workstation, wider on a shared CI runner (ci.yml).
const P95 = Number(__ENV.K6_P95_MS || 300)
const DURATION = __ENV.DURATION || '60s'
const WORKOUT = '0199a0b1-0000-7000-8000-00000000f00d'

export const options = {
  insecureSkipTLSVerify: true,
  scenarios: {
    // Readers come in one by one, then all read together.
    reads: { executor: 'ramping-vus', startVUs: 1, stages: [{ duration: '15s', target: ACCOUNTS }, { duration: DURATION, target: ACCOUNTS }], exec: 'reads' },
    writes: { executor: 'constant-vus', vus: 1, duration: DURATION, exec: 'writes', startTime: '15s' },
  },
  thresholds: {
    http_req_failed: ['rate==0'],
    checks: ['rate==1'],
    'http_req_duration{warmup:no}': [`p(95)<${P95}`],
    'http_req_duration{group:::dashboard}': [`p(95)<${P95}`],
    'http_req_duration{group:::pages}': [`p(95)<${P95}`],
    'http_req_duration{group:::writes}': [`p(95)<${P95}`],
  },
}

const today = () => new Date().toISOString().slice(0, 10)
/** demo@bulkpilot.local, then demo2@bulkpilot.local… (the seed's numbering). */
const emailOf = number => (number === 1 ? EMAIL : EMAIL.replace('@', `${number}@`))

/** The reads of the dashboard, and what the app runs when it opens. */
const DASHBOARD = day => [
  ['GET', '/api/me/dashboard'],
  ['GET', `/api/nutrition/days/${day}`],
  ['GET', `/api/nutrition/averages?days=7&today=${day}`],
  ['GET', `/api/progression/weight?period=7d&today=${day}`],
  ['GET', '/api/training/next-workout'],
  ['GET', `/api/training/analytics?period=7d&today=${day}`],
  ['GET', `/api/progression/weight-goal?today=${day}`],
  // Once a day the analysis runs, then answers what it found; the checks run at most every 5 minutes.
  ['POST', '/api/coach/analysis', { today: day }],
  ['POST', '/api/notifications/check', {}],
  ['GET', '/api/notifications/unread'],
]
const PAGES = day => [
  '/api/workouts?limit=20',
  `/api/progression/weight?period=1y&today=${day}`,
  `/api/nutrition/history?period=3m&today=${day}`,
  `/api/training/analytics?period=all&today=${day}`,
  '/api/coach/recommendations?limit=20',
  '/api/foods?q=riz',
  '/api/exercises',
]
const json = (session, tags = { warmup: 'no' }) => ({
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Origin': BASE_URL, 'Cookie': session },
  tags,
})
const send = (session, method, path, body, tags) => (method === 'GET'
  ? http.get(`${BASE_URL}${path}`, json(session, tags))
  : http.request(method, `${BASE_URL}${path}`, JSON.stringify(body), json(session, tags)))
const expectOk = (response, name) => {
  if (response.status < 200 || response.status >= 300) fail(`${name}: ${response.status} ${response.body}`)
  return response
}

/** Signs every account in once (sign-ins are rate limited) and starts the workout the writer logs sets into. */
export function setup() {
  const sessions = []
  for (let number = 1; number <= ACCOUNTS; number++) {
    const signIn = http.post(`${BASE_URL}/api/auth/sign-in`, JSON.stringify({ email: emailOf(number), password: PASSWORD }), {
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Origin': BASE_URL },
    })
    expectOk(signIn, `sign-in of ${emailOf(number)}`)
    sessions.push(Object.entries(signIn.cookies).map(([name, values]) => `${name}=${values[0].value}`).join('; '))
    // Each session is sent explicitly: the jar would add the last account's cookie to every request.
    http.cookieJar().clear(BASE_URL)
  }
  const session = sessions[0]

  const exerciseId = expectOk(http.get(`${BASE_URL}/api/exercises`, json(session)), 'exercises').json('0.id')
  const foodId = expectOk(http.get(`${BASE_URL}/api/foods?q=riz`, json(session)), 'food search').json('0.id')
  if (!exerciseId || !foodId) fail(`no exercise (${exerciseId}) or food (${foodId}) to write with`)
  const startedAt = new Date()
  expectOk(http.put(`${BASE_URL}/api/workouts/${WORKOUT}`, JSON.stringify({ startedAt: startedAt.toISOString() }), json(session)), 'workout')
  expectOk(http.put(`${BASE_URL}/api/workouts/${WORKOUT}/exercises/0`, JSON.stringify({ exerciseId }), json(session)), 'workout exercise')

  // Warm-up, not measured against the thresholds: the workers' first requests load the code.
  for (const each of sessions) {
    for (const [method, path, body] of DASHBOARD(today())) send(each, method, path, body, { warmup: 'yes' })
  }

  return { sessions, foodId, startedAt: startedAt.getTime() }
}

export function reads({ sessions }) {
  const session = sessions[(__VU - 1) % sessions.length]
  const day = today()
  group('dashboard', () => {
    for (const [method, path, body] of DASHBOARD(day)) {
      const response = send(session, method, path, body)
      check(response, { [`${method} ${path.split('?')[0]} ok`]: r => r.status >= 200 && r.status < 300 })
    }
  })
  group('pages', () => {
    for (const path of PAGES(day)) {
      const response = http.get(`${BASE_URL}${path}`, json(session))
      check(response, { [`${path.split('?')[0]} 200`]: r => r.status === 200 })
    }
  })
  sleep(2)
}

let round = 0

export function writes({ sessions, foodId, startedAt }) {
  const session = sessions[0]
  const day = today()
  group('writes', () => {
    const n = round++
    const weighIn = http.put(`${BASE_URL}/api/progression/weigh-ins/${day}`, JSON.stringify({ weightKg: 78 + (n % 10) / 10 }), json(session))
    check(weighIn, { 'weigh-in 200': r => r.status === 200 })
    // A new journal entry and a new set every round: the tables grow during the test.
    const logged = http.post(`${BASE_URL}/api/nutrition/entries`, JSON.stringify({ day, slot: 'snack', foodId, grams: 30 }), json(session))
    check(logged, { 'entry 201': r => r.status === 201 })
    const id = `0199a0b1-0000-7000-8000-${String(100000 + n).padStart(12, '0')}`
    const doneAt = new Date(startedAt + n * 1000).toISOString()
    const set = http.put(`${BASE_URL}/api/workouts/${WORKOUT}/sets/${id}`, JSON.stringify({ exerciseIndex: 0, kind: 'normal', loadKg: 60, reps: 8, doneAt }), json(session))
    check(set, { 'set 204': r => r.status === 204 })
  })
  // 40 rounds a minute: under every per-account write limit (progression 120 / min, nutrition, training 600 / min).
  sleep(1.5)
}
