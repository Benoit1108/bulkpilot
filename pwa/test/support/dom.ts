import type { VueWrapper } from '@vue/test-utils'
import { expect } from 'vitest'

/** The button whose visible text or accessible name is exactly `name`. */
export function button(wrapper: VueWrapper, name: string, within = '') {
  const found = wrapper.findAll(`${within} button`.trim()).find(b => b.text() === name || b.attributes('aria-label') === name)
  expect(found, name).toBeDefined()
  return found!
}

/** The first button whose text starts with `text` (list items: name + details). */
export function buttonStartingWith(wrapper: VueWrapper, text: string, within = '') {
  const found = wrapper.findAll(`${within} button`.trim()).find(b => b.text().startsWith(text))
  expect(found, text).toBeDefined()
  return found!
}

/** The form control labelled `label`. */
export function field(wrapper: VueWrapper, label: string) {
  const labelElement = wrapper.findAll('label').find(l => l.text() === label)
  expect(labelElement, label).toBeDefined()
  return wrapper.get(`#${labelElement!.attributes('for')}`)
}

/** Clicks a two-step (confirm) button twice. */
export async function confirm(wrapper: VueWrapper, name: string): Promise<void> {
  await button(wrapper, name).trigger('click')
  await button(wrapper, 'Confirmer la suppression').trigger('click')
}

/** The radio or checkbox inside the label whose text is exactly `text` (chips). */
export function choice(wrapper: VueWrapper, text: string) {
  const label = wrapper.findAll('label').find(l => l.text() === text)
  expect(label, text).toBeDefined()
  return label!.get('input')
}
