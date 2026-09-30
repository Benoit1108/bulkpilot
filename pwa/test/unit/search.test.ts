import { describe, expect, it } from 'vitest'
import { foodResults, PAGES, PER_GROUP, searchPages, shortcutResults, workoutResults } from '~/features/search/sources'
import { primaryNavigation, settingsNavigation } from '~/features/shell/navigation'
import { rice, skyr } from '../support/nutrition'

describe('the search sources', () => {
  it('find pages by their name or their words, accents and case ignored, a few at a time', () => {
    expect(searchPages('MOT DE PASSE').map(result => result.to)).toEqual(['/reglages/compte'])
    expect(searchPages('coach').length).toBeLessThanOrEqual(PER_GROUP)
    expect(new Set(PAGES.map(page => page.label)).size).toBe(PAGES.length)
    // The statistics (spec §15) are found too.
    expect(searchPages('statistiques').map(result => result.label)).toEqual(['Analyses', 'Moyennes nutritionnelles', 'Records', 'Adhérence'])
  })

  it('send an editable food to its page and any other to the journal, searched for', () => {
    expect(foodResults([skyr, rice]).map(result => result.to)).toEqual([
      `/nutrition/aliments/${skyr.id}`,
      { path: '/nutrition', query: { ajouter: 'aliment', recherche: rice.name } },
    ])
  })

  it('lead to every section of the navigation', () => {
    const pages = PAGES.map(page => page.to)
    for (const item of [...primaryNavigation, settingsNavigation]) expect(pages).toContain(item.to)
  })

  it('offer shortcuts before anything is typed, the page one is on aside', () => {
    expect(shortcutResults('/').map(result => result.label)).toEqual(['Ajouter une pesée', 'Ajouter un aliment', 'Démarrer la séance', 'Journal du jour', 'Coach'])
    expect(shortcutResults('/coach').map(result => result.label)).not.toContain('Coach')
    // A form to open is offered on its own page too.
    expect(shortcutResults('/progression').map(result => result.label)).toContain('Ajouter une pesée')
  })

  it('find a session by its day\'s name or its date, the device\'s day (Honolulu here)', () => {
    const workouts = [
      { id: 'a', dayName: 'Pull', startedAt: '2026-09-12T08:00:00Z', finishedAt: null, exercises: 5, workingSets: 15, volumeKg: 1 },
      { id: 'b', dayName: null, startedAt: '2026-09-15T08:00:00Z', finishedAt: null, exercises: 2, workingSets: 6, volumeKg: 1 },
    ]
    expect(workoutResults(workouts, 'pull').map(result => result.id)).toEqual(['workout-a'])
    expect(workoutResults(workouts, '14 septembre').map(result => result.label)).toEqual(['Séance libre'])
  })
})
