import drawn from './everkinetic.json'

/**
 * The pictures of the library's exercises, served from public/exercises (where they come from and
 * under which licence: public/exercises/ATTRIBUTION.md, also on the credits page). A drawing has
 * two frames, the start and the effort; a few exercises have a single image instead; the others
 * have none, and show the muscles they work. everkinetic.json maps a library key to its drawing
 * (scripts/exercise-illustrations/fetch.mjs downloads them).
 */
export type Illustration
  = | { kind: 'drawing', start: string, end: string, credit: string }
    | { kind: 'image', src: string, credit: string }

/** The wger images kept (see ATTRIBUTION.md for why the others were not), each with its source work. */
export const WGER_IMAGES: Readonly<Record<string, { author: string, work: string, url: string }>> = {
  dumbbell_row: { author: 'Franpol', work: 'Bent Over Dumbbell Rows (Single Arm)', url: 'https://wger.de/fr/exercise/81/view/' },
  reverse_pec_deck: { author: 'cshep442', work: 'Incline Bench Reverse Fly', url: 'https://wger.de/fr/exercise/828/view/' },
  plank: { author: 'utkb', work: 'Plank', url: 'https://wger.de/fr/exercise/458/view/' },
}

const DRAWING_CREDIT = 'Dessin Everkinetic, CC BY-SA 4.0, modifié'
const file = (key: string) => `/exercises/${key.replaceAll('_', '-')}`

export function illustrationOf(libraryKey: string | null): Illustration | null {
  if (libraryKey === null) return null
  if (Object.hasOwn(drawn, libraryKey)) return { kind: 'drawing', start: `${file(libraryKey)}-start.svg`, end: `${file(libraryKey)}-end.svg`, credit: DRAWING_CREDIT }
  const image = Object.hasOwn(WGER_IMAGES, libraryKey) ? WGER_IMAGES[libraryKey]! : null
  return image ? { kind: 'image', src: `${file(libraryKey)}.webp`, credit: `Image de ${image.author} sur wger, CC BY-SA 4.0, modifiée` } : null
}
