<script setup>
import {computed,onMounted,reactive,ref,watch} from 'vue'
import {api} from '../services/api'
import {useEventStore} from '../stores/event'

const events=useEventStore(),rows=ref([]),tickets=ref([]),attendees=ref([]),show=ref(false),busy=ref(false),error=ref('')
const eventId=computed(()=>events.currentId)
const form=reactive({attendee_id:'',ticket_id:'',amount:0,payment_reference:''})

async function load(){
  if(!events.loaded)await events.load()
  if(!eventId.value){rows.value=[];tickets.value=[];attendees.value=[];return}
  try{[rows.value,tickets.value,attendees.value]=await Promise.all([api.orders(eventId.value),api.tickets(eventId.value),api.attendees(eventId.value)])}
  catch(e){error.value=e.message}
}
onMounted(load);watch(eventId,load)

const total=computed(()=>rows.value.reduce((sum,x)=>sum+Number(x.amount||0),0))
const paid=computed(()=>rows.value.filter(x=>x.status==='paid').reduce((sum,x)=>sum+Number(x.amount||0),0))
const money=n=>new Intl.NumberFormat('en-IN',{style:'currency',currency:events.current?.currency||'INR',maximumFractionDigits:0}).format(n||0)
function ticketChanged(){const ticket=tickets.value.find(x=>x.id===form.ticket_id);if(ticket)form.amount=ticket.price}

async function createOrder(){
  busy.value=true;error.value=''
  try{
    const row=await api.createOrder({...form,event_id:eventId.value,currency:events.current?.currency||'INR'})
    rows.value.unshift(row);show.value=false
    Object.assign(form,{attendee_id:'',ticket_id:'',amount:0,payment_reference:''})
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
async function capture(row){
  const reference=window.prompt('Payment reference / receipt number (optional).',row.payment_reference||'')
  if(reference===null)return
  busy.value=true;error.value=''
  try{
    const result=await api.captureOrder(row.id,{payment_reference:reference})
    Object.assign(row,result.order)
    if(result.attendee){
      const attendee=attendees.value.find(x=>x.id===result.attendee.id)
      if(attendee)Object.assign(attendee,result.attendee)
    }
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
async function refund(row){
  if(!window.confirm('Mark this paid order as refunded? The attendee will no longer pass paid-entry checks.'))return
  busy.value=true;error.value=''
  try{
    const result=await api.refundOrder(row.id,{payment_reference:row.payment_reference||''})
    Object.assign(row,result.order)
    if(result.attendee){
      const attendee=attendees.value.find(x=>x.id===result.attendee.id)
      if(attendee)Object.assign(attendee,result.attendee)
    }
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
</script>

<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
  <div><p class="eyebrow">Commerce</p><h1 class="page-title">Orders & Payments</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · order ledger, manual settlement and verified provider payments.</p></div>
  <button class="btn-primary" :disabled="!eventId" @click="show=true">+ Manual Order</button>
</section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>

<section class="grid gap-4 sm:grid-cols-3">
  <article class="panel p-5"><p class="panel-kicker">Orders</p><p class="mt-2 text-2xl font-black">{{rows.length}}</p></article>
  <article class="panel p-5"><p class="panel-kicker">Gross value</p><p class="mt-2 text-2xl font-black">{{money(total)}}</p></article>
  <article class="panel p-5"><p class="panel-kicker">Paid value</p><p class="mt-2 text-2xl font-black">{{money(paid)}}</p></article>
</section>

<section class="panel overflow-hidden">
<div class="overflow-x-auto"><table class="data-table">
<thead><tr><th>Order</th><th>Attendee</th><th>Amount</th><th>Provider</th><th>Reference</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
<tbody>
  <tr v-if="!rows.length"><td colspan="8" class="text-center">No orders for this event.</td></tr>
  <tr v-for="row in rows" :key="row.id">
    <td class="font-mono">{{row.id}}</td>
    <td>{{attendees.find(x=>x.id===row.attendee_id)?.name||row.attendee_id||'—'}}</td>
    <td>{{money(row.amount)}}</td>
    <td><span class="soft-pill">{{row.provider||'manual'}}</span></td>
    <td class="font-mono text-xs">{{row.payment_reference||row.provider_order_id||'—'}}</td>
    <td><span class="status-badge" :class="row.status==='paid'?'badge-published':row.status==='refunded'?'bg-slate-100 text-slate-600':'badge-draft'">{{row.status}}</span></td>
    <td>{{row.created_at?.slice(0,10)}}</td>
    <td><div class="flex gap-2">
      <button v-if="['pending','failed'].includes(row.status)" class="btn-primary py-1.5" :disabled="busy" @click="capture(row)">Mark paid</button>
      <button v-if="row.status==='paid'" class="btn-secondary py-1.5" :disabled="busy" @click="refund(row)">Refund</button>
    </div></td>
  </tr>
</tbody>
</table></div>
</section>

<div v-if="show" class="modal-backdrop" @click.self="show=false">
<form class="modal-card" @submit.prevent="createOrder">
  <h2 class="text-lg font-bold">Create manual order</h2>
  <p class="mt-1 text-sm text-slate-500">Paid orders are settled explicitly after creation so attendee status and notifications stay consistent.</p>
  <div class="mt-5 grid gap-4">
    <label class="field"><span>Attendee</span><select v-model="form.attendee_id"><option value="">Optional</option><option v-for="a in attendees" :key="a.id" :value="a.id">{{a.name}} · {{a.email}}</option></select></label>
    <label class="field"><span>Ticket</span><select v-model="form.ticket_id" @change="ticketChanged"><option value="">Select</option><option v-for="t in tickets" :key="t.id" :value="t.id">{{t.name}}</option></select></label>
    <label class="field"><span>Amount</span><input v-model.number="form.amount" type="number" min="0"/></label>
    <label class="field"><span>Reference (optional)</span><input v-model="form.payment_reference" placeholder="Invoice or manual receipt reference"/></label>
  </div>
  <div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="show=false">Cancel</button><button class="btn-primary" :disabled="busy">{{busy?'Creating…':'Create pending order'}}</button></div>
</form>
</div>
</div></template>
