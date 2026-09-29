import type { components } from '~/types/api'

export type WeighIn = components['schemas']['WeighIn']
export type WeightSummary = components['schemas']['WeightSummary']
export type WeightGoal = components['schemas']['WeightGoal']
export type GoalEstimate = WeightGoal['toTarget']
export type Period = WeightSummary['period']
export type Measure = components['schemas']['Measure']
export type Measurements = components['schemas']['Measurements']
export type MeasurementsSummary = components['schemas']['MeasurementsSummary']
export type MeasureSeries = components['schemas']['MeasureSeriesOutput']
export type ProgressPhoto = components['schemas']['ProgressPhoto']
export type PhotoSession = components['schemas']['PhotoSession']
export type Pose = ProgressPhoto['pose']

/** How a photo was taken: comparisons set face against face (spec §60). */
export const POSES: ReadonlyArray<{ value: Pose, label: string }> = [
  { value: 'front', label: 'Face' },
  { value: 'side', label: 'Profil' },
  { value: 'back', label: 'Dos' },
  { value: 'other', label: 'Autre' },
]

export function poseLabel(pose: Pose): string {
  return POSES.find(option => option.value === pose)?.label ?? pose
}

/** The body parts measured by default (spec §54), named here; the user's own carry their name. */
export const BODY_PARTS: Readonly<Record<string, string>> = {
  neck: 'Cou',
  shoulders: 'Épaules',
  chest: 'Poitrine',
  left_arm: 'Bras gauche',
  right_arm: 'Bras droit',
  waist: 'Taille',
  left_thigh: 'Cuisse gauche',
  right_thigh: 'Cuisse droite',
  left_calf: 'Mollet gauche',
  right_calf: 'Mollet droit',
}

export function measureName(measure: Pick<Measure, 'key' | 'name'>): string {
  return measure.name ?? BODY_PARTS[measure.key] ?? measure.key
}

/** Centimetres as a tape reads them. */
export const LENGTH_CM = { min: 1, max: 300 } as const

/**
 * The spans of the charts (spec §55): the pill's label, what it stands for, and the whole phrase
 * a sentence uses (« sur la dernière année », not « sur les dernière année »).
 */
export const PERIODS: ReadonlyArray<{ value: Period, label: string, long: string, during: string }> = [
  { value: '7d', label: '7\u00A0j', long: '7 derniers jours', during: 'sur les 7 derniers jours' },
  { value: '30d', label: '30\u00A0j', long: '30 derniers jours', during: 'sur les 30 derniers jours' },
  { value: '3m', label: '3\u00A0mois', long: '3 derniers mois', during: 'sur les 3 derniers mois' },
  { value: '6m', label: '6\u00A0mois', long: '6 derniers mois', during: 'sur les 6 derniers mois' },
  { value: '1y', label: '1\u00A0an', long: 'dernière année', during: 'sur la dernière année' },
  { value: 'all', label: 'Tout', long: 'depuis le début', during: 'depuis le début' },
]

/** Kilograms as typed on a bathroom scale. */
export const WEIGHT_KG = { min: 20, max: 400 } as const

/**
 * useAsyncData keys shared by several pages, so that a write can invalidate what it changed.
 */
export const PROGRESSION_KEYS = {
  weight: (period: Period, today: string) => `progression-weight-${period}-${today}`,
  weighIns: 'progression-weigh-ins',
  goal: 'progression-weight-goal',
  measures: 'progression-measures',
  measurements: (period: Period, today: string) => `progression-measurements-${period}-${today}`,
  photoSessions: 'progression-photo-sessions',
} as const

/** Everything measurements or one's own measures change. */
export function isMeasurementDerived(key: string): boolean {
  return key.startsWith('progression-measure')
}

/** Everything a weigh-in or a goal changes. */
export function isWeightDerived(key: string): boolean {
  return key.startsWith('progression-weight') || key === PROGRESSION_KEYS.weighIns
}
