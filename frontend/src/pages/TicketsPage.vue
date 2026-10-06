<script setup>
import {computed,onMounted,reactive,ref,watch} from 'vue'
import {api} from '../services/api'
import {useEventStore} from '../stores/event'
const events=useEventStore(),rows=ref([]),show=ref(false),busy=ref(false),error=ref('')
const eventId=computed(()=>events.currentId)
const form=reactive({name:'General Admission',price:0,quantity:500,status:'Active',sale_start:'',sale_end:''})
async function load(){if(!events.loaded)await events.load();rows.value=eventId.value?await api.tickets(eventId.value):[]}
onMounted(load);watch(eventId,load)
async function add(){busy.value=true;error.value='';try{const row=await api.createTicket({...form,event_id:eventId.value});rows.value.unshift(row);show.value=false}catch(e){error.value=e.message}finally{busy.value=false}}
async function toggle(row){try{Object.assign(row,await api.updateTicket(row.id,{status:row.status==='Active'?'Paused':'Active'}))}catch(e){error.value=e.message}}
const money=n=>Number(n||0)===0?'Free':new Intl.NumberFormat('en-IN',{style:'currency',currency:events.current?.currency||'INR',maximumFractionDigits:0}).format(n)
</script>
<template><div class="space-y-6"><section class="flex justify-between gap-4"><div><p class="eyebrow">Commerce</p><h1 class="page-title">Ticket Types</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · pricing, capacity and availability.</p></div><button class="btn-primary self-end" :disabled="!eventId" @click="show=true">+ Add Ticket Type</button></section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
<section class="panel overflow-hidden"><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Name</th><th>Price</th><th>Quantity</th><th>Sold</th><th>Utilisation</th><th>Status</th><th></th></tr></thead><tbody><tr v-if="!rows.length"><td colspan="7" class="text-center">{{eventId?'No ticket types yet.':'Create or select an event.'}}</td></tr><tr v-for="r in rows" :key="r.id"><td><b>{{r.name}}</b></td><td>{{money(r.price)}}</td><td>{{r.quantity}}</td><td>{{r.sold}}</td><td>{{r.quantity?Math.round(r.sold/r.quantity*100):0}}%</td><td><span class="status-badge" :class="r.status==='Active'?'badge-published':'badge-draft'">{{r.status}}</span></td><td><button class="btn-secondary py-1.5" @click="toggle(r)">{{r.status==='Active'?'Pause':'Activate'}}</button></td></tr></tbody></table></div></section>
<div v-if="show" class="modal-backdrop" @click.self="show=false"><form class="modal-card" @submit.prevent="add"><h2 class="text-lg font-bold">Add ticket type</h2><div class="mt-5 grid gap-4"><label class="field"><span>Name</span><input v-model="form.name" required/></label><label class="field"><span>Price</span><input v-model.number="form.price" type="number" min="0"/></label><label class="field"><span>Quantity</span><input v-model.number="form.quantity" type="number" min="1"/></label><div class="grid grid-cols-2 gap-3"><label class="field"><span>Sale starts</span><input v-model="form.sale_start" type="date"/></label><label class="field"><span>Sale ends</span><input v-model="form.sale_end" type="date"/></label></div></div><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="show=false">Cancel</button><button class="btn-primary" :disabled="busy">{{busy?'Saving…':'Add Ticket'}}</button></div></form></div></div></template>
