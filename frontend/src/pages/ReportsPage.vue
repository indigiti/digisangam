<script setup>
import {computed,onMounted,reactive,ref,watch} from 'vue'
import {api} from '../services/api'
import {useEventStore} from '../stores/event'
const events=useEventStore(),data=ref(null),definitions=ref([]),error=ref(''),show=ref(false)
const eventId=computed(()=>events.currentId)
const form=reactive({name:'Custom attendee report',dataset:'attendees',columns:'name,email,category,status,company',filter_field:'status',filter_value:'Confirmed'})
async function load(){if(!events.loaded)await events.load();if(!eventId.value){data.value=null;definitions.value=[];return};try{[data.value,definitions.value]=await Promise.all([api.reports(eventId.value),api.reportDefinitions(eventId.value)])}catch(e){error.value=e.message}}
onMounted(load);watch(eventId,load)
const cards=computed(()=>data.value?[
['Registrations',data.value.registrations],['Confirmed',data.value.confirmed],['Checked in',data.value.checked_in],['Paid revenue','₹'+Number(data.value.paid_revenue||0).toLocaleString('en-IN')],
['Refunds',data.value.refunds],['Leads',data.value.leads],['Approved accreditation',data.value.approved_accreditations],['Session entries',data.value.session_entries],
]:[])
function download(type){window.location.assign(api.reportExportUrl(eventId.value,type))}
function downloadDefinition(id){window.location.assign(api.reportDefinitionExportUrl(id))}
async function createDefinition(){
  error.value=''
  try{
    const filters={}
    if(form.filter_field&&form.filter_value)filters[form.filter_field]=form.filter_value
    const row=await api.createReportDefinition({event_id:eventId.value,name:form.name,dataset:form.dataset,columns:form.columns.split(',').map(x=>x.trim()).filter(Boolean),filters})
    definitions.value.unshift(row);show.value=false
  }catch(e){error.value=e.message}
}
async function removeDefinition(row){try{await api.deleteReportDefinition(row.id);definitions.value=definitions.value.filter(x=>x.id!==row.id)}catch(e){error.value=e.message}}
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Reports</p><h1 class="page-title">Operational Reports</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · live summaries, breakdowns, exports and reusable custom report definitions.</p></div><button class="btn-primary" :disabled="!eventId" @click="show=true">+ Build Report</button></section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article v-for="[label,value] in cards" :key="label" class="panel p-5"><p class="panel-kicker">{{label}}</p><p class="mt-2 text-2xl font-black">{{value}}</p></article></section>
<section v-if="data?.breakdowns" class="grid gap-5 xl:grid-cols-2"><article v-for="(values,name) in data.breakdowns" :key="name" class="panel p-5"><p class="panel-kicker">{{name.replaceAll('_',' ')}}</p><div class="mt-4 space-y-2"><div v-for="(count,label) in values" :key="label" class="flex justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm"><span>{{label}}</span><b>{{count}}</b></div><p v-if="!Object.keys(values).length" class="text-sm text-slate-400">No data.</p></div></article></section>
<section class="panel overflow-hidden"><div class="border-b p-5"><p class="panel-kicker">Saved report builder</p><h2 class="panel-title">Reusable custom exports</h2></div><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Name</th><th>Dataset</th><th>Columns</th><th>Filters</th><th></th></tr></thead><tbody><tr v-if="!definitions.length"><td colspan="5" class="text-center">No saved custom reports.</td></tr><tr v-for="r in definitions" :key="r.id"><td><b>{{r.name}}</b></td><td><span class="soft-pill">{{r.dataset}}</span></td><td class="text-xs">{{r.columns?.join(', ')||'All'}}</td><td class="text-xs">{{Object.entries(r.filters||{}).map(([k,v])=>k+'='+v).join(', ')||'None'}}</td><td><div class="flex gap-1"><button class="btn-secondary py-1.5" @click="downloadDefinition(r.id)">CSV</button><button class="btn-secondary py-1.5 text-rose-600" @click="removeDefinition(r)">Delete</button></div></td></tr></tbody></table></div></section>
<section class="panel p-6"><p class="panel-kicker">Quick exports</p><div class="mt-5 flex flex-wrap gap-2"><button v-for="r in ['attendees','orders','checkins','leads','accreditation']" :key="r" class="btn-secondary capitalize" :disabled="!eventId" @click="download(r)">Download {{r}} CSV</button></div></section>
<div v-if="show" class="modal-backdrop" @click.self="show=false"><form class="modal-card max-h-[90vh] overflow-y-auto" @submit.prevent="createDefinition"><h2 class="text-lg font-bold">Build custom report</h2><div class="mt-5 grid gap-4"><label class="field"><span>Name</span><input v-model="form.name" required/></label><label class="field"><span>Dataset</span><select v-model="form.dataset"><option>attendees</option><option>orders</option><option>checkins</option><option>leads</option><option>accreditation</option></select></label><label class="field"><span>Columns</span><input v-model="form.columns" placeholder="name,email,status"/></label><div class="grid grid-cols-2 gap-3"><label class="field"><span>Filter field</span><input v-model="form.filter_field" placeholder="status"/></label><label class="field"><span>Filter value</span><input v-model="form.filter_value" placeholder="Confirmed"/></label></div></div><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="show=false">Cancel</button><button class="btn-primary">Save Report</button></div></form></div>
</div></template>
