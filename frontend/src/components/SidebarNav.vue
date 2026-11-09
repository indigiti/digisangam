<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const props = defineProps({ open: { type: Boolean, default: false } })
const drawer = ref(null)
const isWide = ref(false)
const emit = defineEmits(['close'])
const auth = useAuthStore()
const route = useRoute()
const filter = ref('')
const initials = computed(() => String(auth.user?.name || 'DS').trim().split(/\s+/).slice(0, 2).map(x => x[0]).join('').toUpperCase())
const groups = [
  { label: 'WORKSPACE', items: [
    ['/', 'Overview', 'dashboard'], ['/events', 'Events', 'calendar'],
    ['/registrations', 'Registrations', 'form'], ['/tickets', 'Tickets', 'ticket'],
    ['/attendees', 'Attendees', 'users'],
  ] },
  { label: 'EVENT OPERATIONS', items: [
    ['/communication', 'Communications', 'message'], ['/automation', 'Automation', 'workflow'],
    ['/badges', 'Badge Studio', 'badge'], ['/agenda', 'Agenda & Sessions', 'clock'],
    ['/exhibitors', 'Exhibitors', 'building'], ['/venue', 'Venue & Seating', 'map'],
    ['/onground', 'OnGround', 'scan'], ['/entry-monitor', 'Entry Monitor', 'activity'],
    ['/accreditation', 'Accreditation', 'shield'],
  ] },
  { label: 'GROWTH & INSIGHTS', items: [
    ['/commerce', 'Commerce', 'wallet'], ['/analytics', 'Analytics', 'chart'],
    ['/reports', 'Reports', 'report'], ['/intelligence', 'Intelligence', 'sparkles'],
    ['/event-builder', 'AI Event Builder', 'wand'], ['/developer', 'Developers', 'code'],
    ['/settings', 'Settings', 'settings'],
  ] },
]
const filteredGroups = computed(() => {
  const term = filter.value.trim().toLowerCase()
  if (!term) return groups
  return groups.map(group => ({
    ...group,
    items: group.items.filter(([, name]) => name.toLowerCase().includes(term)),
  })).filter(group => group.items.length)
})
const paths = {
  dashboard: 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
  calendar: 'M7 2v4 M17 2v4 M3 9h18 M4 5h16v16H4z M8 13h3 M8 17h7',
  form: 'M5 3h14v18H5z M9 3v4 M9 12h6 M9 16h6',
  ticket: 'M3 8V5h18v3a3 3 0 0 0 0 6v5H3v-5a3 3 0 0 0 0-6z M13 5v14',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8a4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
  message: 'M3 4h18v13H8l-5 4z M7 9h10 M7 13h7',
  workflow: 'M5 5h5v5H5z M14 14h5v5h-5z M10 7h6a3 3 0 0 1 3 3v4 M7 10v7h7',
  badge: 'M5 3h14v18l-7-4-7 4z M9 9h6 M9 13h6',
  clock: 'M12 22a10 10 0 1 0 0-20a10 10 0 0 0 0 20 M12 6v6l4 2',
  building: 'M3 21V7l9-4 9 4v14z M9 21v-6h6v6 M7 10h2 M15 10h2',
  map: 'M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z M9 3v15 M15 6v15',
  scan: 'M8 3H5a2 2 0 0 0-2 2v3 M16 3h3a2 2 0 0 1 2 2v3 M3 16v3a2 2 0 0 0 2 2h3 M21 16v3a2 2 0 0 1-2 2h-3 M4 12h16',
  activity: 'M2 12h4l3-7 5 14 3-7h5',
  shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z M9 12l2 2 4-4',
  wallet: 'M3 6h16a2 2 0 0 1 2 2v12H3z M3 6V4h16 M16 13h5 M17 13h1',
  chart: 'M3 3v18h18 M7 16l4-5 4 2 5-7',
  report: 'M6 2h9l5 5v15H6z M14 2v6h6 M9 12h8 M9 16h8',
  sparkles: 'M12 2l2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4z',
  wand: 'M4 20L20 4 M14 3v3 M18 8h3 M4 4l2 2 M3 13h3 M19 17l2 2',
  code: 'M8 8l-4 4 4 4 M16 8l4 4-4 4 M14 4l-4 16',
  settings: 'M12 15.5a3.5 3.5 0 1 0 0-7a3.5 3.5 0 0 0 0 7 M12 2v2 M12 20v2 M4 12H2 M22 12h-2 M5 5l2 2 M19 19l-2-2 M19 5l-2 2 M5 19l2-2',
}
watch(() => route.fullPath, () => emit('close'))
watch(() => props.open, async isOpen => {
  if (typeof document === 'undefined') return
  document.body.classList.toggle('nav-drawer-open', isOpen && !isWide.value)
  if (isOpen) {
    await nextTick()
    drawer.value?.querySelector('[aria-label="Close navigation"]')?.focus()
  }
})
function handleResize() {
  isWide.value = window.matchMedia('(min-width: 1024px)').matches
  document.body.classList.toggle('nav-drawer-open', props.open && !isWide.value)
}
function handleGlobalKey(event) {
  if (event.key === 'Escape' && props.open) emit('close')
  if (event.key !== '/' || !(props.open || isWide.value)) return
  const tag = event.target?.tagName?.toLowerCase()
  if (['input','textarea','select'].includes(tag) || event.ctrlKey || event.metaKey || event.altKey) return
  event.preventDefault()
  drawer.value?.querySelector('[aria-label="Filter navigation"]')?.focus()
}
function trapFocus(event) {
  if (!props.open || isWide.value || event.key !== 'Tab') return
  const focusable = [...(drawer.value?.querySelectorAll('a[href],button:not([disabled]),input:not([disabled])') || [])]
  if (!focusable.length) return
  const first = focusable[0], last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
onMounted(() => {
  handleResize()
  window.addEventListener('resize', handleResize)
  window.addEventListener('keydown', handleGlobalKey)
})
onBeforeUnmount(() => {
  document.body.classList.remove('nav-drawer-open')
  window.removeEventListener('resize', handleResize)
  window.removeEventListener('keydown', handleGlobalKey)
})
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-40 bg-[#060b19]/65 backdrop-blur-[2px] lg:hidden" @click="emit('close')" aria-hidden="true"></div>
  <aside
    ref="drawer"
    :inert="!open && !isWide"
    :aria-hidden="!open && !isWide"
    @keydown="trapFocus"
    id="app-navigation"
    class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col text-white transition-transform duration-200 ease-out lg:translate-x-0"
    :class="{ 'translate-x-0': open }"
    aria-label="Main navigation"
  >
    <div class="flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-5">
      <div class="brand-mark grid h-10 w-10 shrink-0 place-items-center rounded-2xl text-xs font-black tracking-tight">DS</div>
      <div class="min-w-0 flex-1">
        <p class="text-[15px] font-extrabold tracking-[-.035em]">DigiSangam</p>
        <p class="mt-0.5 text-[9px] font-semibold uppercase tracking-[.13em] text-indigo-200/65">Event operating system</p>
      </div>
      <button type="button" class="rounded-lg p-1.5 text-white/70 hover:bg-white/10 lg:hidden" aria-label="Close navigation" @click="emit('close')">✕</button>
    </div>
    <div class="px-4 pb-2 pt-5">
      <label class="nav-search flex items-center gap-2 rounded-xl px-3 py-2.5">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
        <span class="sr-only">Filter navigation</span>
        <input v-model="filter" type="search" placeholder="Find a module…" aria-label="Filter navigation" class="min-w-0 w-full bg-transparent text-xs text-white outline-none placeholder:text-white/45"/>
        <kbd class="rounded border border-white/10 px-1 text-[10px] text-white/40">/</kbd>
      </label>
    </div>
    <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-5 pt-2" aria-label="Application modules">
      <section v-for="group in filteredGroups" :key="group.label" class="mt-5 first:mt-2">
        <h2 class="px-3 pb-2 text-[10px] font-bold tracking-[.14em] text-indigo-200/45">{{ group.label }}</h2>
        <div class="space-y-0.5">
          <RouterLink v-for="[to, label, icon] in group.items" :key="to" :to="to" class="nav-item nav-item-dark" @click="emit('close')">
            <svg class="h-[17px] w-[17px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[icon]"/></svg>
            <span class="truncate">{{ label }}</span>
          </RouterLink>
        </div>
      </section>
      <p v-if="!filteredGroups.length" class="px-3 py-4 text-xs text-indigo-100/60">No matching modules.</p>
    </nav>
    <div class="m-3 flex shrink-0 items-center gap-3 rounded-2xl border border-white/10 bg-white/[.055] p-3">
      <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-400/25 text-xs font-extrabold text-indigo-100">{{ initials }}</div>
      <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-semibold">{{ auth.user?.name || 'Administrator' }}</p>
        <p class="mt-1 truncate text-[10px] font-medium text-indigo-200/55">{{ auth.user?.role || 'Workspace' }}</p>
      </div>
    </div>
  </aside>
</template>
