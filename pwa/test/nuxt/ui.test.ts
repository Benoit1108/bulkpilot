import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import { h } from 'vue'
import Page from '~/components/ui/Page.vue'
import Segmented from '~/components/ui/Segmented.vue'

const PERIODS = [{ value: 'week', label: 'Semaine' }, { value: 'month', label: 'Mois', long: '30 derniers jours' }] as const

describe('UiSegmented', () => {
  it('is a group of radios named by its legend, the long name read after the short one', async () => {
    const wrapper = await mountSuspended(Segmented, { props: { 'modelValue': 'week', 'legend': 'Période', 'name': 'period', 'options': PERIODS, 'onUpdate:modelValue': () => {} } })

    expect(wrapper.get('legend').text()).toBe('Période')
    const radios = wrapper.findAll('input[type="radio"]')
    expect(radios.map(radio => (radio.element as HTMLInputElement).checked)).toEqual([true, false])
    expect(wrapper.findAll('label').map(label => label.text())).toEqual(['Semaine', 'Mois (30 derniers jours)'])
  })

  it('can keep its legend for screen readers only, and goes three by row on a phone past four segments', async () => {
    const six = Array.from({ length: 6 }, (_, i) => ({ value: String(i), label: `${i}\u00A0j` }))
    const wrapper = await mountSuspended(Segmented, { props: { 'modelValue': '0', 'legend': 'Période', 'name': 'six', 'options': six, 'hideLegend': true, 'onUpdate:modelValue': () => {} } })
    const few = await mountSuspended(Segmented, { props: { 'modelValue': 'week', 'legend': 'Période', 'name': 'few', 'options': PERIODS, 'onUpdate:modelValue': () => {} } })

    expect(wrapper.get('legend').classes()).toContain('sr-only')
    expect(wrapper.get('fieldset > div').classes()).toContain('max-sm:grid-cols-3')
    expect(few.get('legend').classes()).not.toContain('sr-only')
    expect(few.get('fieldset > div').classes()).not.toContain('max-sm:grid-cols-3')
  })

  it('reports the segment chosen', async () => {
    const chosen: string[] = []
    const wrapper = await mountSuspended(Segmented, { props: { 'modelValue': 'week', 'legend': 'Période', 'name': 'period', 'options': PERIODS, 'onUpdate:modelValue': (value: string) => chosen.push(value) } })

    await wrapper.findAll('input')[1]!.setValue(true)

    expect(chosen).toEqual(['month'])
  })
})

describe('UiPage', () => {
  it('reads as one column by default, and flows cards into two columns on wide screens, what concerns the page above them', async () => {
    const reading = await mountSuspended(Page, { slots: { top: () => h('a', 'Retour'), default: () => h('p', 'Formulaire') } })
    const columns = await mountSuspended(Page, { props: { layout: 'columns' }, slots: { top: () => h('a', 'Retour'), default: () => [h('section', 'Un'), h('section', 'Deux')] } })

    expect(reading.classes()).toContain('max-w-reading')
    expect(reading.findAll(':scope > *').map(child => child.text())).toEqual(['Retour', 'Formulaire'])
    expect(columns.classes()).not.toContain('max-w-reading')
    const flow = columns.get('.xl\\:columns-2')
    expect(flow.findAll(':scope > section').map(card => card.text()), 'the cards flow').toEqual(['Un', 'Deux'])
    expect(columns.get('a').element.parentElement, 'the way back sits above both columns').toBe(columns.element)
  })
})
