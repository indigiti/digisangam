<script setup>
import {computed,onMounted,ref} from 'vue'
import {api} from '../services/api'

const loading=ref(true),error=ref('')
const payload=ref({events:[],categories:[],locations:[]})
const query=ref(''),category=ref('All'),location=ref('All'),dateFilter=ref('All')
const dateOptions=['All','Today','Tomorrow','This weekend']

onMounted(async()=>{
  try{payload.value=await api.publicEvents()}
  catch(e){error.value=e.message}
  finally{loading.value=false}
})

const events=computed(()=>payload.value.events||[])
const categories=computed(()=>['All',...(payload.value.categories||[])])
const locations=computed(()=>['All',...(payload.value.locations||[])])
const topPicks=computed(()=>filtered.value.slice(0,8))
const remaining=computed(()=>filtered.value.slice(8))
const featured=computed(()=>filtered.value[0]||events.value[0]||null)

const categoryTiles=computed(()=>{
  const palette=[
    ['#6d28d9','#8b5cf6','✦'],['#db2777','#f43f5e','♫'],['#0891b2','#06b6d4','◉'],
    ['#ea580c','#f97316','◆'],['#2563eb','#3b82f6','▣'],['#059669','#10b981','⌁'],
  ]
  return (payload.value.categories||[]).slice(0,6).map((name,i)=>({name,from:palette[i%palette.length][0],to:palette[i%palette.length][1],icon:palette[i%palette.length][2]}))
})

const filtered=computed(()=>events.value.filter(event=>{
  const hay=[event.name,event.headline,event.category,event.type,event.location,event.venue_name].join(' ').toLowerCase()
  if(query.value&&!hay.includes(query.value.toLowerCase()))return false
  if(category.value!=='All'&&event.category!==category.value)return false
  if(location.value!=='All'&&event.location!==location.value)return false
  if(dateFilter.value!=='All'&&!dateMatches(event.start_date,dateFilter.value))return false
  return true
}))

function dateMatches(value,mode){
  if(!value)return false
  const d=new Date(value+'T00:00:00'),now=new Date(),today=new Date(now.getFullYear(),now.getMonth(),now.getDate())
  if(mode==='Today')return d.getTime()===today.getTime()
  if(mode==='Tomorrow'){const t=new Date(today);t.setDate(today.getDate()+1);return d.getTime()===t.getTime()}
  if(mode==='This weekend'){
    const sat=new Date(today);sat.setDate(today.getDate()+((6-today.getDay()+7)%7))
    const sun=new Date(sat);sun.setDate(sat.getDate()+1)
    return d>=sat&&d<=sun
  }
  return true
}
function fmtDate(value){
  if(!value)return 'Date TBA'
  return new Intl.DateTimeFormat('en-IN',{day:'numeric',month:'short'}).format(new Date(value+'T00:00:00'))
}
function fullDate(value){
  if(!value)return 'Date to be announced'
  return new Intl.DateTimeFormat('en-IN',{weekday:'short',day:'numeric',month:'short',year:'numeric'}).format(new Date(value+'T00:00:00'))
}
function money(n,c='INR'){
  if(Number(n||0)===0)return 'Free'
  return new Intl.NumberFormat('en-IN',{style:'currency',currency:c,maximumFractionDigits:0}).format(n)
}
function gradient(event){
  return 'linear-gradient(145deg,'+(event.branding?.primary_color||'#f84464')+','+(event.branding?.secondary_color||'#6d28d9')+')'
}
function reset(){query.value='';category.value='All';location.value='All';dateFilter.value='All'}
</script>

<template>
<main class="min-h-screen bg-[#f5f5f5] text-[#1f2533]">
  <header class="sticky top-0 z-50 bg-white shadow-[0_1px_0_rgba(0,0,0,.06)]">
    <div class="mx-auto flex h-[66px] max-w-[1240px] items-center gap-4 px-4 sm:px-6">
      <RouterLink to="/discover" class="flex shrink-0 items-center gap-2">
        <span class="grid h-9 w-9 place-items-center rounded-[10px] bg-[#f84464] text-[11px] font-black text-white">DS</span>
        <span class="hidden text-[19px] font-black tracking-[-.04em] sm:block">DigiSangam</span>
      </RouterLink>

      <div class="relative min-w-0 flex-1 sm:max-w-[620px]">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[17px] text-slate-400">⌕</span>
        <input v-model="query" class="h-[40px] w-full rounded-[8px] border border-[#dedfe3] bg-white pl-11 pr-4 text-[13px] outline-none transition focus:border-[#f84464]" placeholder="Search for events, conferences, workshops and more"/>
      </div>

      <div class="ml-auto hidden items-center gap-3 md:flex">
        <select v-model="location" class="max-w-[180px] cursor-pointer border-0 bg-transparent text-[12px] font-semibold text-slate-600 outline-none">
          <option v-for="place in locations" :key="place">{{place}}</option>
        </select>
        <RouterLink to="/access" class="rounded-[7px] bg-[#f84464] px-4 py-2 text-[11px] font-bold text-white">Organizer Login</RouterLink>
      </div>
    </div>

    <div class="bg-[#f8f8f9]">
      <div class="mx-auto flex h-[42px] max-w-[1240px] items-center gap-6 overflow-x-auto px-4 text-[12px] font-semibold text-[#4a4f5b] sm:px-6">
        <button class="shrink-0 hover:text-[#f84464]" @click="reset">Events</button>
        <button v-for="item in (payload.categories||[]).slice(0,6)" :key="item" class="shrink-0 hover:text-[#f84464]" :class="category===item?'text-[#f84464]':''" @click="category=item">{{item}}</button>
        <span class="ml-auto hidden shrink-0 text-[11px] text-slate-400 lg:block">List Your Event · Corporate Events · Help</span>
      </div>
    </div>
  </header>

  <div v-if="loading" class="mx-auto max-w-[1240px] px-4 py-8 sm:px-6">
    <div class="h-[260px] animate-pulse rounded-[18px] bg-slate-200"></div>
    <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"><div v-for="i in 10" :key="i" class="h-[310px] animate-pulse rounded-[14px] bg-slate-200"></div></div>
  </div>

  <section v-else-if="error" class="mx-auto max-w-lg px-5 py-24 text-center">
    <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-50 font-black text-rose-600">!</div>
    <h1 class="mt-4 text-2xl font-black">Unable to load events</h1><p class="mt-2 text-sm text-slate-500">{{error}}</p>
  </section>

  <template v-else>
    <section class="bg-[#ebebed] py-4 sm:py-5">
      <div class="mx-auto max-w-[1240px] px-3 sm:px-6">
        <RouterLink v-if="featured" :to="'/e/'+featured.id" class="group relative block min-h-[220px] overflow-hidden rounded-[16px] bg-[#2b3140] text-white shadow-sm sm:min-h-[270px]">
          <img v-if="featured.branding?.cover_url" :src="featured.branding.cover_url" :alt="featured.name" class="absolute inset-y-0 right-0 h-full w-full object-cover opacity-65 transition duration-700 group-hover:scale-[1.02] sm:w-[58%]"/>
          <div v-else class="absolute inset-y-0 right-0 w-[58%]" :style="{background:gradient(featured)}"></div>
          <div class="absolute inset-0 bg-gradient-to-r from-[#20242f] via-[#20242f]/92 to-transparent"></div>
          <div class="relative flex min-h-[220px] max-w-[690px] flex-col justify-center p-6 sm:min-h-[270px] sm:p-9">
            <div class="flex items-center gap-2"><span class="rounded-full bg-[#f84464] px-3 py-1 text-[9px] font-black uppercase tracking-[.14em]">Featured</span><span class="rounded-full bg-white/10 px-3 py-1 text-[9px] font-bold">{{featured.category}}</span></div>
            <h1 class="mt-4 max-w-[560px] text-[28px] font-black leading-[1.08] tracking-[-.035em] sm:text-[40px]">{{featured.headline||featured.name}}</h1>
            <p class="mt-3 max-w-[520px] line-clamp-2 text-[13px] leading-6 text-slate-300">{{featured.description||'Discover and register for this experience on DigiSangam.'}}</p>
            <div class="mt-5 flex flex-wrap items-center gap-3 text-[12px] font-semibold text-white/85"><span>{{fullDate(featured.start_date)}}</span><span class="text-white/35">•</span><span>{{featured.location||'Location TBA'}}</span><span class="text-white/35">•</span><b>{{money(featured.min_price,featured.currency)}} onwards</b></div>
          </div>
        </RouterLink>
      </div>
    </section>

    <section v-if="categoryTiles.length" class="mx-auto max-w-[1240px] px-4 py-8 sm:px-6">
      <div class="mb-5 flex items-end justify-between"><div><h2 class="text-[22px] font-black tracking-[-.025em]">Browse by category</h2><p class="mt-1 text-[12px] text-slate-500">Find an experience for every kind of audience.</p></div></div>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <button v-for="tile in categoryTiles" :key="tile.name" class="group relative overflow-hidden rounded-[14px] p-4 text-left text-white shadow-sm" :style="{background:'linear-gradient(135deg,'+tile.from+','+tile.to+')'}" @click="category=tile.name">
          <div class="absolute -right-5 -top-5 h-20 w-20 rounded-full bg-white/10"></div>
          <span class="text-[22px] opacity-90">{{tile.icon}}</span><p class="mt-7 text-[13px] font-black">{{tile.name}}</p><p class="mt-1 text-[10px] text-white/70">Explore events →</p>
        </button>
      </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-4 sm:px-6">
      <div class="rounded-[14px] bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
          <div class="flex flex-1 gap-2 overflow-x-auto">
            <button v-for="item in dateOptions" :key="item" class="shrink-0 rounded-[8px] border px-4 py-2 text-[11px] font-bold" :class="dateFilter===item?'border-[#f84464] bg-rose-50 text-[#f84464]':'border-slate-200 bg-white text-slate-600'" @click="dateFilter=item">{{item}}</button>
            <button v-for="item in categories.slice(0,5)" :key="'cat-'+item" class="shrink-0 rounded-[8px] border px-4 py-2 text-[11px] font-bold md:hidden" :class="category===item?'border-[#f84464] bg-rose-50 text-[#f84464]':'border-slate-200 bg-white text-slate-600'" @click="category=item">{{item}}</button>
          </div>
          <select v-model="location" class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold md:hidden"><option v-for="place in locations" :key="place">{{place}}</option></select>
          <button v-if="query||category!=='All'||location!=='All'||dateFilter!=='All'" class="text-[11px] font-bold text-[#f84464]" @click="reset">Clear all</button>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-4 py-9 sm:px-6">
      <div class="mb-5 flex items-end justify-between">
        <div><h2 class="text-[22px] font-black tracking-[-.025em]">Recommended events</h2><p class="mt-1 text-[12px] text-slate-500">{{filtered.length}} event{{filtered.length===1?'':'s'}} matching your preferences</p></div>
      </div>

      <div v-if="topPicks.length" class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-3 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 lg:grid-cols-5">
        <RouterLink v-for="event in topPicks" :key="event.id" :to="'/e/'+event.id" class="group w-[170px] shrink-0 sm:w-auto">
          <div class="relative aspect-[2/3] overflow-hidden rounded-[12px] bg-[#e7e7e9] shadow-sm">
            <img v-if="event.branding?.cover_url" :src="event.branding.cover_url" :alt="event.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"/>
            <div v-else class="h-full w-full" :style="{background:gradient(event)}"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 flex items-center justify-between p-3 text-white"><span class="rounded bg-black/45 px-2 py-1 text-[9px] font-bold backdrop-blur">{{event.category}}</span><span class="text-[10px] font-bold">{{fmtDate(event.start_date)}}</span></div>
          </div>
          <div class="pt-3">
            <h3 class="line-clamp-2 text-[14px] font-black leading-[1.35] tracking-[-.01em] text-[#222733] group-hover:text-[#f84464]">{{event.name}}</h3>
            <p class="mt-1.5 truncate text-[11px] font-medium text-slate-500">{{event.location||'Location TBA'}}</p>
            <p class="mt-1 text-[11px] text-slate-400">{{event.type}} · <b class="font-semibold text-slate-600">{{money(event.min_price,event.currency)}} onwards</b></p>
          </div>
        </RouterLink>
      </div>

      <div v-else class="rounded-[16px] bg-white px-6 py-14 text-center shadow-sm"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100">⌕</div><h3 class="mt-4 text-lg font-black">No matching events</h3><p class="mt-2 text-sm text-slate-500">Try changing your search or filters.</p><button class="mt-5 rounded-lg bg-[#f84464] px-5 py-2.5 text-xs font-bold text-white" @click="reset">Show all events</button></div>
    </section>

    <section v-if="remaining.length" class="bg-white py-9">
      <div class="mx-auto max-w-[1240px] px-4 sm:px-6">
        <div class="mb-5"><h2 class="text-[22px] font-black tracking-[-.025em]">More experiences</h2><p class="mt-1 text-[12px] text-slate-500">More events available on DigiSangam.</p></div>
        <div class="grid gap-x-4 gap-y-7 sm:grid-cols-3 lg:grid-cols-5">
          <RouterLink v-for="event in remaining" :key="event.id" :to="'/e/'+event.id" class="group">
            <div class="relative aspect-[2/3] overflow-hidden rounded-[12px] bg-slate-100"><img v-if="event.branding?.cover_url" :src="event.branding.cover_url" class="h-full w-full object-cover"/><div v-else class="h-full" :style="{background:gradient(event)}"></div></div>
            <h3 class="mt-3 line-clamp-2 text-[14px] font-black leading-[1.35] group-hover:text-[#f84464]">{{event.name}}</h3><p class="mt-1 text-[11px] text-slate-500">{{fullDate(event.start_date)}}</p><p class="mt-1 truncate text-[11px] text-slate-400">{{event.location||'Location TBA'}}</p>
          </RouterLink>
        </div>
      </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-4 py-10 sm:px-6">
      <div class="grid overflow-hidden rounded-[18px] bg-[#2b3140] text-white lg:grid-cols-[1fr_auto] lg:items-center">
        <div class="p-7 sm:p-9"><p class="text-[10px] font-black uppercase tracking-[.18em] text-rose-300">For organizers</p><h2 class="mt-2 text-[25px] font-black tracking-tight">Your event. Your audience. One operating system.</h2><p class="mt-3 max-w-2xl text-[13px] leading-6 text-slate-300">Registration, ticketing, communications, access control, analytics and intelligence—all managed in DigiSangam EventOS.</p></div>
        <div class="px-7 pb-7 lg:px-9 lg:pb-0"><RouterLink to="/access" class="inline-flex rounded-[9px] bg-[#f84464] px-5 py-3 text-[12px] font-black">Organizer access →</RouterLink></div>
      </div>
    </section>

    <footer class="bg-[#313640] text-slate-400">
      <div class="mx-auto max-w-[1240px] px-4 py-8 sm:px-6">
        <div class="flex flex-col gap-5 border-b border-white/10 pb-6 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-[#f84464] text-[10px] font-black text-white">DS</span><b class="text-[13px] text-white">DigiSangam EventOS</b></div><div class="flex flex-wrap gap-5 text-[11px]"><RouterLink to="/discover">Explore Events</RouterLink><RouterLink to="/access">Organizer Access</RouterLink><span>Help & Support</span></div></div>
        <p class="pt-5 text-[10px] leading-5 text-slate-500">Discover, register and attend events securely with DigiSangam.</p>
      </div>
    </footer>
  </template>
</main>
</template>
