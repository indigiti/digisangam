<script setup>
import { computed, ref } from 'vue'
import { api } from '../services/api'

const payload=ref(''),result=ref(null),busy=ref(false),mode=ref('verify'),error=ref('')
const allowed=computed(()=>!!result.value?.allowed)

async function run(action=mode.value){
  error.value='';result.value=null
  if(!payload.value.trim()){error.value='Scan or paste a DigiSangam QR credential.';return}
  busy.value=true
  try{
    result.value=action==='checkin'
      ? await api.scannerCheckin(payload.value.trim())
      : await api.scannerVerify(payload.value.trim())
  }catch(e){
    error.value=e.message
  }finally{busy.value=false}
}
function reset(){payload.value='';result.value=null;error.value=''}
</script>

<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">OnGround</p><h1 class="page-title">QR Scanner & Check-in</h1><p class="page-subtitle">Verify signed credentials, payment/approval state and prevent duplicate check-ins.</p></div><div class="flex gap-2"><button class="btn-secondary" :class="mode==='verify'?'ring-2 ring-indigo-100':''" @click="mode='verify'">Verify only</button><button class="btn-primary" @click="mode='checkin'">Verify + Check-in</button></div></section>

<section class="grid gap-5 xl:grid-cols-[1fr_.75fr]">
  <article class="panel p-6">
    <p class="panel-kicker">Credential input</p><h2 class="panel-title">Scan or paste QR payload</h2>
    <textarea v-model="payload" class="control mt-5 min-h-40 font-mono text-xs" autofocus placeholder="digisangam://credential/…"></textarea>
    <p class="mt-3 text-xs text-slate-400">USB/Bluetooth QR scanners can type directly into this field. Camera scanning can be layered on in Phase 2 without changing the verification API.</p>
    <div class="mt-5 flex gap-2"><button class="btn-primary" :disabled="busy" @click="run()">{{busy?'Checking…':(mode==='checkin'?'Verify & Check-in':'Verify Credential')}}</button><button class="btn-secondary" @click="reset">Clear</button></div>
    <p v-if="error" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{error}}</p>
  </article>

  <article class="panel p-6">
    <p class="panel-kicker">Result</p>
    <div v-if="!result" class="grid min-h-64 place-items-center text-center text-sm text-slate-400"><div><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-50 text-xl">▦</div><p class="mt-3">Waiting for a credential.</p></div></div>
    <div v-else>
      <div class="rounded-2xl p-5" :class="allowed?'bg-emerald-50':'bg-rose-50'"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-full font-black" :class="allowed?'bg-emerald-600 text-white':'bg-rose-600 text-white'">{{allowed?'✓':'×'}}</div><div><p class="text-xs font-black uppercase tracking-widest" :class="allowed?'text-emerald-700':'text-rose-700'">{{allowed?'Access allowed':'Access denied'}}</p><h2 class="mt-1 text-lg font-black">{{result.reason}}</h2></div></div></div>
      <div v-if="result.attendee" class="mt-5 space-y-3 rounded-2xl border border-slate-200 p-5 text-sm"><div class="flex justify-between gap-4"><span class="text-slate-500">Name</span><b>{{result.attendee.name}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Category</span><b>{{result.attendee.category}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Company</span><b>{{result.attendee.company||'—'}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Registration</span><b class="font-mono text-xs">{{result.attendee.id}}</b></div></div>
      <p v-if="result.already_checked_in||result.reason==='ALREADY_CHECKED_IN'" class="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">This attendee was already checked in. No duplicate entry was created.</p>
      <button v-if="allowed&&mode==='verify'&&result.reason!=='ALREADY_CHECKED_IN'" class="btn-primary mt-4 w-full" @click="run('checkin')">Check in now</button>
    </div>
  </article>
</section>
</div></template>
