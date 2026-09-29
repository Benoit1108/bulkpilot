import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import type { AccountState } from '~/features/identity/account'

export const LEAVE_WITHOUT_SAVING = 'Quitter sans enregistrer ? Les changements seront perdus.'

/**
 * Asks before leaving a form whose content changed since it was loaded or last saved: another page
 * of the app (a confirmation) or the app itself (the browser's own warning). `state` returns what
 * would be sent (a form's body()): the form is "changed" when it differs from the saved one.
 */
export function useUnsavedChanges(state: () => unknown) {
  const saved = ref(JSON.stringify(state()))
  const changed = computed(() => JSON.stringify(state()) !== saved.value)
  const account = useState<AccountState>(ACCOUNT_STATE_KEY)

  // A router-wide guard for as long as the form is shown: it also works for a form nested in a page.
  // A lost session (account null) leaves for sign-in without asking: nothing could be saved anyway.
  const router = useRouter()
  let removeGuard: (() => void) | undefined
  onMounted(() => {
    removeGuard = router.beforeEach(() => account.value === null || !changed.value || window.confirm(LEAVE_WITHOUT_SAVING))
  })
  onBeforeUnmount(() => removeGuard?.())
  useEventListener('beforeunload', (event) => {
    if (changed.value) event.preventDefault()
  })

  return {
    changed,
    /**
     * Call once saved, before leaving, with what was sent: edits typed while the request was on its
     * way still count as changes.
     */
    markSaved: (sent: unknown) => {
      saved.value = JSON.stringify(sent)
    },
  }
}
