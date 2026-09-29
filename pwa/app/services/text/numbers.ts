/** A number as a field holds it: typed text ("12,5"), a number, or blank. */
export type Amount = number | string | null

/**
 * Reads a number typed by the user: "12,5", "12.5", " 150 ". Null when blank or not a number,
 * so that an empty field is never sent as 0.
 */
export function parseAmount(value: string | number | null | undefined): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  const text = (value ?? '').trim().replace(/\s/g, '').replace(',', '.')
  if (text === '') return null
  const amount = Number(text)
  return Number.isFinite(amount) ? amount : null
}

/** Blank or unreadable values are left out (undefined): the API then says which field is missing, never "0". */
export function optionalAmount(value: Amount, round = false): number | undefined {
  const parsed = parseAmount(value)
  return parsed === null ? undefined : round ? Math.round(parsed) : parsed
}

/** A whole number (sets, reps, seconds), or undefined when blank. */
export const wholeAmount = (value: Amount): number | undefined => optionalAmount(value, true)
