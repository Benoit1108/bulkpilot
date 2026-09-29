import { registerErrorMessages } from '~/services/api/messages'

registerErrorMessages({
  'training.exercise_not_found': 'Cet exercice n’existe plus.',
  'training.invalid_exercise': 'Cet exercice ne peut pas être enregistré ainsi.',
  'training.invalid_prescription': 'Séries, reps, RIR, repos ou tempo hors des limites (tempo\u00A0: quatre phases comme 3-1-1-0).',
  'training.invalid_program': 'Ce programme ne peut pas être enregistré ainsi (1 à 7 jours aux noms différents, 1 à 15 blocs par jour).',
  'training.program_not_found': 'Ce programme n’existe plus.',
  'training.invalid_workout': 'Cette séance ne peut pas être enregistrée ainsi (série ou horaire incohérent).',
  'training.workout_in_progress': 'Une autre séance est déjà en cours\u00A0: termine-la ou abandonne-la d’abord.',
  'training.workout_not_found': 'Cette séance n’existe plus.',
})
