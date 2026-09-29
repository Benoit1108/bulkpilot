import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import LineChart from '~/components/ui/LineChart.vue'

const base = {
  title: 'Poids',
  summary: 'En hausse.',
  formatY: (value: number) => `${value} kg`,
  formatX: (day: string) => day.slice(5),
}
const points = [{ x: '2026-09-01', y: 50 }, { x: '2026-09-15', y: 51 }, { x: '2026-09-29', y: 52 }]

describe('UiLineChart', () => {
  it('draws a lone value as a point', async () => {
    const wrapper = await mountSuspended(LineChart, { props: { ...base, series: [{ name: 'Pesées', variant: 'line', points: [points[0]!] }] } })

    expect(wrapper.findAll('svg[role="group"] circle')).toHaveLength(1)
    expect(wrapper.find('svg[role="group"] path[fill="none"]').exists()).toBe(false)
  })

  it('aligns the first and last day labels on the ends', async () => {
    const wrapper = await mountSuspended(LineChart, { props: { ...base, series: [{ name: 'Pesées', variant: 'line', points }] } })

    const labels = wrapper.findAll('svg text').filter(text => /^\d\d-\d\d$/.test(text.text()))
    expect(labels[0]!.attributes('text-anchor')).toBe('start')
    expect(labels.at(-1)!.attributes('text-anchor')).toBe('end')
  })

  it('keeps the scale on the data and pins a mark beyond it to the edge', async () => {
    const wrapper = await mountSuspended(LineChart, {
      props: { ...base, series: [{ name: 'Pesées', variant: 'line', points }], marks: [{ y: 60, label: 'Objectif 60 kg' }, { y: 51, label: 'Jalon 51 kg' }] },
    })

    const values = wrapper.findAll('svg text').map(text => text.text())
    expect(values).not.toContain('60 kg')
    expect(values).toContain('Objectif 60 kg')
    expect(wrapper.findAll('line.stroke-success'), 'only the mark in range is a line').toHaveLength(1)
  })

  it('reads the days with the arrows, and Escape or leaving closes the reading', async () => {
    const wrapper = await mountSuspended(LineChart, { props: { ...base, series: [{ name: 'Pesées', variant: 'line', points }] } })
    const chart = wrapper.get('svg[role="group"]')
    const status = () => wrapper.get('[role="status"]').text()

    expect(chart.attributes('aria-describedby')!.split(' ').map(id => wrapper.find(`[id="${id}"]`).text())).toContain('Flèches gauche et droite pour parcourir les jours, Échap pour fermer.')
    await chart.trigger('keydown', { key: 'End' })
    expect(status()).toBe('09-29 — Pesées\u00A0: 52 kg')
    await chart.trigger('keydown', { key: 'ArrowLeft' })
    expect(status()).toBe('09-15 — Pesées\u00A0: 51 kg')
    expect(wrapper.find('p[aria-hidden="true"]').text()).toBe('09-15 — Pesées\u00A0: 51 kg')

    await chart.trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('p[aria-hidden="true"]').exists()).toBe(false)
    expect(status()).toBe('')

    await chart.trigger('keydown', { key: 'Home' })
    await chart.trigger('blur')
    expect(status(), 'nothing left to announce once the chart is left').toBe('')
  })

  it('shows the values under a finger and keeps them when it lifts', async () => {
    const wrapper = await mountSuspended(LineChart, { props: { ...base, series: [{ name: 'Pesées', variant: 'line', points }] } })
    const chart = wrapper.get('svg[role="group"]')
    const reading = () => wrapper.find('p[aria-hidden="true"]')
    chart.element.getBoundingClientRect = () => ({ left: 0, width: 640 }) as DOMRect

    await chart.trigger('pointerdown', { clientX: 630, pointerType: 'touch' })
    expect(reading().text()).toContain('09-29')
    expect(reading().attributes('style')).toContain('translateX(-')
    await chart.trigger('pointerleave', { pointerType: 'touch' })
    expect(reading().exists()).toBe(true)
    await chart.trigger('pointerleave', { pointerType: 'mouse' })
    expect(reading().exists()).toBe(false)
  })
})
