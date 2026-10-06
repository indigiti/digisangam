<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useEventStore } from '../stores/event'
import { api } from '../services/api'

const events=useEventStore(),busy=ref(false),saved=ref(false),showSeat=ref(false),seats=ref([]),attendees=ref([]),schema=ref(null),error=ref('')
const eventId=computed(()=>events.currentId)
const venue=reactive({event_id:'',name:'',address:'',zones:[],seating:[]})
const seatForm=reactive({attendee_id:'',hall_id:'',seat:''})
const reservedHalls=computed(()=>venue.seating.filter(x=>x.type==='reserved'))
const categories=computed(()=>schema.value?.categories||[])

async function load(){
  error.value=''
  if(!events.loaded)await events.load()
  if(!eventId.value){
    Object.assign(venue,{event_id:'',name:'',address:'',zones:[],seating:[]});seats.value=[];attendees.value=[];schema.value=null;return
  }
  try{
    const [v,s,a,r]=await Promise.all([api.venue(eventId.value),api.seatAssignments(eventId.value),api.attendees(eventId.value),api.registration(eventId.value)])
    Object.assign(venue,v);seats.value=s;attendees.value=a;schema.value=r
  }catch(e){error.value=e.message}
}
onMounted(load);watch(eventId,load)

function addZone(){venue.zones.push({id:'zone_'+Date.now(),name:'New Zone',capacity:100,categories:categories.value.length?[categories.value[0]]:[]})}
function addHall(){venue.seating.push({id:'hall_'+Date.now(),name:'New Hall',type:'general',capacity:100})}
async function save(){busy.value=true;saved.value=false;error.value='';try{Object.assign(venue,await api.updateVenue(eventId.value,venue));saved.value=true}catch(e){error.value=e.message}finally{busy.value=false}}
async function assignSeat(){busy.value=true;error.value='';try{seats.value.unshift(await api.assignSeat(eventId.value,seatForm));showSeat.value=false;Object.assign(seatForm,{attendee_id:'',hall_id:'',seat:''})}catch(e){error.value=e.message}finally{busy.value=false}}
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Venue</p><h1 class="page-title">Zones & Seating</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · access zones, rooms and reserved seating.</p></div><div class="flex items-center gap-2"><span v-if="saved" class="text-xs font-bold text-emerald-600">Saved</span><button class="btn-secondary" :disabled="!eventId||!reservedHalls.length" @click="showSeat=true">Assign Seat</button><button class="btn-primary" :disabled="busy||!eventId" @click="save">Save Venue</button></div></section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
<section class="panel p-6"><div class="grid gap-5 md:grid-cols-2"><label class="field"><span>Venue name</span><input v-model="venue.name"/></label><label class="field"><span>Address</span><input v-model="venue.address"/></label></div></section>
<section class="grid gap-5 xl:grid-cols-2">
<article class="panel p-6"><div class="flex items-center justify-between"><div><p class="panel-kicker">Access control</p><h2 class="panel-title">Zones</h2></div><button class="btn-secondary" :disabled="!eventId" @click="addZone">+ Zone</button></div><div class="mt-5 space-y-3"><div v-for="(z,i) in venue.zones" :key="z.id" class="rounded-2xl border border-slate-200 p-4"><div class="grid gap-3 sm:grid-cols-[1fr_100px]"><input v-model="z.name" class="control font-bold"/><input v-model.number="z.capacity" class="control" type="number" min="0"/></div><p class="mt-3 text-xs text-slate-400">Allowed categories</p><div v-if="categories.length" class="mt-2 flex flex-wrap gap-2"><label v-for="c in categories" :key="c" class="soft-pill cursor-pointer"><input type="checkbox" class="mr-1 accent-indigo-600" :checked="z.categories?.includes(c)" @change="z.categories=$event.target.checked?[...(z.categories||[]),c]:(z.categories||[]).filter(x=>x!==c)"/>{{c}}</label></div><p v-else class="mt-2 text-xs text-amber-600">Configure registration categories first.</p><button class="mt-3 text-xs font-bold text-rose-600" @click="venue.zones.splice(i,1)">Remove</button></div><div v-if="!venue.zones.length" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">No access zones yet.</div></div></article>
<article class="panel p-6"><div class="flex items-center justify-between"><div><p class="panel-kicker">Capacity planning</p><h2 class="panel-title">Rooms & Seating</h2></div><button class="btn-secondary" :disabled="!eventId" @click="addHall">+ Hall</button></div><div class="mt-5 space-y-3"><div v-for="(h,i) in venue.seating" :key="h.id" class="rounded-2xl border border-slate-200 p-4"><div class="grid gap-3 sm:grid-cols-2"><input v-model="h.name" class="control font-bold"/><select v-model="h.type" class="control"><option value="general">General admission</option><option value="reserved">Reserved seating</option></select></div><div class="mt-3 grid gap-3 sm:grid-cols-2"><label class="field" v-if="h.type==='general'"><span>Capacity</span><input v-model.number="h.capacity" type="number" min="0"/></label><template v-else><label class="field"><span>Rows</span><input v-model.number="h.rows" type="number" min="1"/></label><label class="field"><span>Seats / row</span><input v-model.number="h.seats_per_row" type="number" min="1"/></label></template></div><button class="mt-2 text-xs font-bold text-rose-600" @click="venue.seating.splice(i,1)">Remove</button></div><div v-if="!venue.seating.length" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">No rooms configured yet.</div></div></article>
</section>
<section class="panel overflow-hidden"><div class="border-b p-4"><p class="panel-kicker">Reserved seating</p><h2 class="panel-title">Seat assignments</h2></div><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Attendee</th><th>Hall</th><th>Seat</th><th>Assigned</th></tr></thead><tbody><tr v-if="!seats.length"><td colspan="4" class="text-center">No seats assigned.</td></tr><tr v-for="s in seats" :key="s.id"><td>{{attendees.find(x=>x.id===s.attendee_id)?.name||s.attendee_id}}</td><td>{{venue.seating.find(x=>x.id===s.hall_id)?.name||s.hall_id}}</td><td><b>{{s.seat}}</b></td><td>{{s.assigned_at?.slice(0,16).replace('T',' ')}}</td></tr></tbody></table></div></section>
<div v-if="showSeat" class="modal-backdrop" @click.self="showSeat=false"><form class="modal-card" @submit.prevent="assignSeat"><h2 class="text-lg font-bold">Assign reserved seat</h2><div class="mt-5 grid gap-4"><label class="field"><span>Attendee</span><select v-model="seatForm.attendee_id" required><option value="">Select attendee</option><option v-for="a in attendees" :key="a.id" :value="a.id">{{a.name}} · {{a.category}}</option></select></label><label class="field"><span>Hall</span><select v-model="seatForm.hall_id" required><option value="">Select hall</option><option v-for="h in reservedHalls" :key="h.id" :value="h.id">{{h.name}}</option></select></label><label class="field"><span>Seat</span><input v-model="seatForm.seat" required placeholder="e.g. A12"/></label></div><div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="showSeat=false">Cancel</button><button class="btn-primary" :disabled="busy">Assign</button></div></form></div>
</div></template>
