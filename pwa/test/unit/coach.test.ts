import { describe, expect, it } from 'vitest'
import { isCoachDay, statusLabel } from '~/features/coach/coach'
import { adherencePartText, decisionNotice, proposalMacros, proposalSummary, reportNutrition, reportTraining, reportWeight, weekTitle } from '~/features/coach/format'
import { isTargetDerived, NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { parseAmount, signedAmountText } from '~/services/text/numbers'
import { increase, recommendation, report } from '../support/coach'

describe('coach figures', () => {
  it('writes a calorie proposal as the whole change it makes', () => {
    expect(proposalSummary({ fromKcal: 2350, byKcal: 150, toKcal: 2500, carbohydrateChangeGrams: 38 })).toBe('2\u202F350 → 2\u202F500\u00A0kcal par jour')
    expect(proposalMacros({ fromKcal: 2350, byKcal: 150, toKcal: 2500, carbohydrateChangeGrams: 38 })).toBe('Protéines et lipides inchangés, glucides ajustés (environ +38\u00A0g).')
    expect(proposalMacros({ fromKcal: 2350, byKcal: -100, toKcal: 2250, carbohydrateChangeGrams: -25 })).toBe('Protéines et lipides inchangés, glucides ajustés (environ −25\u00A0g).')
  })

  it('explains each part of the adherence', () => {
    expect(adherencePartText({ part: 'calories', done: 5, expected: 7, score: 71 })).toBe('Calories\u00A0: 5 jours sur 7 à ±10\u00A0% de l’objectif')
    expect(adherencePartText({ part: 'protein', done: 1, expected: 7, score: 14 })).toBe('Protéines\u00A0: 1 jour sur 7 à 90\u00A0% de l’objectif ou plus')
    expect(adherencePartText({ part: 'training', done: 2, expected: 3, score: 67 })).toBe('Entraînement\u00A0: 2 séances sur 3 prévues')
    expect(adherencePartText({ part: 'training', done: 1, expected: 1, score: 100 })).toBe('Entraînement\u00A0: 1 séance sur 1 prévue')
    expect(adherencePartText({ part: 'weigh_ins', done: 3, expected: 3, score: 100 })).toBe('Pesées\u00A0: 3 sur 3 attendues')
  })

  it('lays out the weekly report (spec §59)', () => {
    expect(weekTitle('2026-09-21', '2026-09-29')).toBe('Semaine du 21 septembre')
    expect(weekTitle('2025-12-29', '2026-01-05')).toBe('Semaine du 29 décembre 2025')
    expect(reportWeight(report())).toBe('47,8 → 48,2\u00A0kg (+0,4\u00A0kg)')
    expect(reportWeight(report({ startKg: null, changeKg: null }))).toBe('48,2\u00A0kg')
    expect(reportWeight(report({ startKg: null, endKg: null, changeKg: null }))).toBe('Pas de pesée cette semaine')
    expect(reportNutrition(report())).toBe('2\u202F310\u00A0kcal/j · 98\u00A0g de protéines/j (6 jours saisis)')
    expect(reportNutrition(report({ averageKcal: null, averageProtein: null, loggedDays: 0 }))).toBe('Rien de saisi cette semaine')
    expect(reportTraining(report())).toBe('3/3 séances · 4 records')
    expect(reportTraining(report({ sessions: 1, plannedSessions: null, records: 0 }))).toBe('1 séance · aucun record')
  })

  it('says what the answer did, with the day a postponed one comes back', () => {
    expect(decisionNotice(increase, 'accept')).toBe('Objectif passé à 2\u202F500\u00A0kcal à partir de demain.')
    expect(decisionNotice(increase, 'reject')).toBe('Proposition refusée, ton objectif ne change pas.')
    expect(decisionNotice(recommendation(), 'accept')).toBe('C’est noté.')
    expect(decisionNotice(recommendation({ status: 'postponed', postponedUntil: '2026-10-01T10:00:00+00:00' }), 'postpone')).toBe('Mise de côté jusqu’au 1 octobre 2026.')
  })

  it('names a status after the button that led to it', () => {
    expect(statusLabel({ status: 'accepted', proposal: increase.proposal })).toBe('Acceptée')
    expect(statusLabel({ status: 'rejected', proposal: increase.proposal })).toBe('Refusée')
    expect(statusLabel({ status: 'accepted', proposal: null })).toBe('Vue')
    expect(statusLabel({ status: 'rejected', proposal: null })).toBe('Ignorée')
    expect(statusLabel({ status: 'postponed', proposal: null })).toBe('Reportée')
    expect(statusLabel({ status: 'expired', proposal: null })).toBe('Expirée')
  })

  it('knows which cached data a change touches', () => {
    expect(isCoachDay('coach-2026-09-29')).toBe(true)
    expect(isCoachDay('coach-history')).toBe(false)
    expect(isTargetDerived(NUTRITION_KEYS.targets)).toBe(true)
    expect(isTargetDerived(NUTRITION_KEYS.day('2026-09-29'))).toBe(true)
    expect(isTargetDerived(NUTRITION_KEYS.recipes)).toBe(false)
  })
})

describe('signed amounts', () => {
  it('reads the typographic minus and writes it back', () => {
    expect(parseAmount('−0,25')).toBe(-0.25)
    expect(parseAmount('+0,5')).toBe(0.5)
    expect(signedAmountText(-0.25)).toBe('−0,25')
    expect(signedAmountText(0.5)).toBe('+0,5')
    expect(signedAmountText(0)).toBe('0')
  })
})
