/**
 * What the device keeps while offline (spec §116–117): a key-value store (the workout in
 * progress, cached plans) and a queue of writes to send, in order, once the API is reachable.
 * IndexedDB when the browser has it; memory otherwise (private browsing may refuse it), which
 * keeps the app usable, only without surviving a reload.
 *
 * Every write resolves once its transaction is committed, not merely requested.
 */
export interface QueuedWrite<T> {
  /** Order of the queue. */
  seq: number
  write: T
  status: 'pending' | 'failed'
  /** Why it failed, for the user. */
  error: string | null
}

export interface OfflineStore {
  get<T>(key: string): Promise<T | undefined>
  set(key: string, value: unknown): Promise<void>
  remove(key: string): Promise<void>
  /** Stores a value and queues a write at once: both are kept, or neither (a killed app never keeps half). */
  commit<T>(key: string, value: unknown, write?: T): Promise<void>
  enqueue<T>(write: T): Promise<void>
  queue<T>(): Promise<Array<QueuedWrite<T>>>
  update<T>(item: QueuedWrite<T>): Promise<void>
  dequeue(seq: number): Promise<void>
  /** Everything, values and queue (another account signs in on this device). */
  clear(): Promise<void>
}

const DATABASE = 'bulkpilot-offline'
const VALUES = 'values'
const QUEUE = 'queue'

/** Stored values are plain data: reactive proxies and class instances are not cloneable. */
function plain<T>(value: T): T {
  return value === undefined ? value : JSON.parse(JSON.stringify(value)) as T
}

function pending<T>(write: T): Omit<QueuedWrite<T>, 'seq'> {
  return { write: plain(write), status: 'pending', error: null }
}

function openDatabase(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const open = indexedDB.open(DATABASE, 1)
    open.onupgradeneeded = () => {
      open.result.createObjectStore(VALUES)
      open.result.createObjectStore(QUEUE, { keyPath: 'seq', autoIncrement: true })
    }
    open.onsuccess = () => {
      // A newer version of the app in another tab needs the database: let it upgrade.
      open.result.onversionchange = () => open.result.close()
      resolve(open.result)
    }
    open.onerror = () => reject(open.error)
  })
}

function indexedStore(database: IDBDatabase): OfflineStore {
  /** Runs the work in one transaction; resolves with what it read, once committed. */
  function run<R = undefined>(stores: string[], mode: IDBTransactionMode, work: (transaction: IDBTransaction) => (() => R) | undefined): Promise<R> {
    return new Promise((resolve, reject) => {
      const transaction = database.transaction(stores, mode)
      const result = work(transaction)
      transaction.oncomplete = () => resolve((result ? result() : undefined) as R)
      transaction.onerror = () => reject(transaction.error)
      transaction.onabort = () => reject(transaction.error)
    })
  }
  const read = <R>(store: string, query: (objects: IDBObjectStore) => IDBRequest) => run<R>([store], 'readonly', (transaction) => {
    const request = query(transaction.objectStore(store))
    return () => request.result as R
  })

  return {
    get: key => read(VALUES, values => values.get(key)),
    set: (key, value) => run([VALUES], 'readwrite', (transaction) => {
      transaction.objectStore(VALUES).put(plain(value), key)
      return undefined
    }),
    remove: key => run([VALUES], 'readwrite', (transaction) => {
      transaction.objectStore(VALUES).delete(key)
      return undefined
    }),
    commit: (key, value, write) => run([VALUES, QUEUE], 'readwrite', (transaction) => {
      transaction.objectStore(VALUES).put(plain(value), key)
      if (write !== undefined) transaction.objectStore(QUEUE).add(pending(write))
      return undefined
    }),
    enqueue: write => run([QUEUE], 'readwrite', (transaction) => {
      transaction.objectStore(QUEUE).add(pending(write))
      return undefined
    }),
    queue: () => read(QUEUE, queue => queue.getAll()),
    // Only a write still queued: one sent meanwhile is not brought back. Chained in the success
    // callback, where the transaction is still active in every browser.
    update: item => run([QUEUE], 'readwrite', (transaction) => {
      const queue = transaction.objectStore(QUEUE)
      queue.get(item.seq).onsuccess = (event) => {
        if ((event.target as IDBRequest).result !== undefined) queue.put(plain(item))
      }
      return undefined
    }),
    dequeue: seq => run([QUEUE], 'readwrite', (transaction) => {
      transaction.objectStore(QUEUE).delete(seq)
      return undefined
    }),
    clear: () => run([VALUES, QUEUE], 'readwrite', (transaction) => {
      transaction.objectStore(VALUES).clear()
      transaction.objectStore(QUEUE).clear()
      return undefined
    }),
  }
}

export function memoryStore(): OfflineStore {
  const values = new Map<string, unknown>()
  const queue = new Map<number, QueuedWrite<unknown>>()
  let next = 1
  const enqueue = (write: unknown) => {
    const item = { seq: next++, ...pending(write) }
    queue.set(item.seq, item)
  }
  return {
    async get<T>(key: string) {
      return plain(values.get(key)) as T | undefined
    },
    async set(key, value) {
      values.set(key, plain(value))
    },
    async remove(key) {
      values.delete(key)
    },
    async commit(key, value, write) {
      values.set(key, plain(value))
      if (write !== undefined) enqueue(write)
    },
    async enqueue(write) {
      enqueue(write)
    },
    async queue<T>() {
      return plain([...queue.values()].sort((a, b) => a.seq - b.seq)) as Array<QueuedWrite<T>>
    },
    async update(item) {
      if (queue.has(item.seq)) queue.set(item.seq, plain(item) as QueuedWrite<unknown>)
    },
    async dequeue(seq) {
      queue.delete(seq)
    },
    async clear() {
      values.clear()
      queue.clear()
    },
  }
}

let opened: Promise<OfflineStore> | null = null

/** One store per page, opened on first use. */
export function offlineStore(): Promise<OfflineStore> {
  opened ??= (typeof indexedDB === 'undefined' ? Promise.reject(new Error('no IndexedDB')) : openDatabase().then(indexedStore))
    .catch(() => memoryStore())
  return opened
}

/** Tests only: the next call opens the store again. */
export function forgetOfflineStore(): void {
  opened = null
}
