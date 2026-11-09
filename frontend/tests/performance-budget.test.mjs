import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { resolve } from 'node:path'
import { gzipSync } from 'node:zlib'

const root=resolve(import.meta.dirname,'../../public')
const assets=resolve(root,'assets')
const manifest=JSON.parse(readFileSync(resolve(root,'offline-precache.json'),'utf8'))
const gzipped=name=>gzipSync(readFileSync(resolve(root,'.'+name)),{level:9}).byteLength

test('P4 baseline: critical entry JS and CSS stay inside release budgets',()=>{
  const entry=manifest.assets.filter(name=>name.endsWith('.js'))
  const css=manifest.assets.filter(name=>name.endsWith('.css'))
  assert.ok(entry.length>=1,'OnGround and admin entry bundle missing')
  assert.ok(css.length>=1,'Page stylesheet missing')
  const jsBytes=entry.reduce((sum,name)=>sum+gzipped(name),0)
  const cssBytes=css.reduce((sum,name)=>sum+gzipped(name),0)
  // Budgets allow roughly 30% growth from audited ~57KB JS + ~14KB CSS.
  assert.ok(jsBytes<=85*1024,`Initial JS ${jsBytes} bytes (gzip) exceeds 85 KiB budget`)
  assert.ok(cssBytes<=28*1024,`Initial CSS ${cssBytes} bytes (gzip) exceeds 28 KiB budget`)
  console.info('DigiSangam critical bytes (gzip): JS',jsBytes,'CSS',cssBytes)
})

test('P4 baseline: dynamic page chunks do not become monolithic',()=>{
  for(const name of readdirSync(assets)){
    if(!name.endsWith('.js')||name.startsWith('index-'))continue
    const path=resolve(assets,name)
    if(!statSync(path).isFile())continue
    const size=gzipSync(readFileSync(path),{level:9}).byteLength
    assert.ok(size<=40*1024,`Route chunk ${name} compressed to ${size} bytes, exceeds 40 KiB`)
  }
})
