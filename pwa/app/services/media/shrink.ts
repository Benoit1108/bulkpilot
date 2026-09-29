/**
 * Shrinks a photo in the browser before it is sent (ADR-0027): less data on a phone's connection,
 * and a JPEG drawn from pixels, without the original's metadata. The server re-encodes it anyway
 * and never relies on this; where the browser cannot draw it, the file goes as it is.
 */
export const LONGEST_SIDE = 2560
/** Under this and small enough, the file is sent untouched. */
const SMALL_BYTES = 2_000_000

export async function shrinkPhoto(file: File, longest = LONGEST_SIDE): Promise<Blob> {
  if (typeof createImageBitmap !== 'function') return file
  let bitmap: ImageBitmap
  try {
    // The EXIF orientation is applied, so the pixels are upright once the metadata is gone.
    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
  }
  catch {
    return file
  }
  const scale = Math.min(1, longest / Math.max(bitmap.width, bitmap.height))
  if (scale === 1 && file.size <= SMALL_BYTES) {
    bitmap.close()
    return file
  }
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(bitmap.width * scale)
  canvas.height = Math.round(bitmap.height * scale)
  const context = canvas.getContext('2d')
  if (!context) {
    bitmap.close()
    return file
  }
  context.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
  bitmap.close()
  return new Promise(resolve => canvas.toBlob(blob => resolve(blob ?? file), 'image/jpeg', 0.9))
}
