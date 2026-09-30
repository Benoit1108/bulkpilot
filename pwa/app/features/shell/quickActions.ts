import type { Component } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { Camera, Dumbbell, Apple, CookingPot, Ruler, Scale, Star } from 'lucide-vue-next'

export interface QuickAction {
  label: string
  /** Where its form is: the pages read `?ajouter=` to open it. */
  to: RouteLocationRaw
  icon: Component
  /** Starts the next workout of the program followed (its page when there is none). */
  startsWorkout?: true
}

/** What the global « + » adds (spec §14), each leading to the form that does it. */
export const QUICK_ACTIONS: readonly QuickAction[] = [
  { label: 'Pesée', to: { path: '/progression', query: { ajouter: 'pesee' } }, icon: Scale },
  { label: 'Aliment', to: { path: '/nutrition', query: { ajouter: 'aliment' } }, icon: Apple },
  { label: 'Repas (favori)', to: '/nutrition/favoris', icon: Star },
  { label: 'Recette', to: '/nutrition/recettes/nouvelle', icon: CookingPot },
  { label: 'Entraînement', to: '/training', icon: Dumbbell, startsWorkout: true },
  { label: 'Mensuration', to: { path: '/progression/mensurations', query: { ajouter: 'mensurations' } }, icon: Ruler },
  { label: 'Photo', to: { path: '/progression/photos', query: { ajouter: 'photo' } }, icon: Camera },
]
