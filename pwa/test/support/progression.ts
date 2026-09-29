import type { Measure, MeasureSeries, PhotoSession, Pose, ProgressPhoto, WeighIn, WeightGoal, WeightSummary } from '~/features/progression/progression'

export const TODAY = '2026-09-29'

export function weighIn(day: string, weightKg: number): WeighIn {
  return { day, weightKg, recordedAt: `${day}T07:30:00+00:00` }
}

export const goal: WeightGoal = {
  id: '0199a0b1-0000-7000-8000-00000000f001',
  startDay: '2026-09-01',
  startWeightKg: 48,
  targetKg: 60,
  direction: 'gaining',
  steps: [
    { weightKg: 50, target: false, reachedOn: '2026-09-12' },
    { weightKg: 55, target: false, reachedOn: null },
    { weightKg: 60, target: true, reachedOn: null },
  ],
  percent: 32,
  sinceLastStepKg: 1.8,
  next: { weightKg: 55, target: false, remainingKg: 3.2, estimate: { status: 'estimated', weeks: 8 } },
  toTargetKg: 8.2,
  toTarget: { status: 'estimated', weeks: 20 },
  setAt: '2026-09-01T07:00:00+00:00',
}

export function summary(override: Partial<WeightSummary> = {}): WeightSummary {
  return {
    period: '3m',
    latest: { day: TODAY, weightKg: 51.8 },
    first: { day: '2026-06-01', weightKg: 47 },
    average7Kg: 51.6,
    average30Kg: 50.9,
    kgPerWeek: 0.42,
    direction: 'gaining',
    sinceStartKg: 4.8,
    goal: null,
    points: [
      { day: '2026-09-15', weightKg: 50.6, average7Kg: 50.6, average30Kg: 50.2 },
      { day: '2026-09-22', weightKg: 51.2, average7Kg: 51.0, average30Kg: 50.6 },
      { day: TODAY, weightKg: 51.8, average7Kg: 51.6, average30Kg: 50.9 },
    ],
    weeks: [],
    ...override,
  }
}

export const HIPS = '0199a0b1-0000-7000-8000-00000000f101'

const PARTS = ['neck', 'shoulders', 'chest', 'left_arm', 'right_arm', 'waist', 'left_thigh', 'right_thigh', 'left_calf', 'right_calf']

export const measures: Measure[] = [
  ...PARTS.map(key => ({ key, name: null, custom: false, archived: false })),
  { key: HIPS, name: 'Hanches', custom: true, archived: false },
]

export function series(key: string, points: Array<[string, number]>, override: Partial<MeasureSeries> = {}): MeasureSeries {
  const measure = measures.find(one => one.key === key) ?? { key, name: 'Poignet', custom: true, archived: true }
  const last = points.at(-1)
  return {
    ...measure,
    latest: last ? { day: last[0], cm: last[1] } : null,
    changeCm: points.length > 1 ? Math.round((last![1] - points[0]![1]) * 10) / 10 : null,
    points: points.map(([day, cm]) => ({ day, cm })),
    ...override,
  }
}

export function progressPhoto(day: string, pose: Pose, n = 1): ProgressPhoto {
  const id = `0199a0b1-0000-7000-8000-0000000${day.replaceAll('-', '').slice(2)}${n}`
  return {
    id, day, pose, width: 1200, height: 1600,
    url: `https://s3/${id}.webp?sig`, thumbnailUrl: `https://s3/${id}-thumbnail.webp?sig`, downloadUrl: `https://s3/${id}.webp?download`,
    expiresAt: '2099-01-01T00:00:00+00:00',
  }
}

export const sessions: PhotoSession[] = [
  {
    day: '2026-09-28',
    weight: { day: '2026-09-27', weightKg: 51.2 },
    measurements: { day: '2026-09-20', values: [{ key: 'left_arm', name: null, cm: 30.5 }, { key: 'waist', name: null, cm: 70 }] },
    photos: [progressPhoto('2026-09-28', 'front'), progressPhoto('2026-09-28', 'back', 2)],
  },
  {
    day: '2026-06-01',
    weight: { day: '2026-06-01', weightKg: 45 },
    measurements: { day: '2026-06-01', values: [{ key: 'waist', name: null, cm: 68 }] },
    photos: [progressPhoto('2026-06-01', 'front')],
  },
]
