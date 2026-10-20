// Downloads the Everkinetic drawings of the library's exercises into public/exercises (run by hand
// when the library or the mapping changes: `node scripts/exercise-illustrations/fetch.mjs`).
// app/features/training/everkinetic.json maps a library key to an Everkinetic exercise; each gives two frames, the start
// (« relaxation ») and the effort (« tension »). The white page behind each drawing is removed and
// the drawing turned black: the app paints it in the theme's colour through a CSS mask. The
// drawings are CC BY-SA 4.0 (Everkinetic, Greg Priday); the modified copies keep that licence.
import { readFile, writeFile } from 'node:fs/promises'

const SOURCE = 'https://raw.githubusercontent.com/everkinetic/data/master/dist/svg'
const out = new URL('../../public/exercises/', import.meta.url)
const mapping = JSON.parse(await readFile(new URL('../../app/features/training/everkinetic.json', import.meta.url), 'utf8'))

function clean(svg) {
  return svg
    .replace(/<g fill="#(?:FFF|fff|FFFFFF|ffffff)"[^>]*>[\s\S]*?<\/g>/g, '')
    .replace(/fill="#[0-9A-Fa-f]{3,6}"/g, 'fill="#000"')
    .replace(/width="[^"]*" height="[^"]*" /, '')
    .replace(/(\d+\.\d)\d+/g, '$1')
}

for (const [key, id] of Object.entries(mapping)) {
  for (const [frame, name] of [['relaxation', 'start'], ['tension', 'end']]) {
    const response = await fetch(`${SOURCE}/${id}-${frame}.svg`)
    if (!response.ok) throw new Error(`${id}-${frame}.svg: ${response.status}`)
    await writeFile(new URL(`${key.replaceAll('_', '-')}-${name}.svg`, out), clean(await response.text()))
  }
}
process.stdout.write(`${Object.keys(mapping).length} exercises drawn\n`)
