import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import BarcodeScanner from '~/features/nutrition/components/BarcodeScanner.vue'
import CustomFoodForm from '~/features/nutrition/components/CustomFoodForm.vue'
import { createBarcodeReader, looksLikeBarcode } from '~/features/nutrition/scanner'
import NutritionPage from '~/pages/nutrition/index.vue'
import { freezeToday, TODAY } from '../support/clock'
import { button, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { day, entry, rice, skyr } from '../support/nutrition'

const { api, readBarcodes, prepareZXingModule } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, readBarcodes: vi.fn(), prepareZXingModule: vi.fn(async () => ({})) }))

mockNuxtImport('useApi', () => () => api.current!.client)
vi.mock('zxing-wasm/reader', () => ({ prepareZXingModule, readBarcodes }))
vi.mock('zxing-wasm/reader/zxing_reader.wasm?url', () => ({ default: '/_nuxt/zxing_reader.wasm' }))

const fake = createFakeApi()
api.current = fake
freezeToday()

const SPREAD = '3017620422003'
const product = {
  name: 'Pâte à tartiner aux noisettes', brand: 'Nutella', quantity: '400 g',
  energyKcal: 539, protein: 6.3, carbohydrate: 57.5, fat: 30.9, fibre: null, sugars: 56.3, saturatedFat: 10.6, salt: 0.1,
  servingGrams: 15, missing: [], suspect: null, complete: true,
}
const spread = { ...skyr, id: '0199a0b1-0000-7000-8000-00000000f0ff', source: 'off' as const, name: product.name, brand: 'Nutella', editable: false, barcode: SPREAD, portions: [{ label: '1 portion', grams: 15 }] }

const mediaDevices = Object.getOwnPropertyDescriptor(navigator, 'mediaDevices')
const srcObject = Object.getOwnPropertyDescriptor(HTMLMediaElement.prototype, 'srcObject')

function withoutCamera(): void {
  Object.defineProperty(navigator, 'mediaDevices', { value: undefined, configurable: true })
}

/** A camera whose frames the browser detector reads as `codes`, one per frame (then nothing). */
function withCamera(codes: string[] = [SPREAD], { torch = false } = {}) {
  const track = Object.assign(new EventTarget(), {
    stop: vi.fn(),
    getCapabilities: () => (torch ? { torch: true } : {}),
    applyConstraints: vi.fn(async () => {}),
  })
  const getUserMedia = vi.fn(async () => ({ getTracks: () => [track], getVideoTracks: () => [track] }))
  Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia }, configurable: true })
  vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue()
  // happy-dom only accepts a real MediaStream there.
  Object.defineProperty(HTMLMediaElement.prototype, 'srcObject', { set: () => {}, configurable: true })
  const frames = [...codes]
  const detect = vi.fn(async () => {
    const code = frames.shift()
    return code ? [{ rawValue: code }] : []
  })
  class Detector {
    static getSupportedFormats = async () => ['ean_13']
    detect = detect
  }
  ;(globalThis as { BarcodeDetector?: unknown }).BarcodeDetector = Detector
  return { track, getUserMedia, detect }
}

function setVisibility(state: 'hidden' | 'visible'): void {
  Object.defineProperty(document, 'visibilityState', { value: state, configurable: true })
  document.dispatchEvent(new Event('visibilitychange'))
}

beforeEach(() => {
  fake.reset()
  clearNuxtData()
  withoutCamera()
  readBarcodes.mockReset()
  readBarcodes.mockResolvedValue([])
  fake.on('GET /api/foods/recent', () => [rice])
  fake.on('GET /api/recipes', () => [])
  fake.on('GET /api/nutrition/favorite-meals', () => [])
  fake.on('GET /api/nutrition/averages', () => ({ days: 7, loggedDays: 0, dailyAverage: null, currentTarget: null }))
  fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
})

afterEach(() => {
  attached.splice(0).forEach(wrapper => wrapper.unmount())
  vi.useRealTimers()
  delete (globalThis as { BarcodeDetector?: unknown }).BarcodeDetector
  delete (document as { visibilityState?: unknown }).visibilityState
  if (mediaDevices) Object.defineProperty(navigator, 'mediaDevices', mediaDevices)
  if (srcObject) Object.defineProperty(HTMLMediaElement.prototype, 'srcObject', srcObject)
})

const attached: Array<{ unmount: () => void }> = []

async function openScanner() {
  const wrapper = await mountSuspended(NutritionPage, { route: '/nutrition', attachTo: document.body })
  attached.push(wrapper)
  await flushPromises()
  await button(wrapper, 'Ajouter un aliment au petit-déjeuner').trigger('click')
  await flushPromises()
  await button(wrapper, 'Scanner un code-barres').trigger('click')
  await flushPromises()
  return wrapper
}

async function type(wrapper: Awaited<ReturnType<typeof openScanner>>, code: string): Promise<void> {
  await field(wrapper, 'Code-barres').setValue(code)
  // Attached to the document: the submit button submits its form.
  await button(wrapper, 'Rechercher ce code').trigger('click')
  await flushPromises()
}

describe('barcode reader', () => {
  it('knows what a barcode looks like', () => {
    expect(looksLikeBarcode('3017620422003')).toBe(true)
    expect(looksLikeBarcode('3017 620-422003')).toBe(true)
    expect(looksLikeBarcode('96385074')).toBe(true)
    expect(looksLikeBarcode('1234567')).toBe(false)
    expect(looksLikeBarcode('abc')).toBe(false)
  })

  it('uses the browser detector when it reads EAN-13', async () => {
    const detect = vi.fn(async () => [{ rawValue: SPREAD }])
    class Detector {
      static getSupportedFormats = async () => ['ean_13', 'ean_8', 'qr_code']
      constructor(public options: { formats: string[] }) {}
      detect = detect
    }
    ;(globalThis as { BarcodeDetector?: unknown }).BarcodeDetector = Detector

    const reader = await createBarcodeReader()

    expect(await reader.read(document.createElement('video'))).toBe(SPREAD)
    expect(readBarcodes).not.toHaveBeenCalled()
  })

  it('falls back to WebAssembly, served by the app, elsewhere', async () => {
    readBarcodes.mockResolvedValueOnce([{ isValid: true, text: SPREAD }])
    const video = document.createElement('video')
    Object.defineProperty(video, 'videoWidth', { value: 640 })
    Object.defineProperty(video, 'videoHeight', { value: 480 })
    const context = { drawImage: vi.fn(), getImageData: vi.fn(() => ({ data: new Uint8ClampedArray(4) })) }
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(context as unknown as CanvasRenderingContext2D)

    const reader = await createBarcodeReader()

    expect(await reader.read(video)).toBe(SPREAD)
    expect(readBarcodes).toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ formats: ['EAN13', 'EAN8', 'UPCA'] }))
    expect(context.drawImage, 'only the band under the guide').toHaveBeenCalledWith(video, 0, 120, 640, 240, 0, 0, 640, 240)
    expect(await reader.read(document.createElement('video')), 'nothing to read before the first frame').toBeNull()

    const [options] = prepareZXingModule.mock.calls.at(-1) as unknown as [{ fireImmediately: boolean, overrides: { locateFile: (path: string, prefix: string) => string } }]
    expect(options.fireImmediately, 'instantiated before the camera loop').toBe(true)
    expect(options.overrides.locateFile('zxing_reader.wasm', 'https://cdn.example/'), 'never from a CDN').toBe('/_nuxt/zxing_reader.wasm')
    expect(options.overrides.locateFile('other.js', '/base/')).toBe('/base/other.js')

    await createBarcodeReader()
    expect(prepareZXingModule, 'loaded once').toHaveBeenCalledTimes(1)
  })

  it('falls back when the browser detector does not read EAN', async () => {
    class QrOnly {
      static getSupportedFormats = async () => ['qr_code']
      detect = vi.fn()
    }
    ;(globalThis as { BarcodeDetector?: unknown }).BarcodeDetector = QrOnly
    readBarcodes.mockResolvedValueOnce([])
    const video = document.createElement('video')
    Object.defineProperty(video, 'videoWidth', { value: 640 })
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({ drawImage: vi.fn(), getImageData: vi.fn() } as unknown as CanvasRenderingContext2D)

    expect(await (await createBarcodeReader()).read(video)).toBeNull()
    expect(readBarcodes).toHaveBeenCalled()
  })
})

describe('barcode scanner', () => {
  it('offers to type the code when there is no camera', async () => {
    const wrapper = await mountSuspended(BarcodeScanner)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('Pas de caméra disponible')
    await field(wrapper, 'Code-barres').setValue('1234')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('8, 12, 13 ou 14 chiffres')
    await field(wrapper, 'Code-barres').setValue('3017 6204 22003')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('code')).toEqual([[SPREAD]])
  })

  it('focuses a mistyped code again', async () => {
    const wrapper = await mountSuspended(BarcodeScanner, { attachTo: document.body })
    await flushPromises()

    await field(wrapper, 'Code-barres').setValue('12')
    await wrapper.get('form').trigger('submit')

    expect(document.activeElement).toBe(field(wrapper, 'Code-barres').element)
    wrapper.unmount()
  })

  it('says when the camera is refused, and tries again on request', async () => {
    const refused = vi.fn(async () => {
      throw new DOMException('no', 'NotAllowedError')
    })
    Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia: refused }, configurable: true })

    const wrapper = await mountSuspended(BarcodeScanner)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('L’accès à la caméra est refusé')
    await button(wrapper, 'Réessayer la caméra').trigger('click')
    await flushPromises()
    expect(refused).toHaveBeenCalledTimes(2)
  })

  it('reads a code from the camera, then turns the camera off', async () => {
    const vibrate = vi.fn()
    Object.defineProperty(navigator, 'vibrate', { value: vibrate, configurable: true })
    const { track } = withCamera()

    const wrapper = await mountSuspended(BarcodeScanner)
    await flushPromises()
    await flushPromises()

    expect(wrapper.emitted('code')).toEqual([[SPREAD]])
    expect(track.stop).toHaveBeenCalled()
    expect(vibrate).toHaveBeenCalled()
    delete (navigator as { vibrate?: unknown }).vibrate
  })

  it('keeps reading frames until one has a code, and tips the user when it takes long', async () => {
    const { detect } = withCamera(['', '', SPREAD])
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })

    const wrapper = await mountSuspended(BarcodeScanner)
    await vi.advanceTimersByTimeAsync(0)
    expect(wrapper.get('[role="status"]').text()).toContain('Place le code-barres dans le cadre')
    expect(wrapper.emitted('code')).toBeUndefined()

    await vi.advanceTimersByTimeAsync(600)
    expect(detect).toHaveBeenCalledTimes(3)
    expect(wrapper.emitted('code')).toEqual([[SPREAD]])

    const slow = withCamera([])
    const waiting = await mountSuspended(BarcodeScanner)
    await vi.advanceTimersByTimeAsync(8000)
    expect(waiting.get('[role="status"]').text()).toContain('Toujours rien')
    waiting.unmount()
    expect(slow.track.stop, 'the camera is off once the scanner is gone').toHaveBeenCalled()
    const reads = slow.detect.mock.calls.length
    await vi.advanceTimersByTimeAsync(1000)
    expect(slow.detect.mock.calls.length, 'no more reads either').toBe(reads)
  })

  it('turns the camera off while the page is hidden, and back on after', async () => {
    const { track, getUserMedia } = withCamera([])
    const wrapper = await mountSuspended(BarcodeScanner)
    await flushPromises()

    setVisibility('hidden')
    expect(track.stop).toHaveBeenCalled()
    setVisibility('visible')
    await flushPromises()

    expect(getUserMedia).toHaveBeenCalledTimes(2)
    expect(wrapper.get('[role="status"]').text()).toContain('Place le code-barres dans le cadre')
    wrapper.unmount()
  })

  it('offers to restart a camera that stopped', async () => {
    const { track, getUserMedia } = withCamera([])
    const wrapper = await mountSuspended(BarcodeScanner)
    await flushPromises()

    track.dispatchEvent(new Event('ended'))
    await flushPromises()
    expect(wrapper.get('[role="status"]').text()).toContain('La caméra s’est interrompue')

    await button(wrapper, 'Réessayer la caméra').trigger('click')
    await flushPromises()
    expect(getUserMedia).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('switches the torch on and off when the camera has one', async () => {
    const { track } = withCamera([], { torch: true })
    const wrapper = await mountSuspended(BarcodeScanner)
    await flushPromises()

    await button(wrapper, 'Lampe').trigger('click')
    await flushPromises()
    expect(track.applyConstraints).toHaveBeenLastCalledWith({ advanced: [{ torch: true }] })
    expect(button(wrapper, 'Lampe').attributes('aria-pressed')).toBe('true')
    await button(wrapper, 'Lampe').trigger('click')
    await flushPromises()
    expect(track.applyConstraints).toHaveBeenLastCalledWith({ advanced: [{ torch: false }] })
    wrapper.unmount()
  })

  it('says when the barcode reader cannot load', async () => {
    const { track } = withCamera([])
    const broken = Object.assign(function () {
      throw new Error('not here')
    }, { getSupportedFormats: async () => ['ean_13'] })
    ;(globalThis as { BarcodeDetector?: unknown }).BarcodeDetector = broken

    const wrapper = await mountSuspended(BarcodeScanner)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('Le lecteur de codes-barres n’a pas pu se charger')
    expect(track.stop, 'no camera left on for nothing').toHaveBeenCalled()
  })
})

describe('scanning a product into the journal', () => {
  it('logs a food already known right away', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => ({ barcode: SPREAD, status: 'known', food: spread, product: null }))
    const wrapper = await openScanner()

    await type(wrapper, SPREAD)

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/barcode/scan')?.body).toEqual({ barcode: SPREAD })
    expect((field(wrapper, 'Quantité').element as HTMLInputElement).value).toBe('15')
  })

  it('shows what Open Food Facts proposes, keeps it once confirmed, then logs it', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => ({ barcode: SPREAD, status: 'found', food: null, product }))
    fake.on('POST /api/nutrition/barcode/confirm', () => spread)
    fake.on('POST /api/nutrition/entries', () => day(TODAY, [entry('breakfast', 15, spread)]))
    const wrapper = await openScanner()

    await type(wrapper, SPREAD)
    const card = wrapper.get('dialog[open] article')
    expect(card.text()).toContain('Pâte à tartiner aux noisettes')
    expect(card.text()).toContain('Nutella · 400 g')
    expect(card.text()).toContain('539 kcal')
    expect(card.text()).toContain('Open Food Facts (ODbL)')
    expect(fake.calls.some(call => call.route === 'POST /api/nutrition/barcode/confirm'), 'nothing kept before the user agrees').toBe(false)

    await button(wrapper, 'Valeurs conformes à l’étiquette').trigger('click')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/barcode/confirm')?.body).toEqual({ barcode: SPREAD })
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/entries')?.body).toMatchObject({ foodId: spread.id, grams: 15, slot: 'breakfast' })
  })

  it('has an incomplete product completed from the label', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => ({ barcode: SPREAD, status: 'found', food: null, product: { ...product, protein: null, fat: null, missing: ['protein', 'fat'], complete: false } }))
    fake.on('POST /api/nutrition/barcode/confirm', () => ({ ...spread, source: 'custom', editable: true }))
    const wrapper = await openScanner()

    await type(wrapper, SPREAD)
    expect(wrapper.get('dialog[open]').text()).toContain('Il manque des valeurs')
    expect(wrapper.findAll('button').some(b => b.text() === 'Valeurs conformes à l’étiquette')).toBe(false)
    expect(wrapper.get('dialog[open] article').text()).toContain('inconnu')
    await button(wrapper, 'Compléter les valeurs').trigger('click')
    await flushPromises()

    expect((field(wrapper, 'Nom').element as HTMLInputElement).value).toBe('Pâte à tartiner aux noisettes')
    expect((field(wrapper, 'Énergie').element as HTMLInputElement).value).toBe('539')
    expect(wrapper.get('dialog[open]').text()).toContain('Absent d’Open Food Facts')
    expect(document.activeElement, 'the first missing value').toBe(field(wrapper, 'Protéines').element)
    expect(wrapper.findAll('label').some(l => l.text() === 'Code-barres'), 'the barcode is the scanned one').toBe(false)
    await field(wrapper, 'Protéines').setValue('6,3')
    await field(wrapper, 'Lipides').setValue('30,9')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/barcode/confirm')?.body).toMatchObject({ barcode: SPREAD, corrections: { name: 'Pâte à tartiner aux noisettes', energyKcal: 539, protein: 6.3, fat: 30.9, barcode: SPREAD, portions: [{ label: '1 portion', grams: 15 }] } })
    expect(wrapper.findAll('label').some(l => l.text() === 'Quantité'), 'then logged').toBe(true)
  })

  it('lets the user enter a product nobody knows', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => {
      throw apiError(404, 'nutrition.product_not_found')
    })
    const wrapper = await openScanner()

    await type(wrapper, SPREAD)

    expect(wrapper.get('dialog[open]').text()).toContain('Produit inconnu d’Open Food Facts')
    expect(wrapper.findAll('button').some(b => b.text() === 'Enregistrer ce produit')).toBe(true)
  })

  it('offers a retry, or typing the product in, when Open Food Facts is down', async () => {
    let down = true
    fake.on('POST /api/nutrition/barcode/scan', () => {
      if (down) throw apiError(503, 'nutrition.product_database_unavailable')
      return { barcode: SPREAD, status: 'found', food: null, product }
    })
    const wrapper = await openScanner()

    await type(wrapper, SPREAD)
    expect(wrapper.get('dialog[open] [role="alert"]').text()).toContain('Open Food Facts ne répond pas')

    down = false
    await button(wrapper, 'Réessayer').trigger('click')
    await flushPromises()
    expect(wrapper.find('dialog[open] article').exists()).toBe(true)

    await button(wrapper, 'Scanner un autre produit').trigger('click')
    expect(wrapper.find('dialog[open] [data-testid="scanner"]').exists()).toBe(true)
    await button(wrapper, 'Rechercher par le nom').trigger('click')
    expect(wrapper.find('dialog[open] [data-testid="scanner"]').exists()).toBe(false)
  })

  it('starts each step at its heading, and says what happens in one live region', async () => {
    let answer: (value: unknown) => void = () => {}
    fake.on('POST /api/nutrition/barcode/scan', () => new Promise((resolve) => {
      answer = resolve
    }))
    const wrapper = await openScanner()
    expect(document.activeElement?.textContent?.trim()).toBe('Scanner le code-barres')

    await type(wrapper, SPREAD)
    expect(document.activeElement?.textContent?.trim()).toBe('Recherche du produit')
    const live = wrapper.findAll('dialog[open] [role="status"]')
    expect(live).toHaveLength(1)
    expect(live[0]!.text()).toContain(`Recherche du produit ${SPREAD}`)

    answer({ barcode: SPREAD, status: 'found', food: null, product })
    await flushPromises()
    expect(document.activeElement?.textContent?.trim()).toBe(product.name)
    expect(wrapper.get('dialog[open] [data-testid="scan-status"]').text()).toContain('Produit trouvé')
    expect(wrapper.get('dialog[open] article').text()).toContain('portion indiquée\u00A0: 15\u00A0g')
    expect(wrapper.get('dialog[open] article').text()).toContain(`Code-barres\u00A0: ${SPREAD}`)
  })

  it('cancels a slow lookup, and ignores its late answer', async () => {
    let answer: (value: unknown) => void = () => {}
    fake.on('POST /api/nutrition/barcode/scan', () => new Promise((resolve) => {
      answer = resolve
    }))
    const wrapper = await openScanner()
    await type(wrapper, SPREAD)

    await button(wrapper, 'Annuler').trigger('click')
    answer({ barcode: SPREAD, status: 'found', food: null, product })
    await flushPromises()

    expect(wrapper.find('dialog[open] [data-testid="scanner"]').exists()).toBe(true)
    expect(wrapper.find('dialog[open] article').exists()).toBe(false)
  })

  it('flags values that do not hold together', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => ({ barcode: SPREAD, status: 'found', food: null, product: { ...product, sugars: 70, suspect: 'macros', complete: false } }))
    const wrapper = await openScanner()

    await type(wrapper, SPREAD)
    const dialog = wrapper.get('dialog[open]')
    expect(dialog.text()).toContain('protéines, glucides et lipides dépassent 100\u00A0g pour 100\u00A0g')
    expect(dialog.findAll('dd span').filter(flag => flag.text() === '(à vérifier)')).toHaveLength(3)

    await button(wrapper, 'Compléter les valeurs').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[open]').text()).toContain('À vérifier sur l’étiquette.')
    expect(document.activeElement, 'the first value to check').toBe(field(wrapper, 'Protéines').element)
  })

  it('shows the violations of corrections on their fields', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => ({ barcode: SPREAD, status: 'found', food: null, product }))
    fake.on('POST /api/nutrition/barcode/confirm', () => {
      throw apiError(422, null, [{ propertyPath: 'corrections.energyKcal', message: 'Cette valeur doit être comprise entre 0 et 950.' }])
    })
    const wrapper = await openScanner()
    await type(wrapper, SPREAD)

    await button(wrapper, 'Corriger pour moi').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[open]').text()).toContain('La version corrigée sera ton aliment')
    await field(wrapper, 'Énergie').setValue('9999')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(field(wrapper, 'Énergie').attributes('aria-invalid')).toBe('true')
    expect(wrapper.get('dialog[open]').text()).toContain('Cette valeur doit être comprise entre 0 et 950.')
  })

  it('keeps the product on screen when confirming fails', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => ({ barcode: SPREAD, status: 'found', food: null, product }))
    fake.on('POST /api/nutrition/barcode/confirm', () => {
      throw apiError(503, 'nutrition.product_database_unavailable')
    })
    const wrapper = await openScanner()
    await type(wrapper, SPREAD)

    await button(wrapper, 'Valeurs conformes à l’étiquette').trigger('click')
    await flushPromises()

    expect(wrapper.get('dialog[open]').text()).toContain('Open Food Facts ne répond pas')
    expect(wrapper.find('dialog[open] article').exists()).toBe(true)
    await button(wrapper, 'Corriger pour moi').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[open]').text(), 'the error belongs to the previous step').not.toContain('Open Food Facts ne répond pas')
  })

  it('has the product typed in when Open Food Facts is down, saying so', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => {
      throw apiError(503, 'nutrition.product_database_unavailable')
    })
    const wrapper = await openScanner()
    await type(wrapper, SPREAD)

    await button(wrapper, 'Saisir le produit').trigger('click')
    await flushPromises()

    expect(wrapper.get('dialog[open]').text()).toContain('Sans Open Food Facts pour l’instant')
    expect(wrapper.get('dialog[open]').text()).not.toContain('Produit inconnu')
  })

  it('has a product typed in when the answer lacks it', async () => {
    fake.on('POST /api/nutrition/barcode/scan', () => ({ barcode: SPREAD, status: 'found', food: null, product: null }))
    const wrapper = await openScanner()

    await type(wrapper, SPREAD)

    expect(wrapper.get('dialog[open]').text()).toContain('Produit inconnu d’Open Food Facts')
  })
})

describe('a custom food barcode', () => {
  it('can be scanned instead of typed', async () => {
    const { track } = withCamera()
    const wrapper = await mountSuspended(CustomFoodForm, { attachTo: document.body })
    attached.push(wrapper)

    await button(wrapper, 'Scanner le code-barres').trigger('click')
    await flushPromises()
    await flushPromises()

    expect((field(wrapper, 'Code-barres').element as HTMLInputElement).value).toBe(SPREAD)
    expect(track.stop).toHaveBeenCalled()
    expect(document.querySelector('dialog[open]'), 'the scanner closes').toBeNull()
  })
})
