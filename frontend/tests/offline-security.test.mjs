import test from 'node:test'
import assert from 'node:assert/strict'
import { webcrypto } from 'node:crypto'
import {
  toBase64, verifySignedSnapshot, encryptSnapshot, decryptSnapshot,
  encryptQueueItem, decryptQueueItem,
} from '../src/services/offlineSecurity.js'

globalThis.crypto ||= webcrypto
const encoder = new TextEncoder()

async function makeDocument({ expiresInHours = 24 } = {}) {
  const keys = await crypto.subtle.generateKey({ name: 'Ed25519' }, true, ['sign', 'verify'])
  const publicKey = toBase64(new Uint8Array(await crypto.subtle.exportKey('raw', keys.publicKey)))
  const payload = {
    version: 3, event_id: 'evt_p0', generated_at: new Date().toISOString(),
    expires_at: new Date(Date.now() + expiresInHours * 3600000).toISOString(),
    credentials: [{ attendee_id: 'att_1', name: 'Example attendee', payload: 'digisangam://credential/test' }],
    signing_public_key: publicKey,
  }
  const signedBytes = encoder.encode(JSON.stringify(payload))
  const signature = await crypto.subtle.sign('Ed25519', keys.privateKey, signedBytes)
  return { ...payload, signature_algorithm: 'Ed25519', signed_payload: toBase64(signedBytes), signature: toBase64(signature) }
}

test('valid Ed25519 snapshot is accepted using signed payload only', async () => {
  const doc = await makeDocument()
  doc.credentials = [{ attendee_id: 'evil', name: 'Forged' }]
  const result = await verifySignedSnapshot(doc, 'evt_p0')
  assert.equal(result.credentials[0].name, 'Example attendee')
})
test('mismatched event and modified signed data are rejected', async () => {
  const doc = await makeDocument()
  await assert.rejects(verifySignedSnapshot(doc, 'evt_other'), /mismatch/)
  const forged = { ...doc, signed_payload: toBase64(encoder.encode('{"version":3}')) }
  await assert.rejects(verifySignedSnapshot(forged), /mismatch|invalid|signature|unsupported/i)
})
test('expired snapshots are not accepted', async () => {
  const doc = await makeDocument({ expiresInHours: -1 })
  await assert.rejects(verifySignedSnapshot(doc), /expired/)
})
test('encrypted snapshots require the correct passphrase and hide attendee details', async () => {
  const doc = await makeDocument()
  const { record } = await encryptSnapshot(doc, 'safe-passphrase-2026')
  assert.equal(record.format, 3)
  assert.equal(JSON.stringify(record).includes('Example attendee'), false)
  await assert.rejects(decryptSnapshot(record, 'wrong-password'), /incorrect|modified/)
  const result = await decryptSnapshot(record, 'safe-passphrase-2026')
  assert.equal(result.payload.credentials[0].attendee_id, 'att_1')
})
test('offline queue items are encrypted, scoped and authenticated', async () => {
  const doc = await makeDocument()
  const { key } = await encryptSnapshot(doc, 'safe-passphrase-2026')
  const item = { local_id: 'off_1', event_id: 'evt_p0', attendee_id: 'att_1', payload: 'secret-credential' }
  const stored = await encryptQueueItem(item, key)
  assert.equal(JSON.stringify(stored).includes('secret-credential'), false)
  assert.deepEqual(await decryptQueueItem(stored, key), item)
  await assert.rejects(decryptQueueItem({ ...stored, event_id: 'evt_other' }, key))
})
test('weak passphrase is rejected before data persistence', async () => {
  const doc = await makeDocument()
  await assert.rejects(encryptSnapshot(doc, '1234'), /at least 10/)
})
