<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useEventStore } from '../stores/event'
import { api } from '../services/api'
import { offlineStore } from '../services/offline'
import { useAuthStore } from '../stores/auth'

const events=useEventStore(),auth=useAuthStore()
const eventId=computed(()=>events.currentId)
const payload=ref(''),result=ref(null),busy=ref(false),mode=ref('verify'),error=ref(''),zoneId=ref('')
const online=ref(navigator.onLine&&!auth.offlineMode),snapshot=ref(null),queue=ref([]),showWalkin=ref(false),showBinding=ref(false),attendees=ref([]),tickets=ref([]),bindings=ref([]),schema=ref(null),cameraActive=ref(false),cameraSupported=ref(false),videoRef=ref(null),cameraError=ref('')
const walkin=ref({name:'',email:'',phone:'',company:'',category:'General',ticket_id:'',payment_settled:true,payment_reference:''})
const binding=ref({attendee_id:'',type:'rfid',uid:''})
const preparationPassphrase=ref(''),unlockPassphrase=ref(''),prepared=ref(false)
const allowed=computed(()=>!!result.value?.allowed)
const syncCount=computed(()=>queue.value.length)
const zones=computed(()=>snapshot.value?.venue?.zones||[])
let cameraStream=null,cameraFrame=0,cameraDetector=null

async function refreshQueue(){
  const all=await offlineStore.queued()
  queue.value=all.filter(item=>item.event_id===eventId.value)
}
async function downloadSnapshot(){
  if(!online.value||!eventId.value){error.value='Go online and select an event first.';return}
  if(preparationPassphrase.value.length<10){error.value='Set an offline passphrase with at least 10 characters.';return}
  busy.value=true;error.value=''
  try{
    const data=await api.ongroundSnapshot(eventId.value)
    snapshot.value=await offlineStore.saveSnapshot(data,preparationPassphrase.value)
    prepared.value=true
    await refreshQueue()
  }catch(e){error.value=e.message}
  finally{preparationPassphrase.value='';busy.value=false}
}
async function unlockOffline(){
  if(!eventId.value){error.value='Select an event while online first.';return}
  busy.value=true;error.value=''
  try{
    snapshot.value=await offlineStore.unlockSnapshot(eventId.value,unlockPassphrase.value)
    await refreshQueue()
  }catch(e){error.value=e.message}
  finally{unlockPassphrase.value='';busy.value=false}
}
async function syncQueue(){
  if(!navigator.onLine||!queue.value.length)return
  busy.value=true;error.value=''
  try{
    const response=await api.ongroundSync(queue.value)
    for(const item of response.results||[]){
      if(item.result?.allowed) await offlineStore.removeQueued(item.local_id)
    }
    await refreshQueue()
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
async function offlineCheckin(value){
  if(!snapshot.value) return {allowed:false,reason:'OFFLINE_SNAPSHOT_LOCKED'}
  const credential=await offlineStore.findCredential(eventId.value,value)
  if(!credential) return {allowed:false,reason:'NOT_IN_OFFLINE_SNAPSHOT'}
  const duplicate=queue.value.find(x=>x.attendee_id===credential.attendee_id)
  if(duplicate) return {allowed:true,reason:'ALREADY_QUEUED_OFFLINE',attendee:credential,already_checked_in:true}
  const zone=zones.value.find(x=>x.id===zoneId.value)
  if(zone&&zone.categories?.length&&!zone.categories.includes(credential.category)) return {allowed:false,reason:'ZONE_DENIED',attendee:credential,zone}
  const item={local_id:'off_'+Date.now()+'_'+Math.random().toString(16).slice(2),event_id:eventId.value,attendee_id:credential.attendee_id,payload:value,zone_id:zoneId.value,scanned_at:new Date().toISOString()}
  await offlineStore.queueCheckin(item)
  await refreshQueue()
  return {allowed:true,reason:'QUEUED_OFFLINE',attendee:credential,offline:true}
}
async function run(action=mode.value){
  error.value='';result.value=null
  const value=payload.value.trim()
  if(!value){error.value='Scan or paste a DigiSangam QR credential.';return}
  busy.value=true
  try{
    if(!online.value){
      if(!snapshot.value){error.value='Unlock a prepared offline snapshot before scanning.';return}
      if(action==='exit'){error.value='Exit scanning requires an online connection so venue occupancy stays authoritative.';return}
      result.value=action==='checkin'?await offlineCheckin(value):((await offlineStore.findCredential(eventId.value,value))?{allowed:true,reason:'VALID_OFFLINE_SNAPSHOT',attendee:await offlineStore.findCredential(eventId.value,value)}:{allowed:false,reason:'NOT_IN_OFFLINE_SNAPSHOT'})
    }else{
      if(action==='checkin') result.value=await api.scannerCheckin(value,zoneId.value,eventId.value)
      else if(action==='exit') result.value=await api.scannerExit(value,zoneId.value,eventId.value)
      else result.value=await api.scannerVerify(value,zoneId.value,eventId.value)
    }
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
function stopCamera(){
  cameraActive.value=false
  if(cameraFrame)cancelAnimationFrame(cameraFrame)
  cameraFrame=0
  if(cameraStream){for(const track of cameraStream.getTracks())track.stop()}
  cameraStream=null;cameraDetector=null
}
async function scanCameraFrame(){
  if(!cameraActive.value||!cameraDetector||!videoRef.value)return
  try{
    const codes=await cameraDetector.detect(videoRef.value)
    const value=codes?.[0]?.rawValue
    if(value){
      payload.value=value
      stopCamera()
      await run(mode.value)
      return
    }
  }catch(e){cameraError.value=e?.message||'Unable to read QR code.'}
  if(cameraActive.value)cameraFrame=requestAnimationFrame(scanCameraFrame)
}
async function startCamera(){
  cameraError.value='';error.value=''
  if(!cameraSupported.value){error.value='Camera QR scanning is not supported in this browser. Use a hardware scanner or paste the credential.';return}
  try{
    cameraActive.value=true
    await nextTick()
    cameraStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false})
    videoRef.value.srcObject=cameraStream
    await videoRef.value.play()
    cameraDetector=new window.BarcodeDetector({formats:['qr_code']})
    cameraFrame=requestAnimationFrame(scanCameraFrame)
  }catch(e){cameraError.value=e?.message||'Camera permission was denied.';stopCamera()}
}
function reset(){payload.value='';result.value=null;error.value='';cameraError.value=''}
async function hydrate(){
  snapshot.value=eventId.value?await offlineStore.snapshot(eventId.value):null
  prepared.value=eventId.value?await offlineStore.hasPreparedSnapshot(eventId.value):false
  await refreshQueue()
  if(eventId.value&&online.value){
    try{
      [attendees.value,tickets.value,bindings.value,schema.value]=await Promise.all([api.attendees(eventId.value),api.tickets(eventId.value),api.credentialBindings(eventId.value),api.registration(eventId.value)])
      walkin.value.category=schema.value?.categories?.[0]||'General'
    }catch(e){error.value=e.message}
  }
}
async function createWalkin(){
  busy.value=true;error.value=''
  try{
    const registration=await api.walkIn({...walkin.value,event_id:eventId.value})
    attendees.value.unshift(registration.attendee)
    showWalkin.value=false
    walkin.value={name:'',email:'',phone:'',company:'',category:schema.value?.categories?.[0]||'General',ticket_id:'',payment_settled:true,payment_reference:''}
    result.value={allowed:true,reason:'WALK_IN_REGISTERED',attendee:registration.attendee,credential:registration.credential}
  }catch(e){error.value=e.message}finally{busy.value=false}
}
async function createBinding(){
  busy.value=true;error.value=''
  try{
    const row=await api.bindCredential({...binding.value,event_id:eventId.value})
    bindings.value.unshift(row);showBinding.value=false;binding.value={attendee_id:'',type:'rfid',uid:''}
  }catch(e){error.value=e.message}finally{busy.value=false}
}
async function setOnline(){
  if(!navigator.onLine){online.value=false;return}
  if(auth.offlineMode){
    try{
      await auth.bootstrap()
      auth.offlineMode=false
      await events.load()
    }catch{online.value=false;return}
  }
  online.value=true
  await hydrate()
  if(snapshot.value) await syncQueue()
}
onMounted(async()=>{
  cameraSupported.value=!!(window.BarcodeDetector&&navigator.mediaDevices?.getUserMedia)
  if(!events.loaded&&online.value){try{await events.load()}catch(e){error.value=e.message}}
  await hydrate()
  window.addEventListener('online',setOnline)
  window.addEventListener('offline',setOnline)
})
watch(eventId,()=>{result.value=null;payload.value='';hydrate()})
onUnmounted(()=>{stopCamera();window.removeEventListener('online',setOnline);window.removeEventListener('offline',setOnline)})
</script>

<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">OnGround</p><h1 class="page-title">Offline-First Check-in</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · server-authoritative live checks with offline operational continuity.</p></div><div class="flex flex-wrap items-center gap-2"><span class="status-badge" :class="online?'badge-published':'badge-draft'">{{online?'Online':'Offline'}}</span><RouterLink to="/entry-monitor" class="btn-secondary">Live Entry Monitor</RouterLink><button class="btn-secondary" :disabled="busy||!online||!eventId" @click="showWalkin=true">+ Walk-in</button><button class="btn-secondary" :disabled="busy||!online||!eventId" @click="showBinding=true">Bind NFC/RFID</button><span v-if="prepared" class="status-badge badge-published">Prepared device</span><button class="btn-primary" :disabled="busy||!online||!syncCount" @click="syncQueue">Sync {{syncCount}} check-ins</button></div></section>

<section class="panel p-5 sm:p-6">
  <div class="flex flex-col gap-5 lg:flex-row lg:items-end">
    <div class="min-w-0 flex-1">
      <p class="panel-kicker">Secure offline access</p>
      <h2 class="mt-1 text-lg font-extrabold">{{snapshot?'Offline snapshot unlocked':prepared?'Offline snapshot prepared · unlock required':'Prepare this device while online'}}</h2>
      <p class="mt-2 max-w-2xl text-xs leading-5 text-slate-500">An authenticated operator prepares an event snapshot using a passphrase of at least 10 characters. Data and queued check-ins are encrypted on this device. Snapshots expire after 48 hours. Keep the passphrase safe; it is not recoverable.</p>
    </div>
    <form v-if="online" class="flex w-full flex-col gap-2 sm:flex-row lg:max-w-[440px]" @submit.prevent="downloadSnapshot">
      <label class="min-w-0 flex-1"><span class="sr-only">Set offline passphrase</span><input v-model="preparationPassphrase" type="password" autocomplete="new-password" minlength="10" required placeholder="Set offline passphrase (10+ characters)" class="control" /></label>
      <button type="submit" class="btn-primary whitespace-nowrap" :disabled="busy||!eventId">Prepare / Re-sync</button>
    </form>
  </div>
  <form v-if="prepared&&!snapshot" class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-end" @submit.prevent="unlockOffline">
    <label class="min-w-0 flex-1"><span class="mb-1 block text-xs font-bold text-slate-600">Unlock saved event snapshot</span><input v-model="unlockPassphrase" type="password" autocomplete="off" required placeholder="Offline passphrase" class="control" /></label>
    <button type="submit" class="btn-primary whitespace-nowrap" :disabled="busy">Unlock offline data</button>
  </form>
  <p v-if="!online&&!prepared" class="mt-4 text-xs font-semibold text-rose-700">No secure offline snapshot is available. Reconnect and prepare this device before entering offline mode.</p>
</section>
<section class="grid gap-4 sm:grid-cols-3"><article class="panel p-5"><p class="panel-kicker">Offline snapshot</p><p class="mt-2 text-lg font-black">{{snapshot?'Ready':prepared?'Locked':'Not synced'}}</p><p class="mt-1 text-xs text-slate-400">{{snapshot?.generated_at||'Re-sync before doors open'}}</p></article><article class="panel p-5"><p class="panel-kicker">Cached attendees</p><p class="mt-2 text-2xl font-black">{{snapshot?.credentials?.length||0}}</p></article><article class="panel p-5"><p class="panel-kicker">Pending sync</p><p class="mt-2 text-2xl font-black">{{syncCount}}</p></article></section>

<section class="grid gap-5 xl:grid-cols-[1fr_.75fr]">
  <article class="panel p-6"><div class="flex justify-between gap-3"><div><p class="panel-kicker">Credential input</p><h2 class="panel-title">Scan or paste QR payload</h2></div><div class="flex flex-wrap gap-2"><button class="btn-secondary py-1.5" :class="mode==='verify'?'ring-2 ring-indigo-100':''" @click="mode='verify'">Verify</button><button class="btn-secondary py-1.5" :class="mode==='checkin'?'ring-2 ring-indigo-100':''" @click="mode='checkin'">Entry</button><button class="btn-secondary py-1.5" :disabled="!online" :class="mode==='exit'?'ring-2 ring-indigo-100':''" @click="mode='exit'">Exit</button></div></div><label class="field mt-5"><span>Access zone</span><select v-model="zoneId"><option value="">General event entry</option><option v-for="z in zones" :key="z.id" :value="z.id">{{z.name}} · cap {{z.capacity}}</option></select></label><textarea v-model="payload" class="control mt-3 min-h-40 font-mono text-xs" autofocus placeholder="digisangam://credential/…"></textarea><p class="mt-3 text-xs text-slate-400">Offline mode uses the last server-issued snapshot cached in IndexedDB. Queued check-ins are revalidated by the server when connectivity returns.</p><div class="mt-5 flex flex-wrap gap-2"><button class="btn-primary" :disabled="busy||!eventId||(!online&&!snapshot)" @click="run()">{{busy?'Checking…':(mode==='checkin'?'Verify & Enter':mode==='exit'?'Verify & Exit':'Verify Credential')}}</button><button v-if="cameraSupported" class="btn-secondary" :disabled="busy||cameraActive" @click="startCamera">▣ Scan with camera</button><button class="btn-secondary" @click="reset">Clear</button></div><p v-if="!cameraSupported" class="mt-2 text-[11px] text-slate-400">Camera QR is unavailable in this browser; USB/Bluetooth scanners, NFC/RFID and pasted credentials remain supported.</p><p v-if="error" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{error}}</p></article>

  <article class="panel p-6"><p class="panel-kicker">Result</p><div v-if="!result" class="grid min-h-64 place-items-center text-center text-sm text-slate-400"><div><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-50 text-xl">▦</div><p class="mt-3">Waiting for a credential.</p></div></div><div v-else><div class="rounded-2xl p-5" :class="allowed?'bg-emerald-50':'bg-rose-50'"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-full font-black text-white" :class="allowed?'bg-emerald-600':'bg-rose-600'">{{allowed?'✓':'×'}}</div><div><p class="text-xs font-black uppercase tracking-widest" :class="allowed?'text-emerald-700':'text-rose-700'">{{allowed?(mode==='exit'?'Exit recorded':'Access allowed'):'Access denied'}}</p><h2 class="mt-1 text-lg font-black">{{result.reason}}</h2></div></div></div><div v-if="result.attendee" class="mt-5 space-y-3 rounded-2xl border border-slate-200 p-5 text-sm"><div class="flex justify-between gap-4"><span class="text-slate-500">Name</span><b>{{result.attendee.name}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Category</span><b>{{result.attendee.category}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Company</span><b>{{result.attendee.company||'—'}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Registration</span><b class="font-mono text-xs">{{result.attendee.id||result.attendee.attendee_id}}</b></div></div><button v-if="allowed&&mode==='verify'&&!result.already_checked_in&&online" class="btn-primary mt-4 w-full" @click="run('checkin')">Check in now</button></div></article>
</section>
<div v-if="cameraActive" class="modal-backdrop" @click.self="stopCamera"><div class="modal-card"><div class="flex items-center justify-between"><div><p class="panel-kicker">Camera scanner</p><h2 class="mt-1 text-lg font-bold">Point at the attendee QR</h2></div><button class="icon-btn" @click="stopCamera">×</button></div><div class="relative mt-5 overflow-hidden rounded-2xl bg-slate-950"><video ref="videoRef" playsinline muted class="aspect-[4/3] w-full object-cover"></video><div class="pointer-events-none absolute inset-[15%] rounded-2xl border-2 border-white/80 shadow-[0_0_0_999px_rgba(0,0,0,.3)]"></div></div><p v-if="cameraError" class="mt-3 rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700">{{cameraError}}</p><p class="mt-3 text-xs text-slate-500">Scanning stops automatically when a QR code is detected.</p></div></div>
<div v-if="showWalkin" class="modal-backdrop" @click.self="showWalkin=false"><form class="modal-card max-h-[90vh] overflow-y-auto" @submit.prevent="createWalkin"><h2 class="text-lg font-bold">Walk-in registration</h2><div class="mt-5 grid gap-4"><label class="field"><span>Name</span><input v-model="walkin.name" required/></label><div class="grid grid-cols-2 gap-3"><label class="field"><span>Email *</span><input v-model="walkin.email" type="email" required/></label><label class="field"><span>Phone</span><input v-model="walkin.phone"/></label></div><label class="field"><span>Company</span><input v-model="walkin.company"/></label><div class="grid grid-cols-2 gap-3"><label class="field"><span>Category</span><select v-model="walkin.category"><option v-for="x in schema?.categories||['General']" :key="x">{{x}}</option></select></label><label class="field"><span>Ticket</span><select v-model="walkin.ticket_id"><option value="">No ticket</option><option v-for="t in tickets" :key="t.id" :value="t.id">{{t.name}} · {{t.price===0?'Free':t.price}}</option></select></label></div><label class="flex items-center justify-between text-sm font-bold">Payment settled onsite <input v-model="walkin.payment_settled" type="checkbox"/></label><label class="field"><span>Payment reference</span><input v-model="walkin.payment_reference" placeholder="Cash/UPI/reference"/></label></div><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="showWalkin=false">Cancel</button><button class="btn-primary" :disabled="busy">Register & issue credential</button></div></form></div>
<div v-if="showBinding" class="modal-backdrop" @click.self="showBinding=false"><form class="modal-card" @submit.prevent="createBinding"><h2 class="text-lg font-bold">Bind NFC / RFID credential</h2><div class="mt-5 grid gap-4"><label class="field"><span>Attendee</span><select v-model="binding.attendee_id" required><option value="">Select confirmed attendee</option><option v-for="a in attendees.filter(x=>x.status==='Confirmed')" :key="a.id" :value="a.id">{{a.name}} · {{a.category}}</option></select></label><label class="field"><span>Type</span><select v-model="binding.type"><option value="rfid">RFID</option><option value="nfc">NFC</option></select></label><label class="field"><span>UID</span><input v-model="binding.uid" required placeholder="Scan or enter card UID"/></label></div><p class="mt-4 text-xs text-slate-500">After binding, the scanner accepts <code>{{binding.type}}:UID</code> as a credential input.</p><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="showBinding=false">Cancel</button><button class="btn-primary" :disabled="busy">Bind credential</button></div></form></div>
</div></template>
