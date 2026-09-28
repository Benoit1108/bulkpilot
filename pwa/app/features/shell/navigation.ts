import type { Component } from 'vue'
import { ChartLine, Dumbbell, House, Settings, Sparkles, Utensils } from 'lucide-vue-next'

export interface NavigationItem {
  to: string
  label: string
  icon: Component
}

/** Primary sections, in the order of the mobile bottom navigation. */
export const primaryNavigation: readonly NavigationItem[] = [
  { to: '/', label: 'Accueil', icon: House },
  { to: '/nutrition', label: 'Nutrition', icon: Utensils },
  { to: '/training', label: 'Training', icon: Dumbbell },
  { to: '/progression', label: 'Progression', icon: ChartLine },
  { to: '/coach', label: 'Coach', icon: Sparkles },
]

/** Kept apart from the primary sections (header on phones, bottom of the rail on larger screens). */
export const settingsNavigation: NavigationItem = { to: '/reglages', label: 'Réglages', icon: Settings }

export function isActiveSection(itemPath: string, currentPath: string): boolean {
  if (itemPath === '/') return currentPath === '/'
  return currentPath === itemPath || currentPath.startsWith(`${itemPath}/`)
}
