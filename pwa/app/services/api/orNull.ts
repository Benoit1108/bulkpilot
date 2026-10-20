import { asApiError } from './client'

/** Nothing there is null, not an error (no active program, no weight goal). */
export async function orNull<T>(read: () => Promise<T>): Promise<T | null> {
  try {
    return await read()
  }
  catch (caught) {
    if (asApiError(caught).kind === 'not_found') return null
    throw caught
  }
}
