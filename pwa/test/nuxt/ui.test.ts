import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import { h } from 'vue'
import MiniBars from '~/components/ui/MiniBars.vue'
import Page from '~/components/ui/Page.vue'
import ProgressBar from '~/components/ui/ProgressBar.vue'
import Ring from '~/components/ui/Ring.vue'
import Segmented from '~/components/ui/Segmented.vue'
import Sparkline from '~/components/ui/Sparkline.vue'
import MacroBars from '~/features/nutrition/components/MacroBars.vue'

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

describe('UiRing', () => {
  it('is an image named by its figures, one ring inside the other, closed past the target', async () => {
    const wrapper = await mountSuspended(Ring, { props: { label: '2 600 kcal sur 2 500 kcal', rings: [{ value: 2600, max: 2500, tone: 'accent' }, { value: 75, max: 150, tone: 'protein' }, { value: 0, max: 300, tone: 'carbs' }] } })

    expect(wrapper.attributes('role')).toBe('img')
    expect(wrapper.attributes('aria-label')).toBe('2 600 kcal sur 2 500 kcal')
    const radii = wrapper.findAll('g').map(ring => Number(ring.get('circle').attributes('r')))
    expect(radii[0]).toBeGreaterThan(radii[1]!)
    expect(radii[1]).toBeGreaterThan(radii[2]!)
    const [outer, protein] = wrapper.findAll('g').map(ring => ring.findAll('circle').at(1))
    const [drawn, length] = outer!.attributes('stroke-dasharray')!.split(' ').map(Number)
    expect(drawn, 'over the target: closed, not more').toBe(length)
    expect(outer!.classes(), 'in its own colour, not an alarm').toContain('stroke-accent')
    const [half, around] = protein!.attributes('stroke-dasharray')!.split(' ').map(Number)
    expect(half! / around!).toBeCloseTo(0.5)
    expect(wrapper.findAll('g')[2]!.findAll('circle'), 'nothing eaten: the track alone').toHaveLength(1)
  })
})

describe('UiSparkline and UiMiniBars', () => {
  it('draw only a shape worth drawing, hidden from screen readers', async () => {
    const line = await mountSuspended(Sparkline, { props: { values: [70, 71, 70.5, 72] } })
    const dot = await mountSuspended(Sparkline, { props: { values: [70] } })
    const bars = await mountSuspended(MiniBars, { props: { values: [2, 0, 4] } })
    const two = await mountSuspended(MiniBars, { props: { values: [2, 3] } })

    expect(line.get('svg').attributes('aria-hidden')).toBe('true')
    expect(line.get('polyline').attributes('points')!.split(' ')).toHaveLength(4)
    expect(dot.find('svg').exists(), 'one point is no line').toBe(false)
    expect(bars.attributes('aria-hidden')).toBe('true')
    expect(bars.findAll('span').map(bar => bar.attributes('style'))).toEqual(['height: 50%;', 'height: 0%;', 'height: 100%;'])
    expect(two.find('span').exists(), 'two periods are no rhythm').toBe(false)
  })
})

describe('MacroBars', () => {
  it('shows each macro short in its colour, read out in full', async () => {
    const wrapper = await mountSuspended(MacroBars, { props: { protein: 98.4, carbohydrate: 160, fat: 41, target: { proteinGrams: 150, carbohydrateGrams: 290, fatGrams: 80 } } })

    expect(wrapper.findAll('dt').map(dt => dt.text())).toEqual(['Protéines', 'Glucides', 'Lipides'])
    const protein = wrapper.findAll('dd')[0]!
    expect(protein.get('[aria-hidden="true"]').text()).toBe('98 / 150\u00A0g')
    expect(protein.get('[role="progressbar"]').attributes('aria-valuetext'), 'the bar reads it in full, once').toBe('98\u00A0g sur 150\u00A0g')
    expect(wrapper.findAll('[role="progressbar"] span').map(fill => fill.classes().find(name => name.startsWith('bg-')))).toEqual(['bg-protein', 'bg-carbs', 'bg-fat'])
  })
})

describe('UiProgressBar past its target', () => {
  it('turns neutral for calories, keeps a nutrient its colour', async () => {
    const calories = await mountSuspended(ProgressBar, { props: { label: 'Calories', value: 2600, max: 2500, valueText: '2 600 kcal sur 2 500 kcal' } })
    const protein = await mountSuspended(ProgressBar, { props: { label: 'Protéines', value: 170, max: 150, valueText: '170 g sur 150 g', tone: 'protein' } })

    expect(calories.get('span').classes()).toContain('bg-muted')
    expect(protein.get('span').classes()).toContain('bg-protein')
    expect(protein.get('span').attributes('style')).toContain('width: 100%')
  })

  it('says the figure alone, without a bar, when there is no target', async () => {
    const wrapper = await mountSuspended(MacroBars, { props: { protein: 98, carbohydrate: 160, fat: 41, target: null } })

    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.findAll('dd').map(dd => dd.text())).toEqual(['98\u00A0g', '160\u00A0g', '41\u00A0g'])
  })
})
