import 'fake-indexeddb/auto'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { claimDevice, deviceOwner, forgetDevice, onDeviceReset, releaseDevice } from '~/services/offline/owner'
import { forgetOfflineStore, memoryStore, offlineStore } from '~/services/offline/store'

afterEach(async () => {
  await (await offlineStore()).clear()
  forgetOfflineStore()
})

describe.each([
  ['IndexedDB', async () => {
    forgetOfflineStore()
    return offlineStore()
  }],
  ['memory', async () => memoryStore()],
])('the offline store (%s)', (_, open) => {
  it('keeps values and a queue in order', async () => {
    const store = await open()
    await store.set('training.workout', { id: 'w', sets: [1, 2] })
    expect(await store.get('training.workout')).toEqual({ id: 'w', sets: [1, 2] })
    await store.remove('training.workout')
    expect(await store.get('training.workout')).toBeUndefined()

    await store.enqueue({ op: 'start' })
    await store.enqueue({ op: 'record_set' })
    const [first, second] = await store.queue()
    expect(second!.seq).toBeGreaterThan(first!.seq)
    await store.update({ ...first!, status: 'failed', error: 'Refusé' })
    expect((await store.queue()).map(item => [item.write, item.status, item.error])).toEqual([[{ op: 'start' }, 'failed', 'Refusé'], [{ op: 'record_set' }, 'pending', null]])
    await store.dequeue(first!.seq)
    await store.dequeue(second!.seq)
    expect(await store.queue()).toEqual([])
    await store.update({ ...first!, status: 'pending' })
    expect(await store.queue(), 'updating a sent write does not bring it back').toEqual([])
  })

  it('keeps a value and its write together, and forgets everything at once', async () => {
    const store = await open()
    await store.commit('training.workout', { id: 'w' }, { op: 'finish' })
    await store.commit('training.plan', { day: 1 })
    expect(await store.get('training.workout')).toEqual({ id: 'w' })
    expect((await store.queue()).map(item => item.write)).toEqual([{ op: 'finish' }])

    await store.clear()
    expect([await store.get('training.workout'), await store.get('training.plan'), await store.queue()]).toEqual([undefined, undefined, []])
  })
})

describe('the store', () => {
  it('falls back to memory when IndexedDB refuses', async () => {
    vi.stubGlobal('indexedDB', undefined)
    forgetOfflineStore()
    const store = await offlineStore()
    await store.set('x', 1)
    expect(await store.get('x')).toBe(1)
  })
})

describe('the device owner', () => {
  it('keeps the data of the account signed in, and wipes it for another one', async () => {
    const reset = vi.fn()
    onDeviceReset(reset)
    const store = await offlineStore()

    await claimDevice('alex')
    await store.set('training.workout', { id: 'w' })
    expect(deviceOwner()).toBe('alex')

    releaseDevice()
    expect(deviceOwner(), 'session expired: nothing is sent').toBeNull()
    await claimDevice('alex')
    expect(await store.get('training.workout'), 'the owner is back: all is still there').toEqual({ id: 'w' })
    expect(reset).not.toHaveBeenCalled()

    await claimDevice('sam')
    expect(await store.get('training.workout'), 'someone else').toBeUndefined()
    expect(reset).toHaveBeenCalledOnce()
    expect(deviceOwner()).toBe('sam')

    await store.set('training.workout', { id: 'w2' })
    await forgetDevice()
    expect([deviceOwner(), await store.get('training.workout')]).toEqual([null, undefined])
    expect(reset).toHaveBeenCalledTimes(2)
  })
})
