<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'
const auth=useAuthStore()
const initials=computed(()=>String(auth.user?.name||'DS').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase())
const groups=[
  {label:'Core',items:[['/','Dashboard','⌂'],['/events','Events','◇'],['/registrations','Registrations','▣'],['/tickets','Tickets','▤'],['/attendees','Attendees','♙']]},
  {label:'Operations',items:[['/communication','Communication','✉'],['/automation','Automation','⌁'],['/badges','Badges','▦'],['/agenda','Agenda','◷'],['/exhibitors','Exhibitors','▥'],['/venue','Venue','⌖'],['/onground','OnGround','◎'],['/entry-monitor','Entry Monitor','↔'],['/accreditation','Accreditation','◉']]},
  {label:'Business',items:[['/commerce','Commerce','¤'],['/analytics','Analytics','⌁'],['/reports','Reports','▥'],['/intelligence','Intelligence','✦'],['/event-builder','AI Builder','✣'],['/developer','Developers','⌘'],['/settings','Settings','⚙']]},
]
</script>
<template><aside class="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-slate-200/80 bg-white/95 backdrop-blur lg:flex lg:flex-col"><div class="flex h-20 items-center gap-3 px-6"><div class="grid h-9 w-9 place-items-center rounded-xl bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-200">DS</div><div><p class="font-bold tracking-tight">DigiSangam</p><p class="text-[11px] font-medium text-slate-400">EVENT OPERATING SYSTEM</p></div></div><nav class="flex-1 overflow-y-auto px-3 py-3"><div v-for="group in groups" :key="group.label" class="mb-4"><p class="px-3 pb-1 text-[9px] font-black uppercase tracking-[.16em] text-slate-300">{{group.label}}</p><div class="space-y-1"><RouterLink v-for="[to,label,icon] in group.items" :key="to" :to="to" class="nav-item"><span class="w-5 text-center text-base">{{icon}}</span><span>{{label}}</span></RouterLink></div></div></nav><div class="m-3 rounded-2xl border border-slate-200 bg-slate-50 p-3"><div class="flex items-center gap-3"><div class="grid h-9 w-9 place-items-center rounded-full bg-amber-100 text-sm font-bold text-amber-700">{{initials}}</div><div class="min-w-0"><p class="truncate text-sm font-semibold">{{auth.user?.name}}</p><p class="truncate text-xs text-slate-400">{{auth.user?.role}}</p></div></div></div></aside></template>
