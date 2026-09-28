import { globSync, readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

/**
 * French typography: « : ; ? ! » are preceded by a non-breaking space (&nbsp; or &#x202F;), never a
 * plain one that could leave the sign alone at the start of a line. Checked on template text only.
 */
const files = globSync('app/**/*.vue', { cwd: new URL('../..', import.meta.url).pathname })

function templateText(source: string): string[] {
  const template = source.slice(source.indexOf('<template>'), source.lastIndexOf('</template>'))
  const withoutTags = template.replace(/<[^>]*>/g, '\n').replace(/\{\{[^}]*\}\}/g, ' ')
  return withoutTags.split('\n').map(line => line.trim()).filter(Boolean)
}

describe('French typography', () => {
  it.each(files)('%s has no plain space before : ; ? !', (file) => {
    const source = readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8')
    const offending = templateText(source).filter(text => / [:;?!](\s|$)/.test(text))

    expect(offending).toEqual([])
  })
})
