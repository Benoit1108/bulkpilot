import type { Coach, CoachSettings, Recommendation, WeeklyReport } from '~/features/coach/coach'

export function recommendation(override: Partial<Recommendation> = {}): Recommendation {
  return {
    id: '0199a0b1-0000-7000-8000-00000000c001',
    kind: 'weigh_ins.few',
    level: 'info',
    title: 'Pèse-toi plus souvent',
    body: 'Pas assez de pesées pour une tendance fiable.',
    why: ['1 pesée ces 7 derniers jours.', 'Il en faut au moins 3 par semaine pour une tendance fiable.'],
    proposal: null,
    figures: { weighIns7: 1, weighIns28: 1, neededPerWeek: 3 },
    status: 'pending',
    provider: 'fake',
    createdAt: '2026-09-29T08:00:00+00:00',
    expiresAt: '2026-10-06T08:00:00+00:00',
    postponedUntil: null,
    decidedAt: null,
    ...override,
  }
}

export const increase = recommendation({
  id: '0199a0b1-0000-7000-8000-00000000c002',
  kind: 'calories.increase',
  level: 'priority',
  title: 'Proposition : +150\u00A0kcal',
  body: 'Nouvel objectif proposé : 2\u00A0500\u00A0kcal par jour, à partir de demain.',
  why: ['Ton poids évolue de 0,0\u00A0kg par semaine sur 4 semaines, pour +0,5\u00A0kg visés.', 'Tu atteins donc environ 99\u00A0% de ta cible.'],
  proposal: { fromKcal: 2350, byKcal: 150, toKcal: 2500, carbohydrateChangeGrams: 38 },
})

export function report(override: Partial<WeeklyReport> = {}): WeeklyReport {
  return {
    weekStart: '2026-09-21',
    startKg: 47.8,
    endKg: 48.2,
    changeKg: 0.4,
    kgPerWeek: 0.42,
    paceKgPerWeek: 0.5,
    averageKcal: 2310,
    averageProtein: 98,
    loggedDays: 6,
    targetKcal: 2350,
    targetProtein: 110,
    sessions: 3,
    plannedSessions: 3,
    records: 4,
    adherence: { score: 86, parts: [{ part: 'calories', done: 5, expected: 7, score: 71 }, { part: 'weigh_ins', done: 3, expected: 3, score: 100 }] },
    verdict: 'on_track',
    action: null,
    ...override,
  }
}

export function coach(override: Partial<Coach> = {}): Coach {
  return {
    today: '2026-09-29',
    analysedOn: '2026-09-29',
    personality: 'direct',
    recommendations: [increase, recommendation()],
    adherence: {
      score: 75,
      parts: [
        { part: 'calories', done: 5, expected: 7, score: 71 },
        { part: 'protein', done: 4, expected: 7, score: 57 },
        { part: 'training', done: 2, expected: 3, score: 67 },
        { part: 'weigh_ins', done: 3, expected: 3, score: 100 },
      ],
    },
    report: report(),
    ...override,
  }
}

export const settings: CoachSettings = { personality: 'direct', paceKgPerWeek: null }
