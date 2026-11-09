import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const publicRoot = resolve(import.meta.dirname, '../../public')

test('Vite emits an installable OnGround offline precache manifest', () => {
  const filename = resolve(publicRoot, 'offline-precache.json')
  assert.equal(existsSync(filename), true, 'frontend build must emit the offline manifest')
  const manifest = JSON.parse(readFileSync(filename, 'utf8'))
  assert.equal(manifest.version, 1)
  assert.ok(Array.isArray(manifest.assets) && manifest.assets.length > 0)
  assert.ok(manifest.assets.some(name => name.endsWith('.js')))
  assert.ok(manifest.assets.some(name => name.endsWith('.css')))
  for (const name of manifest.assets) {
    assert.match(name, /^\/assets\/[A-Za-z0-9/._-]+$/)
    assert.equal(existsSync(resolve(publicRoot, '.' + name)), true, 'missing asset: ' + name)
  }
})
