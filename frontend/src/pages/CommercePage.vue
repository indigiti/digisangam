<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
const rows=ref([]),show=ref(false),busy=ref(false)
const form=reactive({attendee_id:'',ticket_id:'tic_2',amount:3999,currency:'INR',status:'pending',payment_reference:''})
onMounted(async()=>rows.value=await api.orders())
const total=computed(()=>rows.value.reduce((sum,x)=>sum+Number(x.amount||0),0))
const paid=computed(()=>rows.value.filter(x=>x.status==='paid').reduce((sum,x)=>sum+Number(x.amount||0),0))
const money=n=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:0}).format(n||0)
async function createOrder(){busy.value=true;try{const row=await api.createOrder(form);rows.value.unshift(row);show.value=false}catch{}finally{busy.value=false}}
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Commerce</p><h1 class="page-title">Orders & Payments</h1><p class="page-subtitle">Phase 1 order ledger ready for payment-gateway adapters.</p></div><button class="btn-primary" @click="show=true">+ Create Order</button></section>
<section class="grid gap-4 sm:grid-cols-3"><article class="panel p-5"><p class="panel-kicker">Orders</p><p class="mt-2 text-2xl font-black">{{rows.length}}</p></article><article class="panel p-5"><p class="panel-kicker">Gross value</p><p class="mt-2 text-2xl font-black">{{money(total)}}</p></article><article class="panel p-5"><p class="panel-kicker">Paid value</p><p class="mt-2 text-2xl font-black">{{money(paid)}}</p></article></section>
<section class="panel overflow-hidden"><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Order</th><th>Attendee</th><th>Ticket</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead><tbody><tr v-if="!rows.length"><td colspan="6" class="text-center">No orders yet.</td></tr><tr v-for="r in rows" :key="r.id"><td class="font-mono">{{r.id}}</td><td>{{r.attendee_id||'—'}}</td><td>{{r.ticket_id||'—'}}</td><td>{{money(r.amount)}}</td><td><span class="status-badge" :class="r.status==='paid'?'badge-published':'badge-draft'">{{r.status}}</span></td><td>{{r.created_at?.slice(0,10)}}</td></tr></tbody></table></div></section>
<div v-if="show" class="modal-backdrop" @click.self="show=false"><form class="modal-card" @submit.prevent="createOrder"><h2 class="text-lg font-bold">Create order</h2><div class="mt-5 grid gap-4"><label class="field"><span>Attendee ID</span><input v-model="form.attendee_id"/></label><label class="field"><span>Ticket ID</span><input v-model="form.ticket_id"/></label><label class="field"><span>Amount (₹)</span><input v-model.number="form.amount" type="number" min="0"/></label><label class="field"><span>Status</span><select v-model="form.status"><option>pending</option><option>paid</option><option>failed</option><option>refunded</option></select></label></div><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="show=false">Cancel</button><button class="btn-primary" :disabled="busy">Create</button></div></form></div>
</div></template>
