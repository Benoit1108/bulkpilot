import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PAGE_HEADING_KEY } from '~/composables/usePageHeading'
import ExercisePage from '~/pages/training/exercices/[id].vue'
import ExercisesPage from '~/pages/training/exercices/index.vue'
import NewExercisePage from '~/pages/training/exercices/nouveau.vue'
import TrainingPage from '~/pages/training/index.vue'
import ProgramPage from '~/pages/training/programmes/[id].vue'
import ProgramsPage from '~/pages/training/programmes/index.vue'
import NewProgramPage from '~/pages/training/programmes/nouveau.vue'
import type { Program, ProgramInput } from '~/features/training/training'
import { button, buttonStartingWith, confirm, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { bench, plank, ppl, raise, yates } from '../support/training'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))

mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)

const fake = createFakeApi()
api.current = fake

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  fake.on('GET /api/exercises', () => [yates, bench, raise, plank])
  fake.on('GET /api/programs', () => [ppl, { ...ppl, id: '0199a0b1-0000-7000-8000-00000000b002', name: 'Full Body', active: false, currentVersion: 1, version: 1, versions: [] }])
  fake.on('GET /api/programs/{id}', () => ppl)
})

describe('training home', () => {
  it('offers each day of the program followed', async () => {
    const wrapper = await mountSuspended(TrainingPage, { route: '/training' })
    await flushPromises()

    expect(wrapper.text()).toContain('PPL Masse')
    expect(wrapper.findAll('button').map(b => b.attributes('aria-label')).filter(label => label?.startsWith('Démarrer'))).toEqual(['Démarrer Push', 'Démarrer Pull'])
    expect(wrapper.text()).toContain('3\u00A0exercices')
  })

  it('leads to a first program, or to choosing one', async () => {
    fake.on('GET /api/programs', () => [])
    const empty = await mountSuspended(TrainingPage, { route: '/training' })
    await flushPromises()
    expect(empty.text()).toContain('Aucun programme suivi')
    expect(empty.findAll('a').find(a => a.text() === 'Créer un programme')?.attributes('href')).toBe('/training/programmes/nouveau')

    clearNuxtData()
    fake.on('GET /api/programs', () => [{ ...ppl, active: false }])
    const none = await mountSuspended(TrainingPage, { route: '/training' })
    await flushPromises()
    expect(none.findAll('a').find(a => a.text() === 'Mes programmes')?.attributes('href')).toBe('/training/programmes')
  })

  it('offers a retry when programs cannot load', async () => {
    fake.on('GET /api/programs', () => {
      throw apiError(503, null)
    })
    const wrapper = await mountSuspended(TrainingPage, { route: '/training' })
    await flushPromises()

    expect(wrapper.text()).toContain('Réessayer')
  })
})

describe('exercises', () => {
  it('filters the list by name and muscle on the device', async () => {
    const wrapper = await mountSuspended(ExercisesPage, { route: '/training/exercices' })
    await flushPromises()
    expect(wrapper.get('[role="status"]').text()).toBe('4\u00A0exercices')
    // By main muscle, each with its picture: a drawing, an image, or the muscles it works.
    const groups = wrapper.findAll('section').map(group => [group.get('h2').text(), group.findAll('li a').map(link => link.text())])
    expect(groups).toEqual([
      ['Pectoraux', ['Développé couché à la barreBarre']],
      ['Haut du dos', ['Rowing YatesBarre · perso · consigne']],
      ['Deltoïdes latéraux', ['Élévations latérales aux haltèresHaltères']],
      ['Abdominaux', ['Gainage (planche)Poids du corps']],
    ])
    expect(wrapper.findAll('li a')[1]!.find('svg[role="img"]').exists(), 'an own exercise shows its muscles').toBe(true)

    await field(wrapper, 'Rechercher un exercice').setValue('ELEVATIONS')
    expect(wrapper.get('[role="status"]').text()).toBe('1\u00A0exercice')
    await field(wrapper, 'Rechercher un exercice').setValue('')
    await field(wrapper, 'Muscle').setValue('triceps')
    expect(wrapper.findAll('li a').map(link => link.text())).toEqual([expect.stringContaining('Développé couché')])
    expect(fake.calls.filter(call => call.route === 'GET /api/exercises'), 'loaded once').toHaveLength(1)
  })

  it('creates an own exercise, sending blanks as missing', async () => {
    fake.on('POST /api/exercises', () => yates)
    const wrapper = await mountSuspended(NewExercisePage, { route: '/training/exercices/nouveau' })

    await field(wrapper, 'Nom').setValue('Rowing Yates')
    await field(wrapper, 'Muscle principal').setValue('upper_back')
    await field(wrapper, 'Biceps').setValue(true)
    await field(wrapper, 'Ce qu’une série enregistre').setValue('duration')
    expect(wrapper.text()).toContain('Durée min.')
    await field(wrapper, 'Durée min.').setValue('')
    await field(wrapper, 'Tempo').setValue(' 2011 ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/exercises')?.body).toMatchObject({ name: 'Rowing Yates', primaryMuscle: 'upper_back', secondaryMuscles: ['biceps'], measurement: 'duration', repMin: undefined, repMax: 12, targetRir: 2, restSeconds: 120, tempo: '2011', description: null, mediaUrl: null })
    expect(navigate).toHaveBeenCalledWith(`/training/exercices/${yates.id}`)
  })

  it('shows field errors from the API', async () => {
    fake.on('POST /api/exercises', () => {
      throw apiError(422, null, [{ propertyPath: 'mediaUrl', message: 'Adresse https attendue.' }, { propertyPath: 'secondaryMuscles', message: 'Six au plus.' }])
    })
    const wrapper = await mountSuspended(NewExercisePage, { route: '/training/exercices/nouveau' })

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Adresse https attendue.')
    expect(wrapper.get('fieldset').attributes('aria-describedby')).toBeTruthy()
    expect(wrapper.text()).toContain('Six au plus.')
  })

  it('shows an own exercise, its cue, and lets it be edited or deleted', async () => {
    fake.on('GET /api/exercises/{id}', () => yates)
    fake.on('GET /api/exercises/{id}/progress', () => ({ id: yates.id, records: [{ kind: 'load', value: 90, loadKg: null, date: '2026-09-20T18:00:00Z', workoutId: 'w1' }], sessions: [{ workoutId: 'w1', date: '2026-09-20T18:00:00Z', workingSets: 3, topLoadKg: 90, estimatedOneRepMax: 105, volumeKg: 2000, bestReps: 8, bestSeconds: null }] }))
    fake.on('PUT /api/exercises/{id}/note', body => ({ ...yates, note: (body as { text: string | null }).text }))
    fake.on('PUT /api/exercises/{id}', body => ({ ...yates, ...(body as object) }))
    fake.on('DELETE /api/exercises/{id}', () => null)
    const wrapper = await mountSuspended(ExercisePage, { route: `/training/exercices/${yates.id}` })
    await flushPromises()
    expect(useState(PAGE_HEADING_KEY).value, 'the exercise names the page').toBe(yates.name)

    expect(wrapper.text()).toContain('Muscles secondairesBiceps')
    expect(wrapper.text()).toContain('6–10\u00A0reps · RIR\u00A02 · repos 3\u00A0min · tempo 2-0-1-1')
    expect(wrapper.findAll('a').find(link => link.text().startsWith('Voir le mouvement'))?.attributes('rel')).toBe('noopener noreferrer')
    expect(wrapper.text()).toContain('charge max\u00A0: 90\u00A0kg')
    expect(wrapper.text()).toContain('90\u00A0kg max · 8\u00A0reps · 3\u00A0séries')
    expect((field(wrapper, 'Consigne').element as HTMLTextAreaElement).value).toBe('Buste à 30°')

    await field(wrapper, 'Consigne').setValue('   ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'PUT /api/exercises/{id}/note')?.body).toEqual({ text: null })
    expect(wrapper.text()).toContain('Consigne retirée.')

    await field(wrapper, 'Nom').setValue('Rowing Yates supination')
    await wrapper.findAll('form')[1]!.trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Exercice enregistré.')

    await confirm(wrapper, 'Supprimer l’exercice')
    await flushPromises()
    expect(navigate).toHaveBeenCalledWith('/training/exercices')
  })

  it('keeps library exercises read-only and says when one is gone', async () => {
    fake.on('GET /api/exercises/{id}', () => bench)
    const library = await mountSuspended(ExercisePage, { route: `/training/exercices/${bench.id}` })
    await flushPromises()
    expect(library.text()).toContain('Exercice de la bibliothèque')
    expect(library.findAll('button').some(b => b.text() === 'Supprimer l’exercice')).toBe(false)
    // Its drawing, then the muscles beside the facts that name them (decoration there), with a key.
    const movement = library.findAll('section').find(section => section.text().startsWith('Le mouvement'))!
    expect(movement.findAll('[role="img"]').map(frame => frame.attributes('aria-label'))).toEqual(['Développé couché à la barre\u00A0: départ', 'Développé couché à la barre\u00A0: effort'])
    expect(library.find('svg[aria-hidden="true"] polygon').exists()).toBe(true)
    expect(library.text()).toContain('principalsecondaire')

    clearNuxtData()
    fake.on('GET /api/exercises/{id}', () => {
      throw apiError(404, 'training.exercise_not_found')
    })
    const gone = await mountSuspended(ExercisePage, { route: '/training/exercices/nope' })
    await flushPromises()
    expect(gone.text()).toContain('Cet exercice n’existe plus.')
  })
})

describe('programs', () => {
  it('lists programs with the one followed', async () => {
    const wrapper = await mountSuspended(ProgramsPage, { route: '/training/programmes' })
    await flushPromises()

    expect(wrapper.findAll('nav a').map(link => link.text())).toEqual([expect.stringContaining('Suivi · version 2 · Push, Pull'), expect.stringContaining('Full Body')])
  })

  it('builds a program: days, exercises from the library, a superset, the order', async () => {
    let sent: ProgramInput | null = null
    fake.on('POST /api/programs', (body) => {
      sent = body as ProgramInput
      return { ...ppl, id: 'new' } satisfies Program
    })
    const wrapper = await mountSuspended(NewProgramPage, { route: '/training/programmes/nouveau' })

    await field(wrapper, 'Nom du programme').setValue('Upper')
    await field(wrapper, 'Nom du jour 1').setValue('Haut')
    await button(wrapper, 'Ajouter un exercice à Haut').trigger('click')
    await flushPromises()
    await field(wrapper, 'Rechercher un exercice').setValue('developpe')
    await buttonStartingWith(wrapper, 'Développé couché', 'dialog[open]').trigger('click')
    await button(wrapper, 'Ajouter un exercice à Haut').trigger('click')
    await flushPromises()
    await buttonStartingWith(wrapper, 'Élévations latérales', 'dialog[open]').trigger('click')
    await wrapper.findAll('button').filter(b => b.text().startsWith('Faire un superset'))[1]!.trigger('click')
    await flushPromises()
    await field(wrapper, 'Muscle').setValue('abs')
    await buttonStartingWith(wrapper, 'Gainage', 'dialog[open]').trigger('click')
    expect(wrapper.text()).toContain('Superset B')
    expect(wrapper.text()).toContain('Enchaîné sans repos avec le suivant.')
    expect(field(wrapper, 'Repos après le tour')).toBeDefined()

    await button(wrapper, 'Monter Élévations latérales aux haltères').trigger('click')
    expect(wrapper.findAll('[data-testid="planned-name"]').map(name => name.text())).toEqual(['A1 Élévations latérales aux haltères', 'A2 Gainage (planche)', 'B Développé couché à la barre'])
    await button(wrapper, 'Retirer Gainage (planche) de Haut').trigger('click')
    await wrapper.get('details summary').trigger('click')
    await field(wrapper, 'Progression').setValue('target_rir')
    await wrapper.findAll('input[type="checkbox"]')[0]!.setValue(true)
    await field(wrapper, 'Consigne pour ce programme').setValue(' Coudes hauts ')

    await button(wrapper, 'Ajouter un jour').trigger('click')
    expect((field(wrapper, 'Nom du jour 2').element as HTMLInputElement).value).toBe('Jour B')
    await button(wrapper, 'Retirer ce jour', 'form > fieldset:nth-of-type(2)').trigger('click')
    await button(wrapper, 'Confirmer', 'form > fieldset:nth-of-type(2)').trigger('click')
    expect(wrapper.findAll('form > fieldset')).toHaveLength(1)

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(sent).toEqual({ name: 'Upper', days: [{ name: 'Haut', blocks: [
      { exercises: [{ exerciseId: raise.id, sets: 3, repMin: 12, repMax: 20, targetRir: 1, restSeconds: 60, strategy: 'target_rir', incrementKg: 2.5, warmupSets: 0, dropSet: true, tempo: null, notes: 'Coudes hauts' }] },
      { exercises: [{ exerciseId: bench.id, sets: 3, repMin: 6, repMax: 10, targetRir: 2, restSeconds: 180, strategy: 'double_progression', incrementKg: 2.5, warmupSets: 0, dropSet: false, tempo: null, notes: null }] },
    ] }] })
    expect(navigate).toHaveBeenCalledWith('/training/programmes/new')
  })

  it('puts the API errors on the planned exercise they are about', async () => {
    fake.on('POST /api/programs', () => {
      throw apiError(422, null, [{ propertyPath: 'days[0].blocks[0].exercises[0].sets', message: 'Entre 1 et 10.' }, { propertyPath: 'days', message: 'Une séance au moins.' }])
    })
    const wrapper = await mountSuspended(NewProgramPage, { route: '/training/programmes/nouveau' })
    await button(wrapper, 'Ajouter un exercice à Jour A').trigger('click')
    await flushPromises()
    await buttonStartingWith(wrapper, 'Développé couché', 'dialog[open]').trigger('click')
    await field(wrapper, 'Séries').setValue('')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(field(wrapper, 'Séries').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Une séance au moins.')
    expect((fake.calls.find(call => call.route === 'POST /api/programs')?.body as ProgramInput).days?.[0]?.blocks?.[0]?.exercises?.[0]?.sets).toBeUndefined()
  })

  it('shows a program, its versions, and switches the one followed', async () => {
    fake.on('POST /api/programs/{id}/deactivate', () => ({ ...ppl, active: false }))
    fake.on('POST /api/programs/{id}/activate', () => ppl)
    const wrapper = await mountSuspended(ProgramPage, { route: `/training/programmes/${ppl.id}` })
    await flushPromises()
    expect(useState(PAGE_HEADING_KEY).value, 'the program names the page').toBe(ppl.name)

    expect(wrapper.text()).toContain('Programme suivi · version 2')
    const day = (name: string) => wrapper.findAll('[role="group"]').find(group => group.find('h3').exists() && group.get('h3').text() === name)!
    const push = day('Push')
    expect(push.text()).toContain('4\u00A0×\u00A06–10\u00A0reps · RIR\u00A02 · repos 3\u00A0min')
    expect(push.text()).toContain('2 d’échauffement · tempo 3-1-1-0 · Progression linéaire')
    expect(push.text()).toContain('Pause sur la poitrine')
    expect(push.findAll('p').filter(kind => kind.text() === 'Superset')).toHaveLength(1)
    expect(push.findAll('[data-testid="exercise-label"]').map(label => label.text())).toEqual(['A', 'B1', 'B2'])
    expect(push.text()).toContain('drop set sur la dernière')
    expect(day('Pull').text()).toContain('Rowing Yates (supprimé)')
    const versions = wrapper.findAll('a').filter(link => link.text().startsWith('Version '))
    expect(versions.map(link => link.text())).toEqual(['Version 2 (actuelle)', 'Version 1'])
    expect(versions.find(link => link.attributes('aria-current') === 'page')?.text()).toBe('Version 2 (actuelle)')

    await button(wrapper, 'Ne plus le suivre').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Plus aucun programme n’est suivi.')
    await button(wrapper, 'Suivre ce programme').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('C’est désormais le programme suivi.')
  })

  it('says when saving made a new version', async () => {
    fake.on('PUT /api/programs/{id}', () => ({ ...ppl, currentVersion: 3, version: 3 }))
    const wrapper = await mountSuspended(ProgramPage, { route: `/training/programmes/${ppl.id}` })
    await flushPromises()

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const body = fake.calls.find(call => call.route === 'PUT /api/programs/{id}')?.body as ProgramInput
    expect(body.days?.[0]?.blocks?.[1]?.exercises?.map(exercise => exercise.exerciseId)).toEqual([raise.id, plank.id])
    expect(body.days?.[0]?.blocks?.[0]?.exercises?.[0]).toMatchObject({ sets: 4, warmupSets: 2, tempo: '3-1-1-0', notes: 'Pause sur la poitrine', strategy: 'linear' })
    expect(wrapper.text()).toContain('Version 3 enregistrée.')
  })

  it('shows an older version read-only', async () => {
    fake.on('GET /api/programs/{id}', () => ({ ...ppl, version: 1, versionCreatedAt: '2026-09-01T10:00:00+00:00', days: [ppl.days[0]!] }))
    const wrapper = await mountSuspended(ProgramPage, { route: `/training/programmes/${ppl.id}?version=1` })
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'GET /api/programs/{id}')?.params).toMatchObject({ query: { version: 1 } })
    expect(wrapper.text()).toContain('Version 1 du 1 septembre 2026 (la version actuelle est la 2)')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('archives a program', async () => {
    fake.on('DELETE /api/programs/{id}', () => null)
    const wrapper = await mountSuspended(ProgramPage, { route: `/training/programmes/${ppl.id}` })
    await flushPromises()

    await button(wrapper, 'Archiver le programme').trigger('click')
    await button(wrapper, 'Confirmer l’archivage').trigger('click')
    await flushPromises()

    expect(navigate).toHaveBeenCalledWith('/training/programmes')
  })

  it('says when a program is gone', async () => {
    fake.on('GET /api/programs/{id}', () => {
      throw apiError(404, 'training.program_not_found')
    })
    const wrapper = await mountSuspended(ProgramPage, { route: '/training/programmes/nope' })
    await flushPromises()

    expect(wrapper.text()).toContain('Ce programme (ou cette version) n’existe plus.')
  })
})
