import { registerErrorMessages } from '~/services/api/messages'

registerErrorMessages({
  'nutrition.food_not_found': 'Cet aliment n’existe plus.',
  'nutrition.invalid_food': 'Cet aliment ne peut pas être modifié ainsi.',
  'nutrition.invalid_nutrition_facts': 'Ces valeurs nutritionnelles ne sont pas plausibles pour 100 g.',
  'nutrition.invalid_portion': 'Une portion a besoin d’un nom et d’un poids entre 0 et 5 000 g.',
  'nutrition.invalid_quantity': 'La quantité doit être comprise entre 0 et 5 000 g.',
  'nutrition.invalid_journal_day': 'Ce jour ne peut pas être modifié.',
  'nutrition.journal_entry_not_found': 'Cette entrée a déjà été supprimée.',
  'nutrition.invalid_energy_inputs': 'Certaines informations ne permettent pas d’estimer tes besoins.',
  'nutrition.inconsistent_target': 'Les macros ne correspondent pas aux calories visées.',
  'nutrition.invalid_meal_split': 'La répartition doit totaliser 100 %.',
  'nutrition.invalid_meal_slot': 'Ce repas n’existe pas.',
  'nutrition.invalid_batch': 'Ajoute entre 1 et 10 aliments à la fois.',
  'nutrition.invalid_period': 'Les moyennes portent sur 7 ou 30 jours.',
  'nutrition.target_not_found': 'Cet objectif n’existe plus.',
  'nutrition.invalid_recipe': 'Une recette a un nom, 1 à 50 portions et 1 à 50 ingrédients pesés.',
  'nutrition.recipe_not_found': 'Cette recette n’existe plus.',
  'nutrition.invalid_favorite_meal': 'Ce repas est vide\u00A0: ajoute d’abord des aliments.',
  'nutrition.favorite_meal_not_found': 'Ce repas favori n’existe plus.',
})
