import { afterEach, describe, expect, it, vi } from 'vitest'
import { shrinkPhoto } from '~/services/media/shrink'

const photo = (bytes: number) => new File([new Uint8Array(bytes)], 'IMG_2041.jpg', { type: 'image/jpeg' })

function bitmap(width: number, height: number) {
  const close = vi.fn()
  vi.stubGlobal('createImageBitmap', vi.fn(async () => ({ width, height, close })))
  return close
}

function canvas(blob: Blob | null, context: object | null = { drawImage: vi.fn() }) {
  const element = { width: 0, height: 0, getContext: vi.fn(() => context), toBlob: vi.fn((done: (result: Blob | null) => void) => done(blob)) }
  vi.spyOn(document, 'createElement').mockReturnValue(element as unknown as HTMLCanvasElement)
  return element
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('shrinking a photo before it is sent', () => {
  it('draws a large one at 2560 px on its longest side, as a JPEG', async () => {
    const close = bitmap(4000, 3000)
    const shrunk = new Blob(['jpeg'], { type: 'image/jpeg' })
    const drawn = canvas(shrunk)

    await expect(shrinkPhoto(photo(10))).resolves.toBe(shrunk)
    expect([drawn.width, drawn.height]).toEqual([2560, 1920])
    expect(drawn.toBlob).toHaveBeenCalledWith(expect.any(Function), 'image/jpeg', 0.9)
    expect(close).toHaveBeenCalled()
    expect(createImageBitmap).toHaveBeenCalledWith(expect.any(File), { imageOrientation: 'from-image' })
  })

  it('redraws a heavy one even when small enough, to drop its metadata', async () => {
    bitmap(1200, 1600)
    const drawn = canvas(new Blob(['jpeg']))

    await shrinkPhoto(photo(3_000_000))
    expect([drawn.width, drawn.height]).toEqual([1200, 1600])
  })

  it('sends the file as it is when it is already small, or the browser cannot draw it', async () => {
    const small = photo(10)
    const close = bitmap(800, 600)
    await expect(shrinkPhoto(small)).resolves.toBe(small)
    expect(close).toHaveBeenCalled()

    vi.stubGlobal('createImageBitmap', vi.fn(async () => {
      throw new Error('unsupported')
    }))
    await expect(shrinkPhoto(small)).resolves.toBe(small)

    bitmap(4000, 3000)
    canvas(null)
    await expect(shrinkPhoto(small), 'no blob out of the canvas').resolves.toBe(small)

    bitmap(4000, 3000)
    canvas(new Blob(['x']), null)
    await expect(shrinkPhoto(small), 'no 2D context').resolves.toBe(small)

    vi.stubGlobal('createImageBitmap', undefined)
    await expect(shrinkPhoto(small)).resolves.toBe(small)
  })
})
