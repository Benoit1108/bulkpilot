import type { Component } from 'vue'
import { ChartLine, Dumbbell, House, Settings, Sparkles, Utensils } from 'lucide-vue-next'

export interface NavigationLink {
  to: string
  label: string
  /** Pages reached from this one that it stands for in the navigation (Mensurations for « Mes mesures »). */
  covers?: readonly string[]
}

export interface NavigationItem extends NavigationLink {
  icon: Component
  /** The section's pages, listed under it in the desktop sidebar while it is open; the first is the section's own page. */
  pages?: readonly NavigationLink[]
}

/** Primary sections, in the order of the mobile bottom navigation. */
export const primaryNavigation: readonly NavigationItem[] = [
  { to: '/', label: 'Accueil', icon: House },
  {
    to: '/nutrition',
    label: 'Nutrition',
    icon: Utensils,
    pages: [
      { to: '/nutrition', label: 'Journal' },
      { to: '/nutrition/objectifs', label: 'Objectifs' },
      { to: '/nutrition/repartition', label: 'Répartition' },
      { to: '/nutrition/recettes', label: 'Recettes' },
      { to: '/nutrition/favoris', label: 'Favoris' },
      { to: '/nutrition/aliments', label: 'Aliments' },
    ],
  },
  {
    to: '/training',
    label: 'Training',
    icon: Dumbbell,
    pages: [
      { to: '/training', label: 'Aujourd’hui' },
      { to: '/training/exercices', label: 'Exercices' },
      { to: '/training/seances', label: 'Historique' },
      { to: '/training/programmes', label: 'Programmes' },
    ],
  },
  {
    to: '/progression',
    label: 'Progression',
    icon: ChartLine,
    pages: [
      { to: '/progression', label: 'Tableau de bord' },
      { to: '/progression/analyses', label: 'Analyses' },
      { to: '/progression/objectif', label: 'Objectif' },
      { to: '/progression/mensurations', label: 'Mensurations', covers: ['/progression/mesures'] },
      { to: '/progression/photos', label: 'Photos' },
      { to: '/progression/pesees', label: 'Pesées' },
    ],
  },
  {
    to: '/coach',
    label: 'Coach',
    icon: Sparkles,
    pages: [
      { to: '/coach', label: 'Recommandations' },
      { to: '/coach/historique', label: 'Historique' },
      { to: '/coach/rapports', label: 'Rapports' },
      { to: '/coach/reglages', label: 'Préférences' },
    ],
  },
]

/** Kept apart from the primary sections (header on phones, bottom of the rail on larger screens). */
export const settingsNavigation: NavigationItem = { to: '/reglages', label: 'Réglages', icon: Settings }

export function isActiveSection(itemPath: string, currentPath: string): boolean {
  if (itemPath === '/') return currentPath === '/'
  return currentPath === itemPath || currentPath.startsWith(`${itemPath}/`)
}

/**
 * The page of a section the current path belongs to: the longest match, so that « /nutrition/recettes/12 »
 * is « Recettes » and only « /nutrition » itself (or a page not listed) is the section's own page.
 */
export function activePage(pages: readonly NavigationLink[], currentPath: string): string | null {
  const covering = pages.find(page => page.covers?.some(path => isActiveSection(path, currentPath)))
  if (covering) return covering.to
  const matches = pages.filter(page => isActiveSection(page.to, currentPath))
  return matches.sort((a, b) => b.to.length - a.to.length)[0]?.to ?? null
}
