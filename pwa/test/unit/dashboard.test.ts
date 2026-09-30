import { describe, expect, it } from 'vitest'
import { isKnownWidget, moveWidget, viewDays, viewLong, viewPeriod } from '~/features/dashboard/dashboard'
import { QUICK_ACTIONS } from '~/features/shell/quickActions'
import { layout } from '../support/dashboard'

describe('the dashboard views', () => {
  it('cover the day, the last 7 and the last 30 days', () => {
    expect([viewDays('day'), viewDays('week'), viewDays('month')]).toEqual([7, 7, 30])
    expect([viewPeriod('day'), viewPeriod('week'), viewPeriod('month')]).toEqual(['7d', '7d', '30d'])
    expect(viewLong('month')).toBe('ces 30 derniers jours')
  })
})

describe('moving a widget', () => {
  const keys = (widgets: ReturnType<typeof layout>['widgets']) => widgets.map(widget => widget.key)

  it('swaps it with its neighbour, and leaves the list as it is at either end', () => {
    const { widgets } = layout()
    expect(keys(moveWidget(widgets, 1, -1)).slice(0, 2)).toEqual(['nutrition', 'weight'])
    expect(keys(moveWidget(widgets, 1, 1)).slice(1, 3)).toEqual(['meals', 'nutrition'])
    expect(keys(moveWidget(widgets, 0, -1))).toEqual(keys(widgets))
    expect(keys(moveWidget(widgets, widgets.length - 1, 1))).toEqual(keys(widgets))
    expect(keys(widgets)[0]).toBe('weight')
  })

  it('knows the widgets of this version only (a newer server may send more)', () => {
    expect(isKnownWidget({ key: 'weight', visible: true })).toBe(true)
    expect(isKnownWidget({ key: 'sleep' as never, visible: true })).toBe(false)
  })
})

describe('the quick actions', () => {
  it('offer everything spec §14 lists', () => {
    expect(QUICK_ACTIONS.map(action => action.label)).toEqual(['Pesée', 'Aliment', 'Repas (favori)', 'Recette', 'Entraînement', 'Mensuration', 'Photo'])
    // Each opens its form where the page has one; the workout one starts the next workout.
    expect(QUICK_ACTIONS.filter(action => typeof action.to === 'object').map(action => action.label)).toEqual(['Pesée', 'Aliment', 'Mensuration', 'Photo'])
    expect(QUICK_ACTIONS.find(action => action.startsWorkout)?.label).toBe('Entraînement')
  })
})
