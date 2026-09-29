/**
 * French number display. Intl uses a narrow no-break space between thousands; the unit is
 * separated by a no-break space so that "2 350" and "kcal" never end up on two lines.
 */
const whole = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })
const oneDecimal = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })
const NBSP = ' '

export function formatKcal(kcal: number): string {
  return `${whole.format(Math.round(kcal))}${NBSP}kcal`
}

export function formatGrams(grams: number): string {
  return `${oneDecimal.format(grams)}${NBSP}g`
}

/** Macros are read at a glance: whole grams. */
export function formatMacro(grams: number): string {
  return `${whole.format(Math.round(grams))}${NBSP}g`
}

const numberFormats = new Map<number, Intl.NumberFormat>()

export function formatNumber(value: number, maximumFractionDigits = 1): string {
  let format = numberFormats.get(maximumFractionDigits)
  if (!format) {
    format = new Intl.NumberFormat('fr-FR', { maximumFractionDigits })
    numberFormats.set(maximumFractionDigits, format)
  }
  return format.format(value)
}

/** « +250 kcal », « −300 kcal » (true minus sign). */
export function formatSignedKcal(kcal: number): string {
  const rounded = Math.round(kcal)
  const sign = rounded > 0 ? '+' : rounded < 0 ? '−' : ''
  return `${sign}${formatKcal(Math.abs(rounded))}`
}
