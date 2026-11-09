import {
  encryptSnapshot, decryptSnapshot, encryptQueueItem, decryptQueueItem,
} from './offlineSecurity'

const DB_NAME = 'digisangam-onground'
const DB_VERSION = 2
const STORE_SNAPSHOTS = 'snapshots'
const STORE_QUEUE = 'checkin_queue'
const unlocked = new Map()
const sessionKeys = new Map()

function openDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)
    request.onupgradeneeded = () => {
      const db = request.result
      if (!db.objectStoreNames.contains(STORE_SNAPSHOTS)) db.createObjectStore(STORE_SNAPSHOTS, { keyPath: 'event_id' })
      if (!db.objectStoreNames.contains(STORE_QUEUE)) db.createObjectStore(STORE_QUEUE, { keyPath: 'local_id' })
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

async function put(store, value) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite')
    tx.objectStore(store).put(value)
    tx.oncomplete = () => { db.close(); resolve(value) }
    tx.onerror = () => { db.close(); reject(tx.error) }
    tx.onabort = () => { db.close(); reject(tx.error || new Error('Offline storage write aborted.')) }
  })
}

async function get(store, key) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readonly')
    const request = tx.objectStore(store).get(key)
    request.onsuccess = () => { db.close(); resolve(request.result || null) }
    request.onerror = () => { db.close(); reject(request.error) }
  })
}

async function all(store) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readonly')
    const request = tx.objectStore(store).getAll()
    request.onsuccess = () => { db.close(); resolve(request.result || []) }
    request.onerror = () => { db.close(); reject(request.error) }
  })
}

async function remove(store, key) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite')
    tx.objectStore(store).delete(key)
    tx.oncomplete = () => { db.close(); resolve() }
    tx.onerror = () => { db.close(); reject(tx.error) }
  })
}

async function clear(store) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite')
    tx.objectStore(store).clear()
    tx.oncomplete = () => { db.close(); resolve() }
    tx.onerror = () => { db.close(); reject(tx.error) }
  })
}

function isActive(payload) {
  return payload?.version === 3 && Date.parse(payload.expires_at || '') > Date.now()
}

async function migrateQueue(eventId, key) {
  const rows = await all(STORE_QUEUE)
  for (const row of rows) {
    if (row.event_id !== eventId || row.format === 3) continue
    // Legacy v2 entries stay recoverable, but are re-encrypted before reuse.
    if (typeof row.local_id !== 'string' || typeof row.payload !== 'string' ||
        typeof row.attendee_id !== 'string') continue
    await put(STORE_QUEUE, await encryptQueueItem(row, key))
  }
}

export const offlineStore = {
  async saveSnapshot(document, passphrase) {
    const { record, payload, key } = await encryptSnapshot(document, passphrase)
    await put(STORE_SNAPSHOTS, record)
    unlocked.set(payload.event_id, payload)
    sessionKeys.set(payload.event_id, key)
    await migrateQueue(payload.event_id, key)
    return payload
  },

  async hasPreparedSnapshot(eventId) {
    if (!eventId) return false
    const row = await get(STORE_SNAPSHOTS, eventId)
    return row?.format === 3 && Date.parse(row.expires_at || '') > Date.now()
  },

  async unlockSnapshot(eventId, passphrase) {
    const row = await get(STORE_SNAPSHOTS, eventId)
    if (!row) throw new Error('No offline snapshot is prepared on this device.')
    const { payload, key } = await decryptSnapshot(row, passphrase)
    unlocked.set(eventId, payload)
    sessionKeys.set(eventId, key)
    await migrateQueue(eventId, key)
    return payload
  },

  async snapshot(eventId) {
    const payload = unlocked.get(eventId) || null
    if (!isActive(payload)) {
      unlocked.delete(eventId)
      sessionKeys.delete(eventId)
      return null
    }
    return payload
  },

  isUnlocked(eventId) {
    return !!unlocked.get(eventId) && isActive(unlocked.get(eventId))
  },

  async queued() {
    const records = await all(STORE_QUEUE)
    const results = []
    for (const row of records) {
      const key = sessionKeys.get(row.event_id)
      if (!key || !isActive(unlocked.get(row.event_id))) continue
      if (row.format !== 3) {
        // Only legacy rows with the required fields can be recovered.
        if (typeof row.payload === 'string' && typeof row.attendee_id === 'string') results.push(row)
        continue
      }
      results.push(await decryptQueueItem(row, key))
    }
    return results
  },

  async queueCheckin(item) {
    if (!this.isUnlocked(item.event_id)) throw new Error('Unlock your offline snapshot first.')
    const key = sessionKeys.get(item.event_id)
    return put(STORE_QUEUE, await encryptQueueItem(item, key))
  },

  removeQueued: id => remove(STORE_QUEUE, id),

  async findCredential(eventId, payload) {
    const snapshot = await this.snapshot(eventId)
    if (!snapshot) return null
    return snapshot.credentials.find(x => x.payload === payload) || null
  },

  async clearAll() {
    unlocked.clear()
    sessionKeys.clear()
    await clear(STORE_SNAPSHOTS)
    await clear(STORE_QUEUE)
  },
}
