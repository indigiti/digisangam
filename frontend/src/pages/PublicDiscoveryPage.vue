<script setup>
import {computed,onMounted,ref} from 'vue'
import {api} from '../services/api'

const loading=ref(true),error=ref(''),payload=ref({events:[],categories:[],locations:[]})
const query=ref(''),category=ref('All'),location=ref('All'),dateFilter=ref('All')
const dateOptions=['All','Today','This weekend','Upcoming']

onMounted(async()=>{
  try{payload.value=await api.publicEvents()}
  catch(e){error.value=e.message}
  finally{loading.value=false}
})

const featured=computed(()=>payload.value.events?.[0]||null)
const categories=computed(()=>['All',...(payload.value.categories||[])])
const locations=computed(()=>['All',...(payload.value.locations||[])])
const filtered=computed(()=>payload.value.events.filter(event=>{
  const hay=[event.name,event.headline,event.category,event.type,event.location,event.venue_name].join(' ').toLowerCase()
  if(query.value&&!hay.includes(query.value.toLowerCase())) return false
  if(category.value!=='All'&&event.category!==category.value) return false
  if(location.value!=='All'&&event.location!==location.value) return false
  if(dateFilter.value!=='All'&&!dateMatches(event.start_date,dateFilter.value)) return false
  return true
}))

function dateMatches(value,mode){
  if(!value)return mode==='Upcoming'
  const d=new Date(value+'T00:00:00'),now=new Date()
  const today=new Date(now.getFullYear(),now.getMonth(),now.getDate())
  if(mode==='Today')return d.getTime()===today.getTime()
  if(mode==='Upcoming')return d>=today
  if(mode==='This weekend'){
    const day=today.getDay(),sat=new Date(today),sun=new Date(today)
    sat.setDate(today.getDate()+((6-day+7)%7));sun.setTime(sat.getTime());sun.setDate(sat.getDate()+1)
    return d>=sat&&d<=sun
  }
  return true
}
function formatDate(value){
  if(!value)return 'Date to be announced'
  return new Intl.DateTimeFormat('en-IN',{day:'numeric',month:'short',year:'numeric'}).format(new Date(value+'T00:00:00'))
}
function day(value){return value?new Intl.DateTimeFormat('en-IN',{weekday:'short'}).format(new Date(value+'T00:00:00')):'TBA'}
function money(n,currency='INR'){
  if(Number(n||0)===0)return 'Free'
  return new Intl.NumberFormat('en-IN',{style:'currency',currency,maximumFractionDigits:0}).format(n)
}
function reset(){query.value='';category.value='All';location.value='All';dateFilter.value='All'}
function gradient(event){
  return 'linear-gradient(135deg,'+(event.branding?.primary_color||'#ef4444')+','+(event.branding?.secondary_color||'#7c3aed')+')'
}
</script>

<template>
<main class="min-h-screen bg-[#f5f5f7] text-[#202124]">
  <header class="sticky top-0 z-50 border-b border-black/5 bg-white/95 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-[1280px] items-center gap-4 px-4 sm:px-6">
      <RouterLink to="/discover" class="flex shrink-0 items-center gap-2.5">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-[#f84464] text-xs font-black text-white shadow-sm">DS</span>
        <span class="hidden text-lg font-black tracking-tight sm:block">DigiSangam</span>
      </RouterLink>
      <div class="relative min-w-0 flex-1">
        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
        <input v-model="query" class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 text-sm outline-none transition focus:border-[#f84464] focus:ring-4 focus:ring-rose-50" placeholder="Search for events, conferences, workshops and more"/>
      </div>
      <select v-model="location" class="hidden max-w-48 rounded-lg border-0 bg-transparent px-2 py-2 text-sm font-semibold text-slate-600 outline-none md:block">
        <option v-for="place in locations" :key="place">{{place}}</option>
      </select>
      <RouterLink to="/access" class="rounded-lg bg-[#f84464] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#e53655]">Organizer</RouterLink>
    </div>
    <div class="border-t border-black/[.035] bg-[#fafafa]">
      <div class="mx-auto flex max-w-[1280px] items-center gap-5 overflow-x-auto px-4 py-2.5 text-xs font-bold text-slate-600 sm:px-6">
        <button v-for="item in categories.slice(0,8)" :key="item" class="shrink-0 transition hover:text-[#f84464]" :class="category===item?'text-[#f84464]':'text-slate-600'" @click="category=item">{{item==='All'?'Events':item}}</button>
        <span class="ml-auto hidden shrink-0 text-slate-400 lg:block">Create · Register · Attend · Engage</span>
      </div>
    </div>
  </header>

  <div v-if="loading" class="mx-auto max-w-[1280px] px-4 py-12 sm:px-6">
    <div class="h-72 animate-pulse rounded-[28px] bg-slate-200"></div>
    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4"><div v-for="i in 8" :key="i" class="h-80 animate-pulse rounded-2xl bg-slate-200"></div></div>
  </div>

  <section v-else-if="error" class="mx-auto max-w-xl px-5 py-24 text-center">
    <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-100 font-black text-rose-600">!</div>
    <h1 class="mt-5 text-2xl font-black">Unable to load events</h1><p class="mt-2 text-sm text-slate-500">{{error}}</p>
  </section>

  <template v-else>
    <section v-if="featured" class="mx-auto max-w-[1320px] px-3 pt-5 sm:px-5">
      <RouterLink :to="'/e/'+featured.id" class="group relative block min-h-[330px] overflow-hidden rounded-[26px] text-white shadow-[0_24px_70px_rgba(15,23,42,.18)] sm:min-h-[390px]">
        <img v-if="featured.branding?.cover_url" :src="featured.branding.cover_url" :alt="featured.name" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-[1.025]"/>
        <div v-else class="absolute inset-0" :style="{background:gradient(featured)}"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/10"></div>
        <div class="absolute inset-x-0 bottom-0 top-0 flex max-w-2xl flex-col justify-end p-6 sm:p-10 lg:p-12">
          <div class="mb-auto flex items-center gap-2"><span class="rounded-full bg-[#f84464] px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em]">Featured</span><span class="rounded-full bg-white/15 px-3 py-1.5 text-[10px] font-bold backdrop-blur">{{featured.type}}</span></div>
          <p class="text-xs font-bold uppercase tracking-[.2em] text-white/65">{{featured.category}}</p>
          <h1 class="mt-3 text-3xl font-black leading-tight tracking-tight sm:text-5xl">{{featured.headline||featured.name}}</h1>
          <p class="mt-4 line-clamp-2 max-w-xl text-sm leading-6 text-white/75 sm:text-base">{{featured.description||'Discover and register for this experience on DigiSangam.'}}</p>
          <div class="mt-6 flex flex-wrap items-center gap-3 text-sm font-bold"><span>{{formatDate(featured.start_date)}}</span><span class="text-white/35">•</span><span>{{featured.venue_name||featured.location||'Venue TBA'}}</span><span class="text-white/35">•</span><span>{{money(featured.min_price,featured.currency)}} onwards</span></div>
          <div class="mt-6"><span class="inline-flex rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-950 transition group-hover:bg-[#f84464] group-hover:text-white">View event →</span></div>
        </div>
      </RouterLink>
    </section>

    <section class="mx-auto max-w-[1280px] px-4 py-8 sm:px-6">
      <div class="flex gap-2 overflow-x-auto pb-2">
        <button v-for="item in categories" :key="item" @click="category=item" class="shrink-0 rounded-full border px-4 py-2 text-xs font-bold transition" :class="category===item?'border-[#f84464] bg-[#f84464] text-white':'border-slate-200 bg-white text-slate-600 hover:border-rose-200'">{{item}}</button>
      </div>
    </section>

    <section class="mx-auto max-w-[1280px] px-4 pb-8 sm:px-6">
      <div class="flex flex-col gap-4 rounded-2xl bg-white p-4 shadow-sm sm:flex-row sm:items-center">
        <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto">
          <button v-for="item in dateOptions" :key="item" @click="dateFilter=item" class="shrink-0 rounded-xl px-4 py-2 text-xs font-bold" :class="dateFilter===item?'bg-slate-900 text-white':'bg-slate-50 text-slate-600'">{{item}}</button>
        </div>
        <select v-model="location" class="control sm:max-w-56 md:hidden"><option v-for="place in locations" :key="place">{{place}}</option></select>
        <button v-if="query||category!=='All'||location!=='All'||dateFilter!=='All'" class="text-xs font-bold text-[#f84464]" @click="reset">Clear filters</button>
      </div>
    </section>

    <section class="mx-auto max-w-[1280px] px-4 pb-14 sm:px-6">
      <div class="mb-6 flex items-end justify-between gap-4">
        <div><p class="text-xs font-black uppercase tracking-[.16em] text-[#f84464]">Discover</p><h2 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Events you’ll love</h2></div>
        <p class="text-xs font-semibold text-slate-400">{{filtered.length}} event{{filtered.length===1?'':'s'}}</p>
      </div>

      <div v-if="filtered.length" class="grid gap-x-5 gap-y-9 sm:grid-cols-2 lg:grid-cols-4">
        <RouterLink v-for="event in filtered" :key="event.id" :to="'/e/'+event.id" class="group min-w-0">
          <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-slate-900 shadow-sm">
            <img v-if="event.branding?.cover_url" :src="event.branding.cover_url" :alt="event.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]"/>
            <div v-else class="h-full w-full" :style="{background:gradient(event)}"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-transparent to-black/5"></div>
            <div class="absolute left-3 top-3 rounded-lg bg-white/95 px-2.5 py-2 text-center shadow">
              <p class="text-[9px] font-black uppercase text-[#f84464]">{{day(event.start_date)}}</p>
              <p class="mt-.5 text-sm font-black text-slate-900">{{event.start_date?new Date(event.start_date+'T00:00:00').getDate():'—'}}</p>
            </div>
            <span v-if="event.status==='Live'" class="absolute right-3 top-3 rounded-full bg-rose-500 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-white">Live</span>
            <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
              <span class="rounded-md bg-black/35 px-2 py-1 text-[9px] font-bold backdrop-blur">{{event.category}}</span>
              <p class="mt-2 line-clamp-2 text-xs font-bold text-white/80">{{event.available}} spots available</p>
            </div>
          </div>
          <div class="px-1 pt-3">
            <h3 class="line-clamp-2 text-[15px] font-black leading-5 tracking-tight text-slate-900 transition group-hover:text-[#f84464]">{{event.name}}</h3>
            <p class="mt-1.5 truncate text-xs font-semibold text-slate-500">{{formatDate(event.start_date)}} · {{event.location||'Location TBA'}}</p>
            <p class="mt-1 text-xs text-slate-400">{{event.type}} · <b class="text-slate-600">{{money(event.min_price,event.currency)}} onwards</b></p>
          </div>
        </RouterLink>
      </div>

      <div v-else class="rounded-[28px] border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-xl">⌕</div>
        <h3 class="mt-4 text-xl font-black">No events match these filters</h3>
        <p class="mt-2 text-sm text-slate-500">Try another category, location or search term.</p>
        <button class="mt-5 rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-bold text-white" @click="reset">Show all events</button>
      </div>
    </section>

    <section class="bg-[#2b3140] text-white">
      <div class="mx-auto grid max-w-[1280px] gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_auto] lg:items-center">
        <div><p class="text-xs font-black uppercase tracking-[.18em] text-rose-300">For organizers</p><h2 class="mt-2 text-2xl font-black">Create your next event on DigiSangam</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Registration, ticketing, communications, QR/NFC/RFID access, analytics and EventOS intelligence in one workspace.</p></div>
        <RouterLink to="/access" class="rounded-xl bg-[#f84464] px-5 py-3 text-center text-sm font-black text-white">Organizer login →</RouterLink>
      </div>
    </section>

    <footer class="bg-[#20242f] text-slate-400">
      <div class="mx-auto flex max-w-[1280px] flex-col gap-5 px-4 py-8 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-[#f84464] font-black text-white">DS</span><b class="text-white">DigiSangam EventOS</b></div>
        <div class="flex flex-wrap gap-4"><RouterLink to="/discover">Explore Events</RouterLink><RouterLink to="/access">Organizer Access</RouterLink><span>Secure registration & credentials</span></div>
      </div>
    </footer>
  </template>
</main>
</template>
