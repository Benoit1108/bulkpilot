import type { RouteLocationRaw } from 'vue-router'
import type { Food, Recipe } from '~/features/nutrition/nutrition'
import type { Exercise, Program } from '~/features/training/training'
import type { WorkoutSummary } from '~/features/training/workout'
import { formatFullDay, localDay } from '~/services/calendar/days'
import { matches } from '~/services/text/search'

/**
 * What the palette finds (spec §15), grouped. A later source (natural commands, spec §16) is one
 * more group with the same shape: a label, what it leads to.
 */
type SearchGroup = 'shortcuts' | 'pages' | 'foods' | 'recipes' | 'exercises' | 'programs' | 'workouts'

export interface SearchResult {
  id: string
  group: SearchGroup
  label: string
  detail?: string
  to: RouteLocationRaw
}

export const GROUPS: ReadonlyArray<{ value: SearchGroup, label: string }> = [
  { value: 'shortcuts', label: 'Raccourcis' },
  { value: 'pages', label: 'Pages' },
  { value: 'foods', label: 'Aliments' },
  { value: 'recipes', label: 'Recettes' },
  { value: 'exercises', label: 'Exercices' },
  { value: 'programs', label: 'Programmes' },
  { value: 'workouts', label: 'Séances' },
]

/** At most this many of each group: the palette is a way in, not a list to scroll. */
export const PER_GROUP = 5

/** The pages one goes to, with the words people may type to find them. */
export const PAGES: ReadonlyArray<{ label: string, to: string, words: string }> = [
  { label: 'Accueil', to: '/', words: 'tableau de bord dashboard' },
  { label: 'Journal alimentaire', to: '/nutrition', words: 'nutrition repas calories macros' },
  { label: 'Objectifs nutritionnels', to: '/nutrition/objectifs', words: 'calories cible estimation' },
  { label: 'Répartition des repas', to: '/nutrition/repartition', words: 'repas pourcentages' },
  { label: 'Mes aliments', to: '/nutrition/aliments', words: 'aliments perso' },
  { label: 'Recettes', to: '/nutrition/recettes', words: 'plats portions' },
  { label: 'Repas favoris', to: '/nutrition/favoris', words: 'favoris' },
  { label: 'Training', to: '/training', words: 'entrainement seance demarrer' },
  { label: 'Historique des séances', to: '/training/seances', words: 'seances historique records' },
  { label: 'Programmes', to: '/training/programmes', words: 'programme ppl' },
  { label: 'Exercices', to: '/training/exercices', words: 'exercices bibliotheque' },
  { label: 'Progression', to: '/progression', words: 'poids pesee tendance' },
  { label: 'Objectif de poids', to: '/progression/objectif', words: 'jalons cible' },
  { label: 'Toutes les pesées', to: '/progression/pesees', words: 'pesees poids' },
  { label: 'Mensurations', to: '/progression/mensurations', words: 'tour de bras taille mesures' },
  { label: 'Photos', to: '/progression/photos', words: 'photos comparaison' },
  { label: 'Analyses', to: '/progression/analyses', words: 'statistiques graphiques calories poids volume' },
  { label: 'Moyennes nutritionnelles', to: '/progression/analyses', words: 'statistiques moyennes calories proteines semaine' },
  { label: 'Records', to: '/training/seances', words: 'statistiques pr records battus' },
  { label: 'Coach', to: '/coach', words: 'recommandations' },
  { label: 'Adhérence', to: '/coach', words: 'statistiques adherence score regularite' },
  { label: 'Historique du Coach', to: '/coach/historique', words: 'recommandations decisions' },
  { label: 'Rapports hebdomadaires', to: '/coach/rapports', words: 'rapport semaine' },
  { label: 'Réglages du Coach', to: '/coach/reglages', words: 'personnalite ton rythme' },
  { label: 'Notifications', to: '/notifications', words: 'alertes' },
  { label: 'Réglages', to: '/reglages', words: 'parametres theme' },
  { label: 'Profil', to: '/reglages/profil', words: 'prenom taille' },
  { label: 'Compte et sécurité', to: '/reglages/compte', words: 'mot de passe appareils double authentification 2fa' },
  { label: 'Crédits et licences', to: '/reglages/credits', words: 'licences sources illustrations auteurs' },
]

/**
 * Before anything is typed: what one comes to do most (and later, the natural commands of §16),
 * the page the user is on aside.
 */
export const SHORTCUTS: ReadonlyArray<{ label: string, to: RouteLocationRaw }> = [
  { label: 'Ajouter une pesée', to: { path: '/progression', query: { ajouter: 'pesee' } } },
  { label: 'Ajouter un aliment', to: { path: '/nutrition', query: { ajouter: 'aliment' } } },
  { label: 'Démarrer la séance', to: '/training' },
  { label: 'Journal du jour', to: '/nutrition' },
  { label: 'Coach', to: '/coach' },
  { label: 'Tableau de bord', to: '/' },
]

const first = (results: SearchResult[]) => results.slice(0, PER_GROUP)

export function shortcutResults(currentPath: string): SearchResult[] {
  // A form to open is useful anywhere; going to the page one is on, not.
  return first(SHORTCUTS.filter(shortcut => shortcut.to !== currentPath)
    .map(shortcut => ({ id: `shortcut-${shortcut.label}`, group: 'shortcuts', label: shortcut.label, to: shortcut.to })))
}

export function searchPages(query: string): SearchResult[] {
  return first(PAGES.filter(page => matches(`${page.label} ${page.words}`, query))
    .map(page => ({ id: `page-${page.label}`, group: 'pages', label: page.label, to: page.to })))
}

/** A food the user can edit opens its page; any other is added to today's journal. */
export function foodResults(foods: Food[]): SearchResult[] {
  return first(foods.map(food => ({
    id: `food-${food.id}`,
    group: 'foods',
    label: food.name,
    detail: food.editable ? 'Mon aliment' : food.brand ?? undefined,
    to: food.editable ? `/nutrition/aliments/${food.id}` : { path: '/nutrition', query: { ajouter: 'aliment', recherche: food.name } },
  })))
}

export function recipeResults(recipes: Recipe[], query: string): SearchResult[] {
  return first(recipes.filter(recipe => matches(recipe.name, query))
    .map(recipe => ({ id: `recipe-${recipe.id}`, group: 'recipes', label: recipe.name, to: `/nutrition/recettes/${recipe.id}` })))
}

export function exerciseResults(exercises: Exercise[], query: string): SearchResult[] {
  return first(exercises.filter(exercise => matches(exercise.name, query))
    .map(exercise => ({ id: `exercise-${exercise.id}`, group: 'exercises', label: exercise.name, to: `/training/exercices/${exercise.id}` })))
}

export function programResults(programs: Program[], query: string): SearchResult[] {
  return first(programs.filter(program => matches(program.name, query))
    .map(program => ({ id: `program-${program.id}`, group: 'programs', label: program.name, detail: program.active ? 'Suivi' : undefined, to: `/training/programmes/${program.id}` })))
}

/** A session is found by its day's name or its date (« pull », « 12 septembre »). */
export function workoutResults(workouts: WorkoutSummary[], query: string): SearchResult[] {
  return first(workouts.map((workout) => {
    const day = formatFullDay(localDay(new Date(workout.startedAt)))
    return { workout, day, name: workout.dayName ?? 'Séance libre' }
  }).filter(({ day, name }) => matches(`${name} ${day}`, query))
    .map(({ workout, day, name }) => ({ id: `workout-${workout.id}`, group: 'workouts', label: name, detail: day, to: `/training/seances/${workout.id}` })))
}
