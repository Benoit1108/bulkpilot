import { describe, expect, it } from 'vitest'
import { activityDetails, activityLabel, type Activity } from '~/features/activity/activity'
import { exportWhen, periodText, scopeText } from '~/features/data/format'
import { daysText, erasureDay, hoursText } from '~/features/data/policy'
import { momentLabel } from '~/services/calendar/days'
import { accountExport, activity, DATA_POLICY, DOMAINS } from '../support/data'

const NOW = new Date('2026-09-30T10:00:00')

describe('an export', () => {
  it('says what it covers', () => {
    expect(scopeText(accountExport(), DOMAINS)).toBe('Toute la période · toutes les données')
    expect(scopeText(accountExport({ from: '2026-09-01', to: '2026-09-30', domains: ['nutrition', 'training'] }), DOMAINS))
      .toBe('Du 1 septembre 2026 au 30 septembre 2026 · Nutrition, Training')
    expect(scopeText(accountExport({ from: '2026-09-01' }), DOMAINS)).toBe('Depuis le 1 septembre 2026 · toutes les données')
    expect(periodText(null, '2026-09-30')).toBe('Jusqu’au 30 septembre 2026')
  })

  it('says when it was asked for, and until when it can be downloaded', () => {
    expect(exportWhen(accountExport({ requestedAt: '2026-09-30T08:00:00' }), NOW)).toBe('Demandé aujourd’hui à 08:00')
    expect(exportWhen(accountExport({ status: 'ready', expiresAt: '2026-10-01T08:05:00' }), NOW)).toBe('Disponible jusqu’à demain à 08:05')
    expect(momentLabel('2026-09-21T09:00:00', NOW)).toBe('lundi 21 septembre à 09:00')
  })
})

describe('the rules on personal data', () => {
  it('are said as the server sets them', () => {
    expect(daysText(DATA_POLICY.deletionDelayDays)).toBe('7\u00A0jours')
    expect(daysText(365)).toBe('un an')
    expect(daysText(1)).toBe('1\u00A0jour')
    expect(hoursText(DATA_POLICY.exportAvailableHours)).toBe('24\u00A0heures')
    expect(erasureDay(DATA_POLICY, NOW)).toBe('le mercredi 7 octobre')
  })
})

describe('the account activity', () => {
  it('names each action in words, even one it does not know yet', () => {
    expect(activityLabel(activity())).toBe('Connexion')
    expect(activityLabel(activity({ action: 'identity.registered' }))).toBe('Création du compte')
    expect(activityLabel(activity({ action: 'export.downloaded' }))).toBe('Lien de téléchargement créé')
    // A newer server's action: the type says it cannot happen, the code still copes.
    expect(activityLabel(activity({ action: 'media.something_new' as Activity['action'] }))).toBe('Action\u00A0: media.something_new')
  })

  it('says what changed, from when and who changed it', () => {
    const target = (origin: string) => activity({ action: 'nutrition.target_set', details: { previous_kcal: 2200, kcal: 2350, effective_from: '2026-10-01', origin } })
    expect(activityDetails(target('coach'), '2026-09-30')).toBe('2\u202F200\u00A0kcal → 2\u202F350\u00A0kcal, à partir du 1 octobre, par le Coach')
    expect(activityDetails(target('estimate'), '2026-09-30')).toBe('2\u202F200\u00A0kcal → 2\u202F350\u00A0kcal, à partir du 1 octobre, depuis l’estimation')
    expect(activityDetails(target('manual'), '2026-09-30')).toBe('2\u202F200\u00A0kcal → 2\u202F350\u00A0kcal, à partir du 1 octobre')
    expect(activityDetails(activity({ action: 'nutrition.target_set', details: { kcal: 2350 } }))).toBe('2\u202F350\u00A0kcal')
  })

  it('says which recommendation, which device, which code, which date', () => {
    expect(activityDetails(activity({ action: 'coach.recommendation_decided', details: { status: 'accepted', kind: 'calories.increase' } })))
      .toBe('Acceptée\u00A0: augmenter les calories')
    expect(activityDetails(activity({ action: 'coach.recommendation_decided', details: { status: 'rejected', kind: 'something.new' } }))).toBe('Refusée')
    expect(activityDetails(activity({ details: { device: 'Firefox · Linux' } }))).toBe('Firefox · Linux')
    expect(activityDetails(activity({ action: 'identity.recovery_code_used', details: { remaining: 7 } }))).toBe('7\u00A0codes restants')
    expect(activityDetails(activity({ action: 'identity.external_identity_linked', details: { provider: 'google' } }))).toBe('Google')
    expect(activityDetails(activity({ action: 'identity.deletion_scheduled', details: { due_on: '2026-10-07' } }))).toBe('Effacement prévu le mercredi 7 octobre')
    expect(activityDetails(activity({ action: 'export.requested', details: { from: '2026-09-01', to: null, domains: 'nutrition' } })))
      .toBe('Depuis le 1 septembre 2026 · une partie des données')
    expect(activityDetails(activity({ action: 'export.requested', details: {} }))).toBe('Toute la période · toutes les données')
    expect(activityDetails(activity({ action: 'identity.password_changed' }))).toBe('')
    expect(activityDetails(activity({ action: 'identity.password_reset', source: 'system' }))).toBe('Automatiquement')
  })
})
