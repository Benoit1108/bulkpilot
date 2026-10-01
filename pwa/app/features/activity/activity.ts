import type { Recommendation } from '~/features/coach/coach'
import { periodText } from '~/features/data/format'
import type { ResponseOf } from '~/services/api/contract'
import { formatDayMonth, formatLongDay, localDay } from '~/services/calendar/days'
import { formatNumber } from '~/services/text/numbers'
import { counted } from '~/services/text/plural'

type ActivityResponse = ResponseOf<'/api/me/activity', 'get'>[number]
/** An entry of the journal, with the time usePagedList pages on. */
export type Activity = ActivityResponse & { createdAt: string }
/** Every action the server records: a new one without a label below fails the type check. */
export type ActivityAction = ActivityResponse['action']

export const ACTIVITY_KEY = 'account-activity'
export const ACTIVITY_PAGE = 20

export const asPaged = (entry: ActivityResponse): Activity => ({ ...entry, createdAt: entry.occurredAt })

/** What happened, in the user's words (spec §105). */
const LABELS: Record<ActivityAction, string> = {
  'identity.registered': 'Création du compte',
  'identity.signed_in': 'Connexion',
  'identity.sign_in_failed': 'Connexion refusée',
  'identity.second_factor_rejected': 'Code de double authentification refusé',
  'identity.reauthentication_failed': 'Confirmation refusée',
  'identity.password_changed': 'Mot de passe changé',
  'identity.password_reset': 'Mot de passe réinitialisé',
  'identity.email_verified': 'Adresse email confirmée',
  'identity.two_factor_enabled': 'Double authentification activée',
  'identity.two_factor_disabled': 'Double authentification désactivée',
  'identity.recovery_codes_regenerated': 'Nouveaux codes de secours',
  'identity.recovery_code_used': 'Code de secours utilisé',
  'identity.external_identity_linked': 'Compte externe lié',
  'identity.session_revoked': 'Appareil déconnecté',
  'identity.deletion_scheduled': 'Suppression du compte demandée',
  'identity.deletion_cancelled': 'Suppression du compte annulée',
  'identity.account_erased': 'Compte effacé',
  'nutrition.target_set': 'Objectif nutritionnel modifié',
  'coach.recommendation_decided': 'Recommandation du Coach',
  'export.requested': 'Export demandé',
  'export.downloaded': 'Lien de téléchargement créé',
}

/** Where a nutrition target came from. */
const ORIGINS: Record<string, string> = {
  estimate: 'depuis l’estimation',
  coach: 'par le Coach',
}

const DECISIONS: Record<string, string> = {
  accepted: 'Acceptée',
  rejected: 'Refusée',
  postponed: 'Reportée',
  expired: 'Expirée',
}

/** What a recommendation was about, in a few words. */
const KINDS: Record<Recommendation['kind'], string> = {
  'calories.no_target': 'fixer un objectif calorique',
  'calories.no_pace': 'indiquer le rythme visé',
  'calories.increase': 'augmenter les calories',
  'calories.decrease': 'baisser les calories',
  'calories.reach_target_first': 'atteindre d’abord la cible',
  'calories.back_to_target': 'revenir à la cible',
  'protein.low': 'protéines sous la cible',
  'journal.incomplete': 'journal incomplet',
  'weigh_ins.few': 'pesées trop rares',
  'training.missed_sessions': 'séances manquées',
  'training.stall': 'stagnation',
  'training.records': 'records',
}

const isKind = (value: string): value is Recommendation['kind'] => value in KINDS

const PROVIDERS: Record<string, string> = { google: 'Google', apple: 'Apple' }

/** A newer server may record an action this app does not know yet: still shown. */
export const activityLabel = (entry: Activity) => LABELS[entry.action] ?? `Action\u00A0: ${entry.action}`

type Details = Activity['details']
const number = (details: Details, key: string) => (typeof details[key] === 'number' ? details[key] : null)
const text = (details: Details, key: string) => (typeof details[key] === 'string' && details[key] !== '' ? details[key] : null)
const kcal = (value: number | null) => (value === null ? null : `${formatNumber(value, 0)}\u00A0kcal`)

/**
 * The line under the label: what changed, from when and by whom — « 2 200 kcal → 2 350 kcal, à
 * partir du 30 septembre, par le Coach » answers « pourquoi cette valeur a changé ? ».
 */
export function activityDetails(entry: Activity, today = localDay()): string {
  const { details } = entry
  switch (entry.action) {
    case 'nutrition.target_set': {
      const [before, after] = [kcal(number(details, 'previous_kcal')), kcal(number(details, 'kcal'))]
      const from = text(details, 'effective_from')
      const origin = text(details, 'origin')
      return [
        after && (before ? `${before} → ${after}` : after),
        from && `à partir du ${formatDayMonth(from, today)}`,
        origin && ORIGINS[origin],
      ].filter(Boolean).join(', ')
    }
    case 'coach.recommendation_decided': {
      const status = text(details, 'status')
      const kind = text(details, 'kind')
      const decision = status ? DECISIONS[status] ?? status : null
      const about = kind && isKind(kind) ? KINDS[kind] : null
      return [decision, about].filter(Boolean).join('\u00A0: ')
    }
    case 'identity.signed_in':
    case 'identity.session_revoked':
      return text(details, 'device') ?? ''
    case 'identity.recovery_code_used': {
      const remaining = number(details, 'remaining')
      return remaining === null ? '' : counted(remaining, 'code restant', 'codes restants')
    }
    case 'identity.external_identity_linked': {
      const provider = text(details, 'provider')
      return provider ? PROVIDERS[provider] ?? provider : ''
    }
    case 'identity.deletion_scheduled': {
      const due = text(details, 'due_on')
      return due ? `Effacement prévu le ${formatLongDay(due)}` : ''
    }
    case 'export.requested':
      return `${periodText(text(details, 'from'), text(details, 'to'))} · ${text(details, 'domains') ? 'une partie des données' : 'toutes les données'}`
    default:
      return entry.source === 'system' ? 'Automatiquement' : ''
  }
}
