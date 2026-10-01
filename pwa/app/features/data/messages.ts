import { registerErrorMessages } from '~/services/api/messages'

registerErrorMessages({
  'export.in_progress': 'Un export est déjà en préparation\u00A0: attends qu’il soit prêt.',
  'export.not_found': 'Cet export n’existe plus.',
  'export.invalid_scope': 'Vérifie la période et les données choisies.',
  'export.not_ready': 'Cet export n’est pas encore prêt.',
  'audit.invalid_event': 'Une erreur est survenue. Réessaie.',
  'identity.reauthentication_required': 'Ta dernière connexion est trop ancienne\u00A0: reconnecte-toi, puis confirme sans attendre.',
})
