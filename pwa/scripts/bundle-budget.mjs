// Fails the production build when the SPA grows past its budget (bundle-budget.json), gzip sizes:
// what the first screen downloads (entry, preloaded chunks, styles), and the largest chunk loaded later.
import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import { gzipSync } from 'node:zlib'

const root = new URL('..', import.meta.url).pathname
const publicDir = join(root, '.output/public')
const budget = JSON.parse(readFileSync(join(root, 'bundle-budget.json'), 'utf8'))

const gzipKb = path => gzipSync(readFileSync(join(publicDir, path))).length / 1024
const html = readFileSync(join(publicDir, 'index.html'), 'utf8')
/** Paths of the tags matching `tag` (whatever the order of their attributes) and the `attribute` they point at. */
const referenced = (tag, attribute) => [...html.matchAll(/<(?:script|link)\b[^>]*>/g)]
  .map(([element]) => element)
  .filter(element => tag.test(element))
  .map(element => element.match(new RegExp(`\\b${attribute}="([^"]+)"`))?.[1]?.replace(/^\//, ''))
  .filter(Boolean)

const initialJs = new Set([
  ...referenced(/^<script\b[^>]*\btype="module"/, 'src'),
  ...referenced(/^<link\b[^>]*\brel="modulepreload"/, 'href'),
])
const initialCss = new Set(referenced(/^<link\b[^>]*\brel="stylesheet"/, 'href'))
const lazyJs = readdirSync(join(publicDir, '_nuxt')).filter(file => file.endsWith('.js')).map(file => `_nuxt/${file}`).filter(file => !initialJs.has(file))

const sum = files => [...files].reduce((total, file) => total + gzipKb(file), 0)
const largest = lazyJs.map(file => ({ file, kb: gzipKb(file) })).sort((a, b) => b.kb - a.kb)[0] ?? { file: '-', kb: 0 }

const measures = [
  { name: 'initialJsKb', label: 'JavaScript du premier écran', kb: sum(initialJs) },
  { name: 'initialCssKb', label: 'CSS du premier écran', kb: sum(initialCss) },
  { name: 'largestLazyJsKb', label: `plus gros morceau chargé ensuite (${largest.file})`, kb: largest.kb },
]

let over = false
for (const { name, label, kb } of measures) {
  const limit = budget[name]
  const ok = kb <= limit
  over ||= !ok
  process.stdout.write(`${ok ? 'ok  ' : 'OVER'} ${label}: ${kb.toFixed(1)} kB gzip (budget ${limit} kB)\n`)
}
if (initialJs.size === 0 || initialCss.size === 0) {
  console.error('No script or stylesheet found in index.html: the budget cannot be checked.')
  process.exit(1)
}
if (over) {
  console.error('Bundle budget exceeded: shrink what grew, or raise bundle-budget.json deliberately (and say why in the commit).')
  process.exit(1)
}
