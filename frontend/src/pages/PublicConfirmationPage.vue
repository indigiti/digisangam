<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'
import QrCredential from '../components/QrCredential.vue'
const route=useRoute(),data=ref(null),loading=ref(true),error=ref('')
onMounted(async()=>{try{data.value=await api.publicConfirmation(route.params.token)}catch(e){error.value=e.message}finally{loading.value=false}})
</script>
<template>
<main class="min-h-screen bg-[#f5f7fb] p-5 text-slate-950 sm:p-8">
  <div class="mx-auto max-w-3xl">
    <div class="mb-8 flex items-center gap-3"><div class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-600 text-xs font-black text-white">DS</div><div><b>DigiSangam</b><p class="text-xs text-slate-400">Registration confirmation</p></div></div>
    <div v-if="loading" class="h-96 animate-pulse rounded-[28px] bg-white"></div>
    <div v-else-if="error" class="rounded-[28px] border border-rose-100 bg-white p-10 text-center"><div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-rose-50 font-black text-rose-600">!</div><h1 class="mt-4 text-2xl font-black">Confirmation unavailable</h1><p class="mt-2 text-sm text-slate-500">{{error}}</p></div>
    <template v-else-if="data">
      <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="bg-slate-950 p-7 text-white sm:p-9"><span class="rounded-full px-3 py-1.5 text-xs font-black" :class="data.attendee.status==='Confirmed'?'bg-emerald-400/15 text-emerald-300':'bg-amber-400/15 text-amber-300'">{{data.attendee.status}}</span><h1 class="mt-5 text-3xl font-black tracking-tight">{{data.attendee.status==='Confirmed'?'You’re registered.':'Registration received.'}}</h1><p class="mt-2 text-sm text-slate-300">{{data.event?.name}}</p></div>
        <div class="grid gap-7 p-7 sm:p-9 md:grid-cols-[1fr_250px]">
          <div>
            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Attendee</p><h2 class="mt-2 text-xl font-black">{{data.attendee.name}}</h2><p class="mt-1 text-sm text-slate-500">{{data.attendee.email}}</p>
            <div class="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-5 text-sm"><div class="flex justify-between gap-4"><span class="text-slate-500">Ticket</span><b>{{data.ticket?.name||'—'}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Category</span><b>{{data.attendee.category}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Registration ID</span><b class="font-mono text-xs">{{data.attendee.id}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Venue</span><b class="text-right">{{data.event?.location}}</b></div></div>
            <p v-if="!data.credential" class="mt-5 rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">Your QR credential has not been issued yet. This can happen while payment or organizer approval is pending. The confirmation link remains valid.</p>
          </div>
          <div v-if="data.credential"><p class="mb-3 text-center text-xs font-black uppercase tracking-widest text-slate-400">Entry QR</p><QrCredential :value="data.credential.payload"/><p class="mt-3 text-center text-xs leading-5 text-slate-400">Present this QR at event check-in.</p></div>
        </div>
      </section>
      <div class="mt-5 flex justify-center"><RouterLink :to="'/e/'+data.event.id" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold">Back to event</RouterLink></div>
    </template>
  </div>
</main>
</template>
