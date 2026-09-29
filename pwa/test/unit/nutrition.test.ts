import { describe, expect, it } from 'vitest'
import { dayLabel, isDay, localDay, parseDay, shiftDay } from '~/features/nutrition/days'
import { formatGrams, formatKcal, formatMacro, formatNumber, formatSignedKcal } from '~/features/nutrition/format'
import { parseAmount } from '~/services/text/numbers'
import { MEAL_SLOTS, slotAt, slotLabel, toSlot } from '~/features/nutrition/nutrition'
import { matches, searchKey } from '~/services/text/search'
import { energyOfMacros } from '~/features/nutrition/rules'

const NBSP = ' '
const NNBSP = ' '

describe('calendar days', () => {
  it('runs in a time zone away from UTC, so that the next test means something', () => {
    expect(new Date(2026, 8, 28, 23, 30).getTimezoneOffset()).not.toBe(0)
  })

  it('uses the local day, not the UTC one', () => {
    expect(localDay(new Date(2026, 8, 28, 23, 30))).toBe('2026-09-28')
    expect(localDay(new Date(2026, 0, 5))).toBe('2026-01-05')
  })

  it('accepts only real days', () => {
    expect(isDay('2026-09-28')).toBe(true)
    expect(isDay('2026-02-30')).toBe(false)
    expect(isDay('28/09/2026')).toBe(false)
    expect(isDay(['2026-09-28'])).toBe(false)
    expect(isDay(undefined)).toBe(false)
  })

  it('moves across months and years', () => {
    expect(shiftDay('2026-03-01', -1)).toBe('2026-02-28')
    expect(shiftDay('2026-12-31', 1)).toBe('2027-01-01')
    expect(parseDay('2026-09-28').getDate()).toBe(28)
  })

  it('names the days around today, dates the others', () => {
    expect(dayLabel('2026-09-28', '2026-09-28')).toBe('Aujourd’hui')
    expect(dayLabel('2026-09-27', '2026-09-28')).toBe('Hier')
    expect(dayLabel('2026-09-29', '2026-09-28')).toBe('Demain')
    expect(dayLabel('2026-09-21', '2026-09-28')).toBe('lundi 21 septembre')
    expect(dayLabel('2025-12-31', '2026-09-28')).toBe('mercredi 31 décembre 2025')
  })
})

describe('French number display', () => {
  it('keeps numbers and units together', () => {
    expect(formatKcal(2350.4)).toBe(`2${NNBSP}350${NBSP}kcal`)
    expect(formatGrams(12.54)).toBe(`12,5${NBSP}g`)
    expect(formatMacro(143.6)).toBe(`144${NBSP}g`)
    expect(formatNumber(1.725, 2)).toBe('1,73')
  })

  it('signs a surplus or a deficit with a true minus', () => {
    expect(formatSignedKcal(275.2)).toBe(`+275${NBSP}kcal`)
    expect(formatSignedKcal(-550)).toBe(`−550${NBSP}kcal`)
    expect(formatSignedKcal(0.2)).toBe(`0${NBSP}kcal`)
  })
})

describe('meals and macros', () => {
  it('lists the meals in the order of the day, with their French preposition', () => {
    expect(MEAL_SLOTS.map(slot => slot.value)).toEqual(['breakfast', 'lunch', 'snack', 'gainer', 'dinner'])
    expect(slotLabel('gainer')).toBe('Gainer')
    expect(toSlot('snack')).toBe('à la collation')
    expect(toSlot('lunch')).toBe('au déjeuner')
  })

  it('adds macros up with the Atwater factors', () => {
    expect(energyOfMacros(144, 474, 92)).toBe(3300)
  })
})

describe('filtering on the device', () => {
  it('ignores case, accents and ligatures, and needs every word', () => {
    expect(searchKey('  Œufs  brouillés ')).toBe('oeufs brouilles')
    expect(matches('Riz au poulet', 'POULET riz')).toBe(true)
    expect(matches('Riz au poulet', 'pâtes')).toBe(false)
  })
})

describe('amounts typed by the user', () => {
  it('reads a decimal comma or point, and nothing else', () => {
    expect(parseAmount('12,5')).toBe(12.5)
    expect(parseAmount(' 1 500 ')).toBe(1500)
    expect(parseAmount('0.25')).toBe(0.25)
    expect(parseAmount(150)).toBe(150)
    expect(parseAmount('')).toBeNull()
    expect(parseAmount(null)).toBeNull()
    expect(parseAmount('beaucoup')).toBeNull()
    expect(parseAmount(Number.NaN)).toBeNull()
  })
})

describe('meal of the time of day', () => {
  it('follows the day', () => {
    expect(slotAt(new Date(2026, 8, 28, 7))).toBe('breakfast')
    expect(slotAt(new Date(2026, 8, 28, 12))).toBe('lunch')
    expect(slotAt(new Date(2026, 8, 28, 16))).toBe('snack')
    expect(slotAt(new Date(2026, 8, 28, 18))).toBe('gainer')
    expect(slotAt(new Date(2026, 8, 28, 21))).toBe('dinner')
  })

  it('knows ß when filtering', () => {
    expect(searchKey('Weißbier')).toBe('weissbier')
  })
})
