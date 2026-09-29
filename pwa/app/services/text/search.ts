/** Lowercase, accent-free text for filtering lists on the device (the API does the same for search). */
export function searchKey(text: string): string {
  return text.toLowerCase().replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/ß/g, 'ss').normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/\s+/g, ' ').trim()
}

/** Every word of the query appears in the name, accents and case ignored. */
export function matches(name: string, query: string): boolean {
  const key = searchKey(name)
  return searchKey(query).split(' ').every(word => key.includes(word))
}
