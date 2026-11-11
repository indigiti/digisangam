<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useEventStore } from '../stores/event'
import { useThemeStore } from '../stores/theme'

defineProps({ mobileNavOpen: { type: Boolean, default: false } })
const emit = defineEmits(['toggle-menu'])
const route = useRoute()
const auth = useAuthStore()
const events = useEventStore()
const theme = useThemeStore()
const initials = computed(() => String(auth.user?.name || 'DS').trim().split(/\s+/).slice(0, 2).map(x => x[0]).join('').toUpperCase())
const title = computed(() => {
  const names = {
    dashboard: 'Overview', events: 'Events', 'event-create': 'Create Event', 'event-detail': 'Event Details',
    registrations: 'Registrations', attendees: 'Attendees', tickets: 'Tickets', communication: 'Communications',
    automation: 'Automation', badges: 'Badge Studio', agenda: 'Agenda & Sessions', exhibitors: 'Exhibitors',
    venue: 'Venue & Seating', onground: 'OnGround', 'entry-monitor': 'Entry Monitor',
    accreditation: 'Accreditation', commerce: 'Commerce', analytics: 'Analytics',
    reports: 'Reports', intelligence: 'Intelligence', 'event-builder': 'AI Event Builder',
    developer: 'Developers', settings: 'Settings',
  }
  return names[route.name] || 'Workspace'
})
onMounted(() => { if (!events.loaded) events.load().catch(() => {}) })
</script>

<template>
  <header class="app-topbar sticky top-0 z-30 flex h-20 items-center gap-4 border-b border-slate-200/70 px-4 sm:px-6 lg:px-8">
    <button
      type="button"
      class="icon-btn shrink-0 lg:hidden"
      :aria-expanded="mobileNavOpen"
      aria-controls="app-navigation"
      aria-label="Toggle navigation menu"
      @click="emit('toggle-menu')"
    >
      <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16 M4 12h16 M4 17h16"/></svg>
    </button>
    <div class="hidden shrink-0 md:block">
      <p class="text-[10px] font-bold uppercase tracking-[.15em] text-slate-400">Workspace / {{ title }}</p>
      <p class="mt-1 text-sm font-extrabold tracking-tight text-slate-900">{{ title }}</p>
    </div>
    <div class="min-w-0 flex-1 md:pl-4 lg:pl-6">
      <label v-if="events.events.length" class="relative block max-w-[390px]">
        <span class="sr-only">Active event</span>
        <select :value="events.currentId" class="event-switcher w-full appearance-none truncate rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-9 text-xs font-bold text-slate-800 outline-none focus-visible:ring-4 focus-visible:ring-indigo-100 sm:text-sm" @change="events.select($event.target.value)">
          <option v-for="event in events.events" :key="event.id" :value="event.id">{{ event.name }}</option>
        </select>
        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </label>
      <RouterLink v-else to="/events/create" class="btn-primary text-nowrap">+ New Event</RouterLink>
    </div>
    <nav class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2" aria-label="Quick actions">
      <button type="button" class="icon-btn" :aria-label="theme.isDark?'Switch to light mode':'Switch to dark mode'" :title="theme.isDark?'Light appearance':'Dark appearance'" @click="theme.toggle()">
        <svg v-if="theme.isDark" viewBox="0 0 24 24" class="h-[19px] w-[19px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2 M12 20v2 M2 12h2 M20 12h2 M4.93 4.93l1.42 1.42 M17.65 17.65l1.42 1.42 M4.93 19.07l1.42-1.42 M17.65 6.35l1.42-1.42"/></svg>
        <svg v-else viewBox="0 0 24 24" class="h-[19px] w-[19px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M20.9 13.2A8.8 8.8 0 0 1 10.8 3.1 9 9 0 1 0 20.9 13.2Z"/></svg>
      </button>
      <RouterLink to="/discover" class="ds-admin-public-link hidden xl:inline-flex" aria-label="Open public marketplace">View marketplace <span aria-hidden="true">↗</span></RouterLink>
      <RouterLink to="/intelligence" class="icon-btn" aria-label="Intelligence" title="Intelligence">
        <svg viewBox="0 0 24 24" class="h-[19px] w-[19px]" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 2 2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4z"/></svg>
      </RouterLink>
      <RouterLink to="/settings" class="icon-btn" aria-label="Settings" title="Settings">
        <svg viewBox="0 0 24 24" class="h-[19px] w-[19px]" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3 M12 19v3 M2 12h3 M19 12h3 M5 5l2 2 M17 17l2 2 M19 5l-2 2 M7 17l-2 2"/></svg>
      </RouterLink>
      <div class="ml-1 grid h-9 w-9 place-items-center rounded-xl bg-slate-900 text-[11px] font-bold text-white sm:ml-2">{{ initials }}</div>
    </nav>
  </header>
</template>
