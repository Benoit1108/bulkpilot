/**
 * French plurals: 0 and 1 take the singular (« 0 séance », « 1 séance », « 2 séances »).
 * `many` defaults to the singular with an s.
 */
export function plural(count: number, one: string, many = `${one}s`): string {
  return Math.abs(count) >= 2 ? many : one
}

/** « 3 séances », with a non-breaking space between the number and the word. */
export function counted(count: number, one: string, many?: string): string {
  return `${count}\u00A0${plural(count, one, many)}`
}
