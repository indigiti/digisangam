/* Offline integrity and at-rest protection. No secret or raw attendee snapshot is persisted. */
const encoder = new TextEncoder()
const decoder = new TextDecoder()
const KDF_ITERATIONS = 310000
const MAX_VALIDITY_MS = 48 * 60 * 60 * 1000

export function toBase64(bytes) {
  const value = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes)
  let result = ''
  for (let i = 0; i < value.length; i += 8192) {
    result += String.fromCharCode(...value.subarray(i, i + 8192))
  }
  return btoa(result)
}

export function fromBase64(value) {
  if (typeof value !== 'string' || !/^[A-Za-z0-9+/]*={0,2}$/.test(value) || value.length % 4 !== 0) {
    throw new Error('Malformed signed snapshot encoding.')
  }
  const raw = atob(value)
  return Uint8Array.from(raw, x => x.charCodeAt(0))
}

function requireCrypto() {
  if (!globalThis.crypto?.subtle || typeof globalThis.crypto.getRandomValues !== 'function') {
    throw new Error('Secure browser cryptography is required for offline mode.')
  }
  return globalThis.crypto
}

/** The signed bytes, not a mutable copy of JSON fields, are the authority. */
export async function verifySignedSnapshot(document, expectedEventId = '') {
  const crypto = requireCrypto()
  if (!document || document.signature_algorithm !== 'Ed25519' || !document.signed_payload || !document.signature) {
    throw new Error('An updated, digitally signed offline snapshot is required. Re-sync online.')
  }
  const signedBytes = fromBase64(document.signed_payload)
  let payload
  try { payload = JSON.parse(decoder.decode(signedBytes)) }
  catch { throw new Error('Offline snapshot content is invalid.') }
  if (payload?.version !== 3 || typeof payload.event_id !== 'string' || !Array.isArray(payload.credentials) || !payload.signing_public_key) {
    throw new Error('Offline snapshot schema is unsupported.')
  }
  if (expectedEventId && payload.event_id !== expectedEventId) throw new Error('Offline snapshot event mismatch.')
  if (document.event_id !== payload.event_id || document.signing_public_key !== payload.signing_public_key) {
    throw new Error('Offline snapshot metadata mismatch.')
  }
  const issued = Date.parse(payload.generated_at || '')
  const expires = Date.parse(payload.expires_at || '')
  const now = Date.now()
  if (!Number.isFinite(issued) || !Number.isFinite(expires) || issued > now + 5 * 60 * 1000 ||
      expires <= now || expires <= issued || expires - issued > MAX_VALIDITY_MS + 1000) {
    throw new Error('Offline snapshot has expired or has invalid validity dates. Re-sync online.')
  }
  const publicKeyBytes = fromBase64(payload.signing_public_key)
  if (publicKeyBytes.length !== 32) throw new Error('Offline signing key is invalid.')
  let verificationKey
  try {
    verificationKey = await crypto.subtle.importKey('raw', publicKeyBytes, { name: 'Ed25519' }, false, ['verify'])
  } catch {
    throw new Error('This browser does not support Ed25519 offline signature verification.')
  }
  const signatureBytes = fromBase64(document.signature)
  if (signatureBytes.length !== 64 ||
      !await crypto.subtle.verify({ name: 'Ed25519' }, verificationKey, signatureBytes, signedBytes)) {
    throw new Error('Offline snapshot signature is invalid.')
  }
  return payload
}

async function deriveKey(passphrase, salt) {
  const crypto = requireCrypto()
  if (typeof passphrase !== 'string' || !passphrase.length) throw new Error('Enter your offline passphrase.')
  const material = await crypto.subtle.importKey('raw', encoder.encode(passphrase), 'PBKDF2', false, ['deriveKey'])
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt, iterations: KDF_ITERATIONS, hash: 'SHA-256' },
    material,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt']
  )
}

export async function encryptSnapshot(document, passphrase) {
  const parsed = await verifySignedSnapshot(document)
  if (typeof passphrase !== 'string' || passphrase.length < 10) {
    throw new Error('Use an offline passphrase of at least 10 characters.')
  }
  const crypto = requireCrypto()
  const salt = crypto.getRandomValues(new Uint8Array(16))
  const iv = crypto.getRandomValues(new Uint8Array(12))
  const key = await deriveKey(passphrase, salt)
  const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, encoder.encode(JSON.stringify(document)))
  return {
    record: {
      format: 3, event_id: parsed.event_id, expires_at: parsed.expires_at,
      saved_at: new Date().toISOString(),
      salt: toBase64(salt), iv: toBase64(iv), ciphertext: toBase64(ciphertext),
    },
    payload: parsed, key,
  }
}

export async function decryptSnapshot(record, passphrase) {
  if (!record || record.format !== 3) throw new Error('Offline data uses an older format. Re-sync online.')
  const crypto = requireCrypto()
  const key = await deriveKey(passphrase, fromBase64(record.salt))
  let document
  try {
    const plainBytes = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: fromBase64(record.iv) },
      key,
      fromBase64(record.ciphertext)
    )
    document = JSON.parse(decoder.decode(plainBytes))
  } catch {
    throw new Error('Offline passphrase is incorrect or cached data has been modified.')
  }
  const payload = await verifySignedSnapshot(document, record.event_id)
  return { payload, key }
}

export async function encryptQueueItem(item, key) {
  if (!key) throw new Error('Unlock the offline snapshot before recording a check-in.')
  const iv = requireCrypto().getRandomValues(new Uint8Array(12))
  const content = await requireCrypto().subtle.encrypt({ name: 'AES-GCM', iv }, key, encoder.encode(JSON.stringify(item)))
  return { format: 3, event_id: item.event_id, local_id: item.local_id, iv: toBase64(iv), ciphertext: toBase64(content) }
}

export async function decryptQueueItem(record, key) {
  if (!key) throw new Error('Unlock the offline snapshot before viewing the check-in queue.')
  if (record.format !== 3) throw new Error('Legacy queue item must be reconciled through a verified migration.')
  const plain = await requireCrypto().subtle.decrypt(
    { name: 'AES-GCM', iv: fromBase64(record.iv) },
    key,
    fromBase64(record.ciphertext)
  )
  const item = JSON.parse(decoder.decode(plain))
  if (item.event_id !== record.event_id || item.local_id !== record.local_id) {
    throw new Error('Offline check-in queue integrity check failed.')
  }
  return item
}
