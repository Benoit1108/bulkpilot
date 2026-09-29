import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import { LEAVE_WITHOUT_SAVING, useUnsavedChanges } from '~/composables/useUnsavedChanges'

/** What the form shows, and what useUnsavedChanges gave it: set by the form each time it is mounted. */
const name = ref('')
let guard: ReturnType<typeof useUnsavedChanges>

const Form = defineComponent({
  setup() {
    name.value = 'Upper'
    guard = useUnsavedChanges(() => ({ name: name.value }))
    return () => h('input', { value: name.value })
  },
})

async function mountForm() {
  const wrapper = await mountSuspended(Form, { route: '/training' })
  return { wrapper, router: useRouter() }
}

/** happy-dom has no dialogs: `answer` is what the user replies to the browser's confirmation. */
function confirmAnswering(answer: boolean) {
  const confirm = vi.fn(() => answer)
  vi.stubGlobal('confirm', confirm)
  return confirm
}

describe('unsaved changes', () => {
  it('lets the user leave an untouched form without asking', async () => {
    const { router } = await mountForm()
    const confirm = confirmAnswering(true)

    await router.push('/nutrition')

    expect(confirm).not.toHaveBeenCalled()
    expect(router.currentRoute.value.path).toBe('/nutrition')
  })

  it('asks before leaving a changed form, and stays when the user says no', async () => {
    const { router } = await mountForm()
    name.value = 'Lower'
    const confirm = confirmAnswering(false)

    await router.push('/nutrition')
    expect(confirm).toHaveBeenCalledWith(LEAVE_WITHOUT_SAVING)
    expect(router.currentRoute.value.path).toBe('/training')

    confirm.mockReturnValue(true)
    await router.push('/nutrition')
    expect(router.currentRoute.value.path).toBe('/nutrition')
  })

  it('stops asking once saved, and when the form is gone', async () => {
    const { wrapper, router } = await mountForm()
    const confirm = confirmAnswering(false)
    name.value = 'Lower'
    guard.markSaved({ name: 'Lower' })
    expect(guard.changed.value).toBe(false)

    name.value = 'Push'
    wrapper.unmount()
    await router.push('/nutrition')
    expect(confirm).not.toHaveBeenCalled()
  })

  it('keeps what was typed while saving as a change', async () => {
    await mountForm()
    name.value = 'Lower'
    const sent = { name: name.value }
    name.value = 'Lower body'

    guard.markSaved(sent)

    expect(guard.changed.value).toBe(true)
  })

  it('lets a lost session go to sign-in without asking', async () => {
    const { router } = await mountForm()
    const confirm = confirmAnswering(false)
    name.value = 'Lower'

    useState(ACCOUNT_STATE_KEY).value = null
    await router.push('/connexion')

    expect(confirm).not.toHaveBeenCalled()
    expect(router.currentRoute.value.path).toBe('/connexion')
    useState(ACCOUNT_STATE_KEY).value = undefined
  })

  it('has the browser warn before closing the app with changes', async () => {
    await mountForm()
    const untouched = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(untouched)
    expect(untouched.defaultPrevented).toBe(false)

    name.value = 'Lower'
    const closing = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(closing)
    expect(closing.defaultPrevented).toBe(true)
  })
})
