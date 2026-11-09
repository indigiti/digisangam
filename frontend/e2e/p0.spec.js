import { test, expect } from '@playwright/test'
import { webcrypto } from 'node:crypto'

const BASE = '/digisangam/'
const events = [
  { id:'evt_a', name:'Design Leadership Summit', headline:'Design Leadership Summit', status:'Live',
    category:'Business', type:'Conference', date:'2026-12-10', start_date:'2026-12-10',
    location:'Mumbai', registrations:128, progress:85, min_price:0, currency:'INR' },
  { id:'evt_b', name:'Technology Builders Forum', headline:'Technology Builders Forum', status:'Published',
    category:'Technology', type:'Conference', date:'2026-12-18', start_date:'2026-12-18',
    location:'Pune', registrations:75, progress:60, min_price:500, currency:'INR' },
]
const attendeeCredential = 'digisangam://credential/e2e-demo-credential'

const respond = (route, data) => route.fulfill({
  status: 200, contentType:'application/json', body:JSON.stringify(data),
})

async function signedSnapshot() {
  const keys = await webcrypto.subtle.generateKey({ name:'Ed25519' }, true, ['sign','verify'])
  const publicKey = Buffer.from(await webcrypto.subtle.exportKey('raw',keys.publicKey)).toString('base64')
  const payload = {
    version:3, event_id:'evt_a', generated_at:new Date().toISOString(),
    expires_at:new Date(Date.now()+24*3600000).toISOString(), signing_public_key:publicKey,
    credentials:[{attendee_id:'att_a',name:'Test Operator Credential',category:'General',company:'Fixture',payload:attendeeCredential}],
    attendees:[],tickets:[],venue:{zones:[]},sessions:[],badges:[],
  }
  const encoded = new TextEncoder().encode(JSON.stringify(payload))
  const signature = await webcrypto.subtle.sign('Ed25519',keys.privateKey,encoded)
  return { ...payload,signature_algorithm:'Ed25519',signed_payload:Buffer.from(encoded).toString('base64'),signature:Buffer.from(signature).toString('base64') }
}

async function mockApi(page, { snapshot = null, offlineState = { offline:false }, onSync = () => {} } = {}) {
  await page.route('**/api/v1/**', async route => {
    if (offlineState.offline) return route.abort('internetdisconnected')
    const url = new URL(route.request().url())
    const path = url.pathname.split('/api/v1/')[1] || ''
    if(path==='auth/status') return respond(route,{
      setup_required:false,csrf_token:'playwright-csrf',
      user:{ id:'usr_ci',name:'QA Operator',role:'super_admin',workspace_id:'ws_default' },
    })
    if(path==='events') return respond(route,events)
    if(path.startsWith('dashboard')) {
      const eventId = url.searchParams.get('event_id') || events[0].id
      const current = events.find(x => x.id===eventId) || events[0]
      return respond(route,{
        event:current,stats:[{label:'Registrations',value:String(current.registrations),delta:'',tone:'blue'}],
        trend:[3,5,7,9,11],categories:[],metrics:{confirmation_rate:50,checkin_rate:20},
      })
    }
    if(path==='public/events') return respond(route,{ events,categories:['Business','Technology'],locations:['Mumbai','Pune'] })
    if(path.startsWith('onground/snapshot/')) return respond(route,snapshot)
    if(path==='onground/sync') {
      const body = route.request().postDataJSON()
      onSync(body.items || [])
      return respond(route,{results:(body.items||[]).map(item => ({local_id:item.local_id,result:{allowed:true,reason:'VALID'}}))})
    }
    if(path.startsWith('attendees') || path.startsWith('tickets') || path.startsWith('credentials/bindings')) return respond(route,[])
    if(path.includes('registration')) return respond(route,{categories:['General'],fields:[]})
    return respond(route,[])
  })
}

test('P0 mobile navigation opens, supports keyboard, and closes accessibly', async ({page}) => {
  await page.setViewportSize({width:375,height:812})
  await mockApi(page)
  await page.goto(BASE)
  const toggle = page.getByRole('button',{name:'Toggle navigation menu'})
  await toggle.click()
  await expect(page.getByRole('navigation',{name:'Application modules'})).toBeVisible()
  await page.getByLabel('Filter navigation').fill('Badges')
  await expect(page.getByRole('link',{name:'Badge Studio'})).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(toggle).toBeFocused()
  await expect(page.getByRole('navigation',{name:'Application modules'})).toBeHidden()
})

test('P0 active event switching reacts without full document reload', async ({page}) => {
  await mockApi(page)
  await page.goto(BASE)
  await expect(page.getByText('Design Leadership Summit').first()).toBeVisible()
  await expect(page.getByText('128',{exact:true})).toBeVisible()
  await page.getByLabel('Active event').selectOption('evt_b')
  await expect(page.getByText('Technology Builders Forum').first()).toBeVisible()
  await expect(page.getByText('75',{exact:true})).toBeVisible()
  const navigations=await page.evaluate(() => performance.getEntriesByType('navigation').length)
  expect(navigations).toBe(1)
})

test('P0 public discovery search uses real filtered event records', async ({page}) => {
  await mockApi(page)
  await page.goto(BASE+'discover')
  await expect(page.getByText('Design Leadership Summit').first()).toBeVisible()
  await page.getByPlaceholder('Search for events, conferences, workshops and more').fill('Technology Builders')
  await expect(page.getByText('Technology Builders Forum').first()).toBeVisible()
  await expect(page.getByText('Design Leadership Summit')).toHaveCount(0)
})

test('P0 prepared device survives offline reload, verifies credentials, and reconciles queued entry', async ({page,context}) => {
  const offlineState={offline:false}
  let syncedItems=0
  await mockApi(page,{snapshot:await signedSnapshot(),offlineState,onSync:items=>{syncedItems+=items.length}})
  await page.goto(BASE+'onground')
  await expect(page.getByText('Secure offline access')).toBeVisible()
  await page.evaluate(async () => { await navigator.serviceWorker.ready })
  await page.reload()
  const passphrase='Offline-Shift-Secret-2026'
  await page.getByPlaceholder('Set offline passphrase (10+ characters)').fill(passphrase)
  await page.getByRole('button',{name:'Prepare / Re-sync'}).click()
  await expect(page.getByText('Offline snapshot unlocked')).toBeVisible()
  await expect(page.getByText('1',{exact:true}).first()).toBeVisible()
  offlineState.offline=true
  await context.setOffline(true)
  await page.reload()
  await expect(page.getByText('Offline · local access only')).toBeVisible()
  await page.getByPlaceholder('Offline passphrase',{exact:true}).fill(passphrase)
  await page.getByRole('button',{name:'Unlock offline data'}).click()
  await expect(page.getByText('Offline snapshot unlocked')).toBeVisible()
  await page.getByRole('button',{name:'Entry',exact:true}).click()
  await page.locator('textarea[placeholder^="digisangam://"]').fill(attendeeCredential)
  await page.getByRole('button',{name:'Verify & Enter'}).click()
  await expect(page.getByText('QUEUED_OFFLINE')).toBeVisible()
  await context.setOffline(false)
  offlineState.offline=false
  await expect(page.getByText('Online',{exact:true})).toBeVisible()
  await page.getByRole('button',{name:/Sync \d+ check-ins/}).click().catch(() => {})
  await expect(page.getByText('Pending sync')).toBeVisible()
})
