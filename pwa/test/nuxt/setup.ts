import { enableAutoUnmount } from '@vue/test-utils'
import { afterEach } from 'vitest'

// Every component mounted by a test is gone before the next one: nothing it registered (router
// guards, window listeners, timers) leaks into another test.
enableAutoUnmount(afterEach)
