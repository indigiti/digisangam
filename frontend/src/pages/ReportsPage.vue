<script setup>
import {computed,onMounted,ref,watch} from 'vue'
import {api} from '../services/api'
import {useEventStore} from '../stores/event'
const events=useEventStore(),data=ref(null),error=ref('')
const eventId=computed(()=>events.currentId)
async function load(){if(!events.loaded)await events.load();if(!eventId.value){data.value=null;return};try{data.value=await api.reports(eventId.value)}catch(e){error.value=e.message}}
onMounted(load);watch(eventId,load)
const cards=computed(()=>data.value?[
['Registrations',data.value.registrations],['Confirmed',data.value.confirmed],['Checked in',data.value.checked_in],['Paid revenue','₹'+Number(data.value.paid_revenue||0).toLocaleString('en-IN')],
['Refunds',data.value.refunds],['Leads',data.value.leads],['Approved accreditation',data.value.approved_accreditations],['Session entries',data.value.session_entries],
]:[])
function download(type){window.location.assign(api.reportExportUrl(eventId.value,type))}
</script>
<template><div class="space-y-6"><section><p class="eyebrow">Reports</p><h1 class="page-title">Operational Reports</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · live operational summaries and CSV exports.</p></section><p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p><section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article v-for="[label,value] in cards" :key="label" class="panel p-5"><p class="panel-kicker">{{label}}</p><p class="mt-2 text-2xl font-black">{{value}}</p></article></section><section v-if="data?.breakdowns" class="grid gap-5 xl:grid-cols-2"><article v-for="(values,name) in data.breakdowns" :key="name" class="panel p-5"><p class="panel-kicker">{{name.replaceAll('_',' ')}}</p><div class="mt-4 space-y-2"><div v-for="(count,label) in values" :key="label" class="flex justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm"><span>{{label}}</span><b>{{count}}</b></div><p v-if="!Object.keys(values).length" class="text-sm text-slate-400">No data.</p></div></article></section>
<section class="panel p-6"><p class="panel-kicker">Exports</p><h2 class="panel-title">Download source-level reports</h2><div class="mt-5 flex flex-wrap gap-2"><button v-for="r in ['attendees','orders','checkins','leads','accreditation']" :key="r" class="btn-secondary capitalize" :disabled="!eventId" @click="download(r)">Download {{r}} CSV</button></div></section></div></template>
