import { mountSuspended } from '@nuxt/test-utils/runtime'
import { afterEach, describe, expect, it, vi } from 'vitest'
import ExerciseIllustration from '~/features/training/components/ExerciseIllustration.vue'
import MuscleMap from '~/features/training/components/MuscleMap.vue'
import { illustrationOf } from '~/features/training/illustrations'
import { playChime, prepareChime } from '~/features/training/restChime'

describe('illustrationOf', () => {
  it('finds a drawing by library key, an image for a few, nothing for an own exercise', () => {
    expect(illustrationOf('barbell_bench_press')).toEqual({ kind: 'drawing', start: '/exercises/barbell-bench-press-start.svg', end: '/exercises/barbell-bench-press-end.svg', credit: 'Dessin Everkinetic, CC BY-SA 4.0, modifié' })
    expect(illustrationOf('plank')).toEqual({ kind: 'image', src: '/exercises/plank.webp', credit: 'Image de utkb sur wger, CC BY-SA 4.0, modifiée' })
    expect(illustrationOf('farmer_walk'), 'no picture whose origin is sure').toBeNull()
    expect(illustrationOf(null)).toBeNull()
  })
})

describe('MuscleMap', () => {
  it('fills the main muscle, lightens the others, names them in words', async () => {
    const wrapper = await mountSuspended(MuscleMap, { props: { primary: 'chest', secondary: ['triceps', 'side_delts'] } })

    expect(wrapper.attributes('aria-label')).toBe('Pectoraux en principal ; triceps, deltoïdes latéraux en secondaire')
    expect(wrapper.findAll('polygon.fill-accent').length, 'both pecs').toBe(2)
    // Side delts show on the front and the back deltoids, the triceps on both views.
    expect(wrapper.findAll('polygon.fill-accent\\/45').length).toBeGreaterThanOrEqual(6)
    expect(wrapper.findAll('g'), 'front and back').toHaveLength(2)
  })

  it('shows one side only when small: the one its main muscle is seen from', async () => {
    const back = await mountSuspended(MuscleMap, { props: { primary: 'hamstrings', secondary: [], oneSide: true } })
    const front = await mountSuspended(MuscleMap, { props: { primary: 'quads', secondary: [], oneSide: true } })

    expect(back.findAll('g')).toHaveLength(1)
    expect(back.attributes('viewBox')).toBe('0 0 1000 2200')
    expect(back.findAll('polygon.fill-accent').length).toBeGreaterThan(0)
    expect(front.findAll('polygon.fill-accent').length).toBeGreaterThan(0)
  })
})

describe('ExerciseIllustration', () => {
  const props = { name: 'Développé couché à la barre', primary: 'chest', secondary: ['triceps'] } as const

  it('paints both frames of a drawing in the theme colour, each named, with its credit', async () => {
    const wrapper = await mountSuspended(ExerciseIllustration, { props: { ...props, libraryKey: 'barbell_bench_press' } })

    const frames = wrapper.findAll('[role="img"]')
    expect(frames.map(frame => frame.attributes('aria-label'))).toEqual(['Développé couché à la barre : départ', 'Développé couché à la barre : effort'])
    expect(frames[0]!.attributes('style')).toContain('/exercises/barbell-bench-press-start.svg')
    expect(frames[0]!.classes()).toContain('bg-ink')
    expect(wrapper.get('figcaption').text()).toContain('Dessin Everkinetic, CC BY-SA 4.0, modifié')
    expect(wrapper.get('figcaption a').attributes('href')).toBe('/reglages/credits')
  })

  it('shows an image with its name, or the muscles when there is no picture', async () => {
    const image = await mountSuspended(ExerciseIllustration, { props: { ...props, name: 'Gainage', libraryKey: 'plank' } })
    const none = await mountSuspended(ExerciseIllustration, { props: { ...props, libraryKey: null } })

    expect(image.get('img').attributes('alt')).toBe('Gainage')
    expect(image.get('img').attributes('loading')).toBe('lazy')
    expect(none.get('svg').attributes('aria-label')).toContain('Pectoraux en principal')
    expect(none.find('figcaption').exists(), 'nothing to credit').toBe(false)
  })

  it('is decoration as a thumbnail beside the name, its mask set once near', async () => {
    vi.stubGlobal('IntersectionObserver', undefined)
    const wrapper = await mountSuspended(ExerciseIllustration, { props: { ...props, libraryKey: 'barbell_bench_press', thumbnail: true } })

    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(wrapper.get('span').attributes('style'), 'the effort frame').toContain('-end.svg')
    vi.unstubAllGlobals()
  })
})

describe('the end of a rest', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('chimes once the audio was opened by the user, and stays silent otherwise', () => {
    const started: number[] = []
    class FakeContext {
      state = 'running'
      currentTime = 0
      destination = {}
      resume = vi.fn(async () => {})
      createGain() {
        return { gain: { setValueAtTime: vi.fn(), exponentialRampToValueAtTime: vi.fn() }, connect: (next: unknown) => next }
      }

      createOscillator() {
        return { frequency: { value: 0 }, connect: (next: unknown) => next, start: (at: number) => started.push(at), stop: vi.fn() }
      }
    }
    playChime()
    expect(started, 'nothing before the user acted').toEqual([])

    vi.stubGlobal('AudioContext', FakeContext)
    prepareChime()
    playChime()
    expect(started, 'two notes').toHaveLength(2)
  })
})
