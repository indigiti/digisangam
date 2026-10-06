<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
const eventId='evt_001',busy=ref(false),saved=ref(false)
const venue=reactive({event_id:eventId,name:'',address:'',zones:[],seating:[]})
onMounted(async()=>Object.assign(venue,await api.venue(eventId)))
function addZone(){venue.zones.push({id:'zone_'+Date.now(),name:'New Zone',capacity:100,categories:['General']})}
function addHall(){venue.seating.push({id:'hall_'+Date.now(),name:'New Hall',type:'general',capacity:100})}
async function save(){busy.value=true;saved.value=false;try{Object.assign(venue,await api.updateVenue(eventId,venue));saved.value=true}finally{busy.value=false}}
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Venue</p><h1 class="page-title">Zones & Seating</h1><p class="page-subtitle">Model access zones, room capacity and reserved/general seating for onsite rules.</p></div><div class="flex items-center gap-3"><span v-if="saved" class="text-xs font-bold text-emerald-600">Saved</span><button class="btn-primary" :disabled="busy" @click="save">Save Venue</button></div></section>
<section class="panel p-6"><div class="grid gap-5 md:grid-cols-2"><label class="field"><span>Venue name</span><input v-model="venue.name"/></label><label class="field"><span>Address</span><input v-model="venue.address"/></label></div></section>
<section class="grid gap-5 xl:grid-cols-2">
<article class="panel p-6"><div class="flex items-center justify-between"><div><p class="panel-kicker">Access control</p><h2 class="panel-title">Zones</h2></div><button class="btn-secondary" @click="addZone">+ Zone</button></div><div class="mt-5 space-y-3"><div v-for="(z,i) in venue.zones" :key="z.id" class="rounded-2xl border border-slate-200 p-4"><div class="grid gap-3 sm:grid-cols-[1fr_100px]"><input v-model="z.name" class="control font-bold"/><input v-model.number="z.capacity" class="control" type="number"/></div><p class="mt-3 text-xs text-slate-400">Allowed categories</p><input class="control mt-1" :value="z.categories?.join(', ')" @change="z.categories=$event.target.value.split(',').map(x=>x.trim()).filter(Boolean)"/><button class="mt-2 text-xs font-bold text-rose-600" @click="venue.zones.splice(i,1)">Remove</button></div></div></article>
<article class="panel p-6"><div class="flex items-center justify-between"><div><p class="panel-kicker">Capacity planning</p><h2 class="panel-title">Rooms & Seating</h2></div><button class="btn-secondary" @click="addHall">+ Hall</button></div><div class="mt-5 space-y-3"><div v-for="(h,i) in venue.seating" :key="h.id" class="rounded-2xl border border-slate-200 p-4"><div class="grid gap-3 sm:grid-cols-2"><input v-model="h.name" class="control font-bold"/><select v-model="h.type" class="control"><option value="general">General admission</option><option value="reserved">Reserved seating</option></select></div><div class="mt-3 grid gap-3 sm:grid-cols-2"><label class="field" v-if="h.type==='general'"><span>Capacity</span><input v-model.number="h.capacity" type="number"/></label><template v-else><label class="field"><span>Rows</span><input v-model.number="h.rows" type="number"/></label><label class="field"><span>Seats / row</span><input v-model.number="h.seats_per_row" type="number"/></label></template></div><button class="mt-2 text-xs font-bold text-rose-600" @click="venue.seating.splice(i,1)">Remove</button></div></div></article>
</section>
</div></template>
