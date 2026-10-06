<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'
import QrCredential from '../components/QrCredential.vue'
const route=useRoute(),data=ref(null),loading=ref(true),error=ref('')
const money=(n,currency='INR')=>new Intl.NumberFormat('en-IN',{style:'currency',currency,maximumFractionDigits:0}).format(Number(n||0))
onMounted(async()=>{try{data.value=await api.publicConfirmation(route.params.token)}catch(e){error.value=e.message}finally{loading.value=false}})
function printDocument(){window.print()}
</script>
<template>
<main class="min-h-screen bg-[#f5f7fb] p-5 text-slate-950 sm:p-8">
  <div class="mx-auto max-w-3xl">
    <div class="no-print mb-8 flex items-center justify-between gap-4"><div class="flex items-center gap-3"><div class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-600 text-xs font-black text-white">DS</div><div><b>DigiSangam</b><p class="text-xs text-slate-400">Registration confirmation</p></div></div><button v-if="data" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold" @click="printDocument">Print / Save PDF</button></div>
    <div v-if="loading" class="h-96 animate-pulse rounded-[28px] bg-white"></div>
    <div v-else-if="error" class="rounded-[28px] border border-rose-100 bg-white p-10 text-center"><div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-rose-50 font-black text-rose-600">!</div><h1 class="mt-4 text-2xl font-black">Confirmation unavailable</h1><p class="mt-2 text-sm text-slate-500">{{error}}</p></div>
    <template v-else-if="data">
      <section class="print-card overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="bg-slate-950 p-7 text-white sm:p-9"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-3 py-1.5 text-xs font-black" :class="data.attendee.status==='Confirmed'?'bg-emerald-400/15 text-emerald-300':'bg-amber-400/15 text-amber-300'">{{data.attendee.status}}</span><span v-if="data.order" class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-black">{{data.order.status==='paid'?'Payment paid':'Payment '+data.order.status}}</span></div><h1 class="mt-5 text-3xl font-black tracking-tight">{{data.attendee.status==='Confirmed'?'You’re registered.':'Registration received.'}}</h1><p class="mt-2 text-sm text-slate-300">{{data.event?.name}}</p></div>
        <div class="grid gap-7 p-7 sm:p-9 md:grid-cols-[1fr_250px]">
          <div>
            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Attendee</p><h2 class="mt-2 text-xl font-black">{{data.attendee.name}}</h2><p class="mt-1 text-sm text-slate-500">{{data.attendee.email}}</p>
            <div class="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-5 text-sm"><div class="flex justify-between gap-4"><span class="text-slate-500">Ticket</span><b>{{data.ticket?.name||'—'}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Category</span><b>{{data.attendee.category}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Registration ID</span><b class="font-mono text-xs">{{data.attendee.id}}</b></div><div class="flex justify-between gap-4"><span class="text-slate-500">Venue</span><b class="text-right">{{data.event?.location}}</b></div><div v-if="data.order" class="flex justify-between gap-4"><span class="text-slate-500">Payment</span><b>{{data.order.status}}</b></div></div>
            <p v-if="!data.credential" class="mt-5 rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">Your QR credential has not been issued yet. Payment or organizer approval is still pending. This confirmation link remains valid and will show the QR after the state changes.</p>
          </div>
          <div v-if="data.credential"><p class="mb-3 text-center text-xs font-black uppercase tracking-widest text-slate-400">Entry QR</p><QrCredential :value="data.credential.payload"/><p class="mt-3 text-center text-xs leading-5 text-slate-400">Present this QR at event check-in.</p></div>
        </div>
      </section>

      <section v-if="data.receipt" class="print-card mt-5 rounded-[24px] border border-slate-200 bg-white p-7 shadow-sm sm:p-9">
        <div class="flex flex-col justify-between gap-4 sm:flex-row"><div><p class="text-xs font-black uppercase tracking-widest text-slate-400">Payment receipt</p><h2 class="mt-2 text-xl font-black">{{data.receipt.receipt_number}}</h2><p class="mt-1 text-xs text-slate-400">{{data.receipt.issued_at}}</p></div><div class="sm:text-right"><b>{{data.receipt.seller.name}}</b><p v-if="data.receipt.seller.gstin" class="mt-1 text-xs text-slate-500">GSTIN {{data.receipt.seller.gstin}}</p><p v-if="data.receipt.seller.address" class="mt-1 max-w-sm whitespace-pre-line text-xs text-slate-500">{{data.receipt.seller.address}}</p></div></div>
        <div class="my-6 border-t border-slate-100"></div>
        <div class="grid gap-5 sm:grid-cols-2"><div><p class="text-xs font-bold uppercase text-slate-400">Billed to</p><p class="mt-2 font-bold">{{data.receipt.buyer.name}}</p><p class="text-sm text-slate-500">{{data.receipt.buyer.email}}</p></div><div class="sm:text-right"><p class="text-xs font-bold uppercase text-slate-400">Payment</p><p class="mt-2 font-bold">{{data.receipt.payment.status}}</p><p class="text-xs text-slate-500">{{data.receipt.payment.provider||'—'}} · {{data.receipt.payment.reference||'—'}}</p></div></div>
        <div class="mt-6 rounded-2xl bg-slate-50 p-5"><div class="flex justify-between gap-4 text-sm"><span>{{data.receipt.ticket.name}}</span><b>{{money(data.receipt.total,data.receipt.payment.currency)}}</b></div><div class="mt-4 border-t border-slate-200 pt-4 flex justify-between gap-4"><strong>Total</strong><strong class="text-lg">{{money(data.receipt.total,data.receipt.payment.currency)}}</strong></div></div>
      </section>

      <div class="no-print mt-5 flex justify-center gap-2"><RouterLink :to="'/e/'+data.event.id" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold">Back to event</RouterLink><button class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white" @click="printDocument">Print / Save PDF</button></div>
    </template>
  </div>
</main>
</template>
