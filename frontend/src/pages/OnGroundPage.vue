<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { api } from '../services/api'
import { offlineStore } from '../services/offline'

const eventId='evt_001'
const payload=ref(''),result=ref(null),busy=ref(false),mode=ref('verify'),error=ref(''),zoneId=ref('')
const online=ref(navigator.onLine),snapshot=ref(null),queue=ref([])
const allowed=computed(()=>!!result.value?.allowed)
const syncCount=computed(()=>queue.value.length)
const zones=computed(()=>snapshot.value?.venue?.zones||[])

async function refreshQueue(){queue.value=await offlineStore.queued()}
async function downloadSnapshot(){
  busy.value=true;error.value=''
  try{
    const data=await api.ongroundSnapshot(eventId)
    await offlineStore.saveSnapshot(data)
    snapshot.value=data
  }catch(e){error.value=e.message}
  finally{busy.value=false}
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
  const credential=await offlineStore.findCredential(eventId,value)
  if(!credential) return {allowed:false,reason:'NOT_IN_OFFLINE_SNAPSHOT'}
  const duplicate=queue.value.find(x=>x.attendee_id===credential.attendee_id)
  if(duplicate) return {allowed:true,reason:'ALREADY_QUEUED_OFFLINE',attendee:credential,already_checked_in:true}
  const zone=zones.value.find(x=>x.id===zoneId.value)
  if(zone&&zone.categories?.length&&!zone.categories.includes(credential.category)) return {allowed:false,reason:'ZONE_DENIED',attendee:credential,zone}
  const item={local_id:'off_'+Date.now()+'_'+Math.random().toString(16).slice(2),event_id:eventId,attendee_id:credential.attendee_id,payload:value,zone_id:zoneId.value,scanned_at:new Date().toISOString()}
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
    if(!navigator.onLine){
      result.value=action==='checkin'?await offlineCheckin(value):((await offlineStore.findCredential(eventId,value))?{allowed:true,reason:'VALID_OFFLINE_SNAPSHOT',attendee:await offlineStore.findCredential(eventId,value)}:{allowed:false,reason:'NOT_IN_OFFLINE_SNAPSHOT'})
    }else{
      result.value=action==='checkin'?await api.scannerCheckin(value,zoneId.value):await api.scannerVerify(value,zoneId.value)
    }
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
function reset(){payload.value='';result.value=null;error.value=''}
async function hydrate(){snapshot.value=await offlineStore.snapshot(eventId);await refreshQueue()}
function setOnline(){online.value=navigator.onLine;if(online.value)syncQueue()}
onMounted(()=>{hydrate();window.addEventListener('online',setOnline);window.addEventListener('offline',setOnline)})
onUnmounted(()=>{window.removeEventListener('online',setOnline);window.removeEventListener('offline',setOnline)})
</script>

<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">OnGround</p><h1 class="page-title">Offline-First Check-in</h1><p class="page-subtitle">Online verification when connected; signed event snapshot and queued reconciliation when offline.</p></div><div class="flex flex-wrap items-center gap-2"><span class="status-badge" :class="online?'badge-published':'badge-draft'">{{online?'Online':'Offline'}}</span><button class="btn-secondary" :disabled="busy||!online" @click="downloadSnapshot">Sync event data</button><button class="btn-primary" :disabled="busy||!online||!syncCount" @click="syncQueue">Sync {{syncCount}} check-ins</button></div></section>

<section class="grid gap-4 sm:grid-cols-3"><article class="panel p-5"><p class="panel-kicker">Offline snapshot</p><p class="mt-2 text-lg font-black">{{snapshot?'Ready':'Not synced'}}</p><p class="mt-1 text-xs text-slate-400">{{snapshot?.generated_at||'Download before doors open'}}</p></article><article class="panel p-5"><p class="panel-kicker">Cached attendees</p><p class="mt-2 text-2xl font-black">{{snapshot?.credentials?.length||0}}</p></article><article class="panel p-5"><p class="panel-kicker">Pending sync</p><p class="mt-2 text-2xl font-black">{{syncCount}}</p></article></section>

<section class="grid gap-5 xl:grid-cols-[1fr_.75fr]">
  <article class="panel p-6"><div class="flex justify-between gap-3"><div><p class="panel-kicker">Credential input</p><h2 class="panel-title">Scan or paste QR payload</h2></div><div class="flex gap-2"><button class="btn-secondary py-1.5" :class="mode==='verify'?'ring-2 ring-indigo-100':''" @click="mode='verify'">Verify</button><button class="btn-secondary py-1.5" :class="mode==='checkin'?'ring-2 ring-indigo-100':''" @click="mode='checkin'">Check-in</button></div></div><label class="field mt-5"><span>Access zone</span><select v-model="zoneId"><option value="">General event entry</option><option v-for="z in zones" :key="z.id" :value="z.id">{{z.name}} · cap {{z.capacity}}</option></select></label><textarea v-model="payload" class="control mt-3 min-h-40 font-mono text-xs" autofocus placeholder="digisangam://credential/…"></textarea><p class="mt-3 text-xs text-slate-400">Offline mode uses an authenticated snapshot cached in IndexedDB and queues accepted check-ins for reconciliation.</p><div class="mt-5 flex gap-2"><button class="btn-primary" :disabled="busy" @click="run()">{{busy?'Checking…':(mode==='checkin'?'Verify & Check-in':'Verify Credential')}}</button><button class="btn-secondary" @click="reset">Clear</button></div><p v-if="error" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{error}}</p></article>

  <article class="panel p-6"><p class="panel-kicker">Result</p><div v-if="!result" class="grid min-h-64 place-items-center text-center text-sm text-slate-400"><div><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-50 text-xl">▦</div><p class="mt-3">Waiting for a credential.</p></div></div><div v-else><div class="rounded-2xl p-5" :class="allowed?'bg-emerald-50':'bg-rose-50'"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-full font-black text-white" :class="allowed?'bg-emerald-600':'bg-rose-600'">{{allowed?'✓':'×'}}</div><div><p class="text-xs font-black uppercase tracking-widest" :class="allowed?'text-emerald-700':'text-rose-700'">{{allowed?'Access allowed':'Access denied'}}</p><h2 class="mt-1 text-lg font-black">{{result.reason}}</h2></div></div></div><div v-if="result.attendee" class="mt-5 space-y-3 rounded-2xl border border-slate-200 p-5 text-sm"><div class="flex justify-between gap-4"><span class="text-slate-500">Name</span><b>{{result.attendee.name}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Category</span><b>{{result.attendee.category}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Company</span><b>{{result.attendee.company||'—'}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Registration</span><b class="font-mono text-xs">{{result.attendee.id||result.attendee.attendee_id}}</b></div></div><button v-if="allowed&&mode==='verify'&&!result.already_checked_in&&online" class="btn-primary mt-4 w-full" @click="run('checkin')">Check in now</button></div></article>
</section>
</div></template>
