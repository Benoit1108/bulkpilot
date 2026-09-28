/**
 * Reads EAN/UPC barcodes from the camera. The browser's BarcodeDetector when it knows EAN-13
 * (Chrome on Android); otherwise ZXing compiled to WebAssembly (Safari on iPhone…), loaded only
 * then and served by the app itself (never from a CDN).
 */
export interface BarcodeReader {
  read: (video: HTMLVideoElement) => Promise<string | null>
}

interface DetectedBarcode { rawValue: string }
interface BarcodeDetectorLike { detect: (source: HTMLVideoElement) => Promise<DetectedBarcode[]> }
interface BarcodeDetectorConstructor {
  new (options: { formats: string[] }): BarcodeDetectorLike
  getSupportedFormats?: () => Promise<string[]>
}

/** UPC-E is left out: its 8 digits would be mistaken for an EAN-8 (rare on French shelves). */
const NATIVE_FORMATS = ['ean_13', 'ean_8', 'upc_a']

/**
 * The band of the frame under the on-screen guide (see BarcodeScanner): decoding it alone is
 * faster, and ignores other codes around.
 */
const BAND = { top: 0.25, height: 0.5 }

/**
 * @throws when the WebAssembly reader cannot be loaded (offline before its first use…)
 */
export async function createBarcodeReader(): Promise<BarcodeReader> {
  const Detector = (globalThis as { BarcodeDetector?: BarcodeDetectorConstructor }).BarcodeDetector
  const supported = Detector ? await Detector.getSupportedFormats?.().catch(() => []) ?? [] : []
  if (Detector && supported.includes('ean_13')) {
    const detector = new Detector({ formats: NATIVE_FORMATS.filter(format => supported.includes(format)) })
    return {
      async read(video) {
        const [code] = await detector.detect(video)
        return code?.rawValue ?? null
      },
    }
  }
  return createWasmReader()
}

type ReadBarcodes = typeof import('zxing-wasm/reader')['readBarcodes']

/** Loaded once per page: reopening the scanner does not fetch or compile it again. */
let wasm: Promise<ReadBarcodes> | null = null

function loadWasm(): Promise<ReadBarcodes> {
  wasm ??= (async () => {
    const [{ prepareZXingModule, readBarcodes }, { default: wasmUrl }] = await Promise.all([
      import('zxing-wasm/reader'),
      import('zxing-wasm/reader/zxing_reader.wasm?url'),
    ])
    // Instantiated now, so that a failure is known before the camera loop starts.
    await prepareZXingModule({
      overrides: { locateFile: (path: string, prefix: string) => (path.endsWith('.wasm') ? wasmUrl : prefix + path) },
      fireImmediately: true,
    })
    return readBarcodes
  })().catch((error: unknown) => {
    wasm = null
    throw error
  })
  return wasm
}

async function createWasmReader(): Promise<BarcodeReader> {
  const readBarcodes = await loadWasm()
  const canvas = document.createElement('canvas')
  const context = canvas.getContext('2d', { willReadFrequently: true })
  return {
    async read(video) {
      if (!context || video.videoWidth === 0) return null
      const top = Math.round(video.videoHeight * BAND.top)
      const width = video.videoWidth
      const height = Math.max(1, Math.round(video.videoHeight * BAND.height))
      // Resizing clears and reallocates the canvas: only when the camera resolution changes.
      if (canvas.width !== width) canvas.width = width
      if (canvas.height !== height) canvas.height = height
      context.drawImage(video, 0, top, width, height, 0, 0, width, height)
      const [result] = await readBarcodes(context.getImageData(0, 0, width, height), {
        formats: ['EAN13', 'EAN8', 'UPCA'],
        maxNumberOfSymbols: 1,
      })
      return result?.isValid ? result.text : null
    },
  }
}

/** What the API accepts; the server checks the digits. */
export function looksLikeBarcode(text: string): boolean {
  return /^(\d{8}|\d{12,14})$/.test(text.replace(/[\s-]/g, ''))
}
