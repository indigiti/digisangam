<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import SidebarNav from '../components/SidebarNav.vue'
import Topbar from '../components/Topbar.vue'

const route = useRoute()
const auth = useAuthStore()
const offlineShell = computed(() => !auth.user && auth.offlineMode && route.name === 'onground')
const mobileNavOpen = ref(false)
watch(() => route.fullPath, () => { mobileNavOpen.value = false })
</script>

<template>
  <div class="app-bg min-h-screen text-slate-900">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <template v-if="offlineShell">
      <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
        <p class="text-sm font-extrabold tracking-tight">DigiSangam <span class="ml-2 font-medium text-slate-500">/ Secure OnGround</span></p>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">Offline · local access only</span>
      </header>
      <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[1300px] px-4 py-6 sm:px-6">
        <RouterView />
      </main>
    </template>
    <template v-else>
      <SidebarNav :open="mobileNavOpen" @close="mobileNavOpen = false" />
      <div class="min-w-0 lg:pl-64">
        <Topbar :mobile-nav-open="mobileNavOpen" @toggle-menu="mobileNavOpen = !mobileNavOpen" />
        <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[1800px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <RouterView />
        </main>
      </div>
    </template>
  </div>
</template>
