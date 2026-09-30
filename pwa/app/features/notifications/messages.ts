import { registerErrorMessages } from '~/services/api/messages'

registerErrorMessages({
  'notification.not_found': 'Cette notification n’existe plus.',
  'notification.invalid_settings': 'Ces réglages ne peuvent pas être enregistrés (des heures au format HH:MM).',
  'identity.invalid_time_zone': 'Le fuseau horaire de cet appareil n’est pas reconnu.',
  'identity.invalid_dashboard_layout': 'Cette disposition du tableau de bord ne peut pas être enregistrée.',
  'training.no_active_program': 'Aucun programme actif pour l’instant.',
})
