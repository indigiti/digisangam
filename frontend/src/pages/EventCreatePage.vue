<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useEventStore } from '../stores/event'

const router=useRouter(),eventStore=useEventStore()
const step=ref(1),saving=ref(false),error=ref('')
const steps=['Identity','Venue & Date','Registration','Branding','Review']
const form=reactive({
  name:'',description:'',type:'Conference',category:'Business',format:'in_person',
  start_date:'',end_date:'',timezone:'Asia/Kolkata',location:'',venue_name:'',website:'',currency:'INR',privacy:'public',
  registration:{title:'',approval_mode:'auto',categories:['General','VIP'],fields:[
    {id:'fld_name',label:'Full Name',type:'text',required:true,visibility:'always'},
    {id:'fld_email',label:'Email Address',type:'email',required:true,visibility:'always'},
    {id:'fld_phone',label:'Mobile Number',type:'phone',required:true,visibility:'always'},
    {id:'fld_category',label:'Category',type:'select',required:true,visibility:'always'},
    {id:'fld_company',label:'Company Name',type:'text',required:false,visibility:'always'},
  ]},
  ticket:{enabled:true,name:'General Admission',price:0,quantity:500,sale_start:'',sale_end:''},
  branding:{brand_name:'',logo_url:'',poster_url:'',cover_url:'',primary_color:'#4f46e5',secondary_color:'#06b6d4',background_color:'#0f172a'},
  organizer:{name:'',email:'',phone:''},
  public_page:{headline:'',show_location:true,show_organizer:true},
})
const categoriesText=ref('General, VIP')
const canNext=computed(()=>{
  if(step.value===1) return form.name.trim()!==''
  if(step.value===2) return form.start_date!=='' && (form.end_date===''||form.end_date>=form.start_date)
  if(step.value===3) return categoriesText.value.split(',').some(x=>x.trim())
  return true
})
function next(){
  error.value=''
  if(!canNext.value){error.value=step.value===1?'Event name is required.':step.value===2?'Set a valid event date range.':'Add at least one registration category.';return}
  if(step.value<5) step.value++
}
async function create(){
  error.value='';saving.value=true
  try{
    form.registration.categories=categoriesText.value.split(',').map(x=>x.trim()).filter(Boolean)
    if(!form.registration.title) form.registration.title=form.name+' Registration'
    if(!form.branding.brand_name) form.branding.brand_name=form.name
    const event=await api.createEvent(form)
    eventStore.add(event)
    await router.replace('/events/'+event.id)
  }catch(e){error.value=e.message||'Unable to create event.'}
  finally{saving.value=false}
}
</script>

<template><div class="mx-auto max-w-5xl space-y-6">
<section><p class="eyebrow">Event builder</p><h1 class="page-title">Create Event</h1><p class="page-subtitle">Every step below is persisted into the real event configuration.</p></section>
<div class="panel p-4"><div class="grid grid-cols-5 gap-2"><div v-for="(s,i) in steps" :key="s" class="text-center"><div class="mx-auto grid h-8 w-8 place-items-center rounded-full text-xs font-bold" :class="i+1<=step?'bg-indigo-600 text-white':'bg-slate-100 text-slate-400'">{{i+1}}</div><p class="mt-2 hidden text-[11px] font-semibold sm:block" :class="i+1===step?'text-indigo-700':'text-slate-400'">{{s}}</p></div></div></div>

<form class="panel p-5 sm:p-7" @submit.prevent="step===5?create():next()">
<div v-if="step===1">
  <h2 class="text-lg font-bold">Event identity</h2><p class="mt-1 text-sm text-slate-500">Public name, event model and description.</p>
  <div class="mt-6 grid gap-5 md:grid-cols-2">
    <label class="md:col-span-2 field"><span>Event name *</span><input v-model="form.name" required placeholder="e.g. DigiSangam Leadership Summit 2027"/></label>
    <label class="md:col-span-2 field"><span>Description</span><textarea v-model="form.description" rows="4" placeholder="What is this event about?"></textarea></label>
    <label class="field"><span>Event type</span><select v-model="form.type"><option>Conference</option><option>Expo</option><option>Awards</option><option>Festival</option><option>Workshop</option><option>Webinar</option><option>Meetup</option></select></label>
    <label class="field"><span>Category</span><input v-model="form.category" placeholder="Business, Technology…"/></label>
    <label class="field"><span>Format</span><select v-model="form.format"><option value="in_person">In person</option><option value="hybrid">Hybrid</option><option value="virtual">Virtual</option></select></label>
    <label class="field"><span>Public website</span><input v-model="form.website" placeholder="https://…"/></label>
  </div>
</div>

<div v-else-if="step===2">
  <h2 class="text-lg font-bold">Venue, date & regional settings</h2>
  <div class="mt-6 grid gap-5 md:grid-cols-2">
    <label class="field"><span>Start date *</span><input type="date" v-model="form.start_date" required/></label>
    <label class="field"><span>End date</span><input type="date" v-model="form.end_date"/></label>
    <label class="field"><span>Venue name</span><input v-model="form.venue_name" placeholder="Convention Centre"/></label>
    <label class="field"><span>Location / address</span><input v-model="form.location" placeholder="Mumbai, India"/></label>
    <label class="field"><span>Timezone</span><input v-model="form.timezone"/></label>
    <label class="field"><span>Currency</span><select v-model="form.currency"><option>INR</option><option>USD</option><option>AED</option><option>EUR</option><option>GBP</option></select></label>
    <label class="field"><span>Privacy</span><select v-model="form.privacy"><option value="public">Public</option><option value="private">Private</option><option value="invite_only">Invite only</option></select></label>
  </div>
</div>

<div v-else-if="step===3">
  <h2 class="text-lg font-bold">Registration & first ticket</h2><p class="mt-1 text-sm text-slate-500">These settings are created with the event, not as demo placeholders.</p>
  <div class="mt-6 grid gap-5 md:grid-cols-2">
    <label class="field"><span>Registration title</span><input v-model="form.registration.title" :placeholder="form.name+' Registration'"/></label>
    <label class="field"><span>Approval mode</span><select v-model="form.registration.approval_mode"><option value="auto">Auto approve</option><option value="manual">Manual approval</option><option value="invite_only">Invite only</option></select></label>
    <label class="field md:col-span-2"><span>Attendee categories</span><input v-model="categoriesText" placeholder="General, VIP, Speaker"/></label>
  </div>
  <div class="mt-6 rounded-2xl border border-slate-200 p-5">
    <label class="flex items-center justify-between font-bold">Create first ticket type <input v-model="form.ticket.enabled" type="checkbox" class="accent-indigo-600"/></label>
    <div v-if="form.ticket.enabled" class="mt-4 grid gap-4 md:grid-cols-3">
      <label class="field"><span>Ticket name</span><input v-model="form.ticket.name"/></label>
      <label class="field"><span>Price</span><input v-model.number="form.ticket.price" type="number" min="0"/></label>
      <label class="field"><span>Capacity</span><input v-model.number="form.ticket.quantity" type="number" min="1"/></label>
    </div>
  </div>
</div>

<div v-else-if="step===4">
  <h2 class="text-lg font-bold">Branding & organizer</h2><p class="mt-1 text-sm text-slate-500">Brand settings immediately drive the public event page and badge defaults.</p>
  <div class="mt-6 grid gap-5 md:grid-cols-2">
    <label class="field"><span>Brand name</span><input v-model="form.branding.brand_name" :placeholder="form.name"/></label>
    <label class="field"><span>Organizer name</span><input v-model="form.organizer.name"/></label>
    <label class="field md:col-span-2"><span>Logo URL</span><input v-model="form.branding.logo_url" placeholder="https://…"/></label>
    <label class="field"><span>Event poster URL (2:3)</span><input v-model="form.branding.poster_url" placeholder="Portrait artwork"/></label>
    <label class="field"><span>Hero banner URL (16:9)</span><input v-model="form.branding.cover_url" placeholder="Wide event artwork"/></label>
    <label class="field"><span>Primary colour</span><input v-model="form.branding.primary_color" type="color" class="h-11"/></label>
    <label class="field"><span>Secondary colour</span><input v-model="form.branding.secondary_color" type="color" class="h-11"/></label>
    <label class="field"><span>Organizer email</span><input v-model="form.organizer.email" type="email"/></label>
    <label class="field"><span>Organizer phone</span><input v-model="form.organizer.phone"/></label>
    <label class="field md:col-span-2"><span>Hero headline</span><input v-model="form.public_page.headline" placeholder="Optional marketing headline"/></label>
  </div>
</div>

<div v-else>
  <h2 class="text-lg font-bold">Review & create draft</h2>
  <div class="mt-6 grid gap-4 md:grid-cols-2">
    <div class="rounded-2xl bg-slate-50 p-5"><p class="panel-kicker">Event</p><h3 class="mt-2 text-xl font-black">{{form.name}}</h3><p class="mt-2 text-sm text-slate-500">{{form.start_date}} → {{form.end_date||form.start_date}} · {{form.location||'Location not set'}}</p></div>
    <div class="rounded-2xl p-5 text-white" :style="{background:`linear-gradient(135deg,${form.branding.primary_color},${form.branding.secondary_color})`}"><p class="text-xs font-black uppercase tracking-widest opacity-70">Brand preview</p><h3 class="mt-2 text-xl font-black">{{form.branding.brand_name||form.name}}</h3><p class="mt-2 text-sm opacity-80">{{form.public_page.headline||form.description||'Your event public page'}}</p></div>
    <div class="rounded-2xl border border-slate-200 p-5"><p class="panel-kicker">Registration</p><p class="mt-2 font-bold">{{form.registration.approval_mode}}</p><p class="mt-1 text-sm text-slate-500">{{categoriesText}}</p></div>
    <div class="rounded-2xl border border-slate-200 p-5"><p class="panel-kicker">Initial ticket</p><p class="mt-2 font-bold">{{form.ticket.enabled?form.ticket.name:'No ticket created'}}</p><p v-if="form.ticket.enabled" class="mt-1 text-sm text-slate-500">{{form.ticket.price===0?'Free':'₹'+form.ticket.price}} · capacity {{form.ticket.quantity}}</p></div>
  </div>
</div>

<p v-if="error" class="mt-6 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{error}}</p>
<div class="mt-8 flex justify-between border-t border-slate-100 pt-5">
  <button type="button" class="btn-secondary" :disabled="step===1||saving" @click="step--">← Back</button>
  <button class="btn-primary" :disabled="saving">{{step===5?(saving?'Creating…':'Create Event Draft'):'Next →'}}</button>
</div>
</form>
</div></template>
