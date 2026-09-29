import { registerErrorMessages } from '~/services/api/messages'

registerErrorMessages({
  'progression.invalid_weigh_in': 'Cette pesée ne peut pas être enregistrée (entre 20 et 400 kg, pour un jour déjà venu).',
  'progression.weigh_in_not_found': 'Il n’y a pas de pesée ce jour-là.',
  'progression.invalid_weight_goal': 'Cet objectif ne peut pas être enregistré (au moins 0,5 kg d’écart, jalons entre le poids actuel et la cible, 10 au plus). Il faut aussi une pesée.',
  'progression.weight_goal_not_found': 'Aucun objectif de poids n’est suivi.',
  'progression.invalid_day': 'Ce jour n’existe pas.',
  'progression.invalid_measurement': 'Ces mensurations ne peuvent pas être enregistrées (entre 1 et 300 cm, pour un jour déjà venu, au moins une mesure).',
  'progression.measure_not_found': 'Cette mesure n’existe plus ou a été retirée.',
  'progression.measurements_not_found': 'Il n’y a pas de mensurations ce jour-là.',
  'progression.invalid_photo': 'Cette photo ne peut pas être ajoutée (un jour déjà venu, une pose, 500 photos au plus).',
  'progression.photo_not_found': 'Cette photo n’existe plus.',
  // Photos are kept by Media, for Progression (ADR-0027).
  'media.invalid_photo': 'Ce fichier ne peut pas être gardé comme photo\u00A0: il faut une image JPEG, PNG ou WebP fixe, de 15 Mo au plus.',
  'media.not_found': 'Cette photo n’existe plus.',
})
