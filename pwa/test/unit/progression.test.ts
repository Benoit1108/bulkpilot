import { describe, expect, it } from 'vitest'
import { formatEstimate, formatKg, formatSignedKg, formatSpeed, formatTrend, periodLong, periodLongStart } from '~/features/progression/format'
import { isWeightDerived, PROGRESSION_KEYS } from '~/features/progression/progression'
import { formatFullDay, formatShortDay } from '~/services/calendar/days'

describe('progression figures', () => {
  it('writes weights to the tenth, signed with a true minus', () => {
    expect(formatKg(62)).toBe('62,0\u00A0kg')
    expect(formatSignedKg(0.44)).toBe('+0,4\u00A0kg')
    expect(formatSignedKg(-1.25)).toBe('−1,3\u00A0kg')
    expect(formatSignedKg(0.02)).toBe('0,0\u00A0kg')
    expect(formatSpeed(-0.3)).toBe('−0,3\u00A0kg/semaine')
  })

  it('says the trend, or why it is not known yet', () => {
    expect(formatTrend({ kgPerWeek: 0.42, direction: 'gaining' })).toBe('en hausse (+0,42\u00A0kg/semaine)')
    expect(formatTrend({ kgPerWeek: 0.01, direction: 'stable' })).toBe('stable (+0,01\u00A0kg/semaine)')
    expect(formatTrend({ kgPerWeek: null, direction: null })).toBe('pas encore assez de pesées sur 4 semaines')
  })

  it('always presents the time left as a statistic', () => {
    expect(formatEstimate({ status: 'estimated', weeks: 17 })).toBe('environ 17\u00A0semaines au rythme actuel (estimation statistique)')
    expect(formatEstimate({ status: 'estimated', weeks: 1 })).toBe('environ 1\u00A0semaine au rythme actuel (estimation statistique)')
    expect(formatEstimate({ status: 'reached', weeks: null })).toBe('atteint')
    expect(formatEstimate({ status: 'not_enough_weigh_ins', weeks: null })).toContain('au moins 4 pesées')
    expect(formatEstimate({ status: 'not_moving', weeks: null })).toContain('stable')
    expect(formatEstimate({ status: 'moving_away', weeks: null })).toContain('l’autre sens')
  })

  it('says each period as a whole phrase', () => {
    expect(periodLong('3m')).toBe('sur les 3 derniers mois')
    expect(periodLong('1y')).toBe('sur la dernière année')
    expect(periodLong('all')).toBe('depuis le début')
    expect(periodLongStart('1y')).toBe('Sur la dernière année')
  })

  it('writes days the French way', () => {
    expect(formatFullDay('2026-09-12')).toBe('12 septembre 2026')
    expect(formatShortDay('2026-09-12')).toBe('12 sept.')
  })

  it('knows what a weigh-in makes stale', () => {
    expect(isWeightDerived(PROGRESSION_KEYS.weight('3m', '2026-09-29'))).toBe(true)
    expect(isWeightDerived(PROGRESSION_KEYS.weighIns)).toBe(true)
    expect(isWeightDerived(PROGRESSION_KEYS.goal)).toBe(true)
    expect(isWeightDerived('nutrition-day-2026-09-29')).toBe(false)
  })
})
