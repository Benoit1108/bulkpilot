import { registerErrorMessages } from '~/services/api/messages'

registerErrorMessages({
  'coach.recommendation_closed': 'Cette recommandation n’est plus proposée\u00A0: relance l’analyse.',
  'coach.target_changed': 'Ton objectif a changé depuis\u00A0: relance l’analyse.',
  'coach.target_unreachable': 'Ce changement donnerait un objectif impossible\u00A0: ajuste-le toi-même dans Nutrition.',
  'coach.recommendation_not_found': 'Cette recommandation n’existe plus.',
  'coach.invalid_settings': 'Ces réglages ne peuvent pas être enregistrés (rythme entre −1 et +1\u00A0kg par semaine, au centième).',
})
