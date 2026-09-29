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
