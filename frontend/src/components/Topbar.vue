<script setup>
import {computed,onMounted} from 'vue'
import {useAuthStore} from '../stores/auth'
import {useEventStore} from '../stores/event'
const auth=useAuthStore(),eventStore=useEventStore()
const initials=computed(()=>String(auth.user?.name||'DS').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase())
onMounted(()=>{if(!eventStore.loaded)eventStore.load().catch(()=>{})})
</script>
<template><header class="sticky top-0 z-20 flex h-20 items-center border-b border-slate-200/70 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8">
  <div class="flex min-w-0 flex-1 items-center gap-3">
    <select v-if="eventStore.events.length" :value="eventStore.currentId" @change="eventStore.select($event.target.value);location.reload()" class="control max-w-xs font-semibold">
      <option v-for="event in eventStore.events" :key="event.id" :value="event.id">{{event.name}}</option>
    </select>
    <RouterLink v-else to="/events/create" class="btn-primary">+ Create first event</RouterLink>
    <span v-if="eventStore.current" class="hidden text-xs text-slate-400 md:inline">{{eventStore.current.status}} · {{eventStore.current.start_date||'Date not set'}}</span>
  </div>
  <div class="ml-4 flex items-center gap-2"><RouterLink to="/intelligence" class="icon-btn">✦</RouterLink><RouterLink to="/settings" class="icon-btn">⚙</RouterLink><div class="ml-1 grid h-9 w-9 place-items-center rounded-full bg-slate-900 text-xs font-bold text-white">{{initials}}</div></div>
</header></template>
