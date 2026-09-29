import { describe, expect, it } from 'vitest'
import { isExerciseKey, isProgramKey, TRAINING_KEYS } from '~/features/training/cacheKeys'
import { blockLabel, formatRest, formatTarget, labelOf, MUSCLES, prescription } from '~/features/training/training'
import { parseAmount } from '~/services/text/numbers'
import { matches, searchKey } from '~/services/text/search'
import { planned, plank, raise } from '../support/training'

describe('training formats', () => {
  it('writes targets, rests and positions as gyms do', () => {
    expect(formatTarget(8, 12, 'weight')).toBe('8–12\u00A0reps')
    expect(formatTarget(5, 5, 'bodyweight')).toBe('5\u00A0reps')
    expect(formatTarget(30, 60, 'duration')).toBe('30–60\u00A0s')
    expect(formatRest(0)).toBe('sans repos')
    expect(formatRest(45)).toBe('45\u00A0s')
    expect(formatRest(120)).toBe('2\u00A0min')
    expect(formatRest(95)).toBe('1\u00A0min\u00A035')
    expect(blockLabel(0, 0, 1)).toBe('A')
    expect(blockLabel(1, 1, 2)).toBe('B2')
    expect(prescription(planned(raise))).toBe('3\u00A0×\u00A012–20\u00A0reps · RIR\u00A01 · repos 1\u00A0min')
    expect(prescription(planned(plank))).toBe('3\u00A0×\u00A030–60\u00A0s · repos 1\u00A0min')
    expect(labelOf(MUSCLES, 'quads')).toBe('Quadriceps')
    expect(labelOf(MUSCLES, 'wings' as never)).toBe('wings')
  })

  it('names what a change invalidates', () => {
    expect(isProgramKey(TRAINING_KEYS.programs)).toBe(true)
    expect(isProgramKey(TRAINING_KEYS.program('x', 2))).toBe(true)
    expect(TRAINING_KEYS.program('x', null)).toBe('training-program-x-current')
    expect(isProgramKey(TRAINING_KEYS.exercises)).toBe(false)
    expect(isExerciseKey(TRAINING_KEYS.exercise('x'))).toBe(true)
    expect(isExerciseKey(TRAINING_KEYS.program('x', null)), 'programs show exercise names').toBe(true)
    expect(isExerciseKey('nutrition-recipes')).toBe(false)
  })
})

describe('text helpers', () => {
  it('matches words whatever the accents and case', () => {
    expect(searchKey('  Œuf   Écrasé ')).toBe('oeuf ecrase')
    expect(matches('Développé couché à la barre', 'DEVELOPPE barre')).toBe(true)
    expect(matches('Développé couché', 'incliné')).toBe(false)
  })

  it('reads numbers typed with a comma or a point', () => {
    expect(parseAmount('12,5')).toBe(12.5)
    expect(parseAmount(' 1 500 ')).toBe(1500)
    expect(parseAmount('')).toBeNull()
    expect(parseAmount('abc')).toBeNull()
    expect(parseAmount(Number.NaN)).toBeNull()
    expect(parseAmount(3)).toBe(3)
    expect(parseAmount(null)).toBeNull()
  })
})
