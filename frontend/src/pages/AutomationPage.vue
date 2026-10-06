<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

const rows=ref([]),selected=ref(null),show=ref(false),testResult=ref(null),busy=ref(false)
const triggers=['person.registered','attendee.confirmed','payment.captured','attendee.checked_in','session.entered','event.ended']
const form=reactive({event_id:'evt_001',name:'New attendee journey',trigger:'attendee.confirmed',conditions:[],actions:[{type:'email',template:'registration_confirmation'}],enabled:false})
onMounted(async()=>{rows.value=await api.automations();selected.value=rows.value[0]||null})
async function create(){const row=await api.createAutomation(form);rows.value.unshift(row);selected.value=row;show.value=false}
async function toggle(row){const updated=await api.updateAutomation(row.id,{enabled:!row.enabled});Object.assign(row,updated)}
async function test(row){busy.value=true;try{testResult.value=await api.fireAutomation({trigger:row.trigger,context:{email:'test@example.test',phone:'919999999999',event_id:row.event_id,category:'General'}})}finally{busy.value=false}}
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Automation</p><h1 class="page-title">Workflow Engine</h1><p class="page-subtitle">Trigger → conditions → actions → wait → next action, executed through the notification outbox.</p></div><button class="btn-primary" @click="show=true">+ New Workflow</button></section>
<section class="grid gap-5 xl:grid-cols-[320px_1fr]">
<aside class="panel overflow-hidden"><div class="border-b border-slate-100 p-4"><p class="panel-kicker">Workflows</p></div><button v-for="r in rows" :key="r.id" @click="selected=r" class="w-full border-b border-slate-100 p-4 text-left hover:bg-slate-50" :class="selected?.id===r.id?'bg-indigo-50':''"><div class="flex justify-between gap-3"><div><b class="text-sm">{{r.name}}</b><p class="mt-1 text-xs text-slate-400">{{r.trigger}}</p></div><span class="status-badge" :class="r.enabled?'badge-published':'badge-draft'">{{r.enabled?'Active':'Off'}}</span></div></button></aside>
<article v-if="selected" class="panel p-6"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="panel-kicker">Workflow canvas</p><h2 class="mt-1 text-xl font-black">{{selected.name}}</h2></div><div class="flex gap-2"><button class="btn-secondary" @click="test(selected)">Test trigger</button><button class="btn-primary" @click="toggle(selected)">{{selected.enabled?'Disable':'Enable'}}</button></div></div>
<div class="mt-8 grid gap-3"><div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4"><p class="text-[10px] font-black uppercase tracking-widest text-indigo-600">Trigger</p><b class="mt-1 block">{{selected.trigger}}</b></div><template v-for="(a,i) in selected.actions" :key="i"><div class="mx-auto h-6 w-px bg-slate-300"></div><div class="rounded-2xl border border-slate-200 p-4"><div class="flex justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{a.type}}</p><b class="mt-1 block">{{a.type==='wait'?(a.minutes+' minute delay'):(a.template||'Action')}}</b></div><span class="soft-pill">Step {{i+1}}</span></div></div></template></div>
<p v-if="testResult" class="mt-5 rounded-xl bg-cyan-50 p-3 text-xs font-semibold text-cyan-800">Test fired {{testResult.trigger}}: {{testResult.matched}} workflow(s) matched.</p></article>
</section>
<div v-if="show" class="modal-backdrop" @click.self="show=false"><form class="modal-card" @submit.prevent="create"><h2 class="text-lg font-bold">New workflow</h2><div class="mt-5 grid gap-4"><label class="field"><span>Name</span><input v-model="form.name" required/></label><label class="field"><span>Trigger</span><select v-model="form.trigger"><option v-for="t in triggers" :key="t">{{t}}</option></select></label><label class="field"><span>First action</span><select v-model="form.actions[0].type"><option value="email">Send email</option><option value="whatsapp">Send WhatsApp</option></select></label><label class="field"><span>Template</span><input v-model="form.actions[0].template"/></label></div><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="show=false">Cancel</button><button class="btn-primary">Create</button></div></form></div>
</div></template>
