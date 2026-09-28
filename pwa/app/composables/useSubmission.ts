import { asApiError } from '~/services/api/client'
import type { ApiError } from '~/services/api/errors'
import { fieldErrors, messageFor } from '~/services/api/messages'

/**
 * State of a form submission: pending flag, a user-facing message and per-field errors from a 422.
 */
export function useSubmission() {
  const pending = ref(false)
  const error = ref<ApiError | null>(null)

  const message = computed(() => (error.value ? messageFor(error.value) : null))
  const fields = computed<Record<string, string>>(() => (error.value ? fieldErrors(error.value) : {}))

  async function run(action: () => Promise<void>): Promise<boolean> {
    if (pending.value) return false
    pending.value = true
    error.value = null
    try {
      await action()
      return true
    }
    catch (caught) {
      error.value = asApiError(caught)
      return false
    }
    finally {
      pending.value = false
    }
  }

  /** Forget the last error (e.g. when the user cancels or switches step). */
  function clear(): void {
    error.value = null
  }

  return { pending: readonly(pending), error: readonly(error), message, fields, run, clear }
}
