<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import SidebarNav from '../components/SidebarNav.vue'
import Topbar from '../components/Topbar.vue'

const route = useRoute()
const mobileNavOpen = ref(false)
watch(() => route.fullPath, () => { mobileNavOpen.value = false })
</script>

<template>
  <div class="app-bg min-h-screen text-slate-900">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <SidebarNav :open="mobileNavOpen" @close="mobileNavOpen = false" />
    <div class="min-w-0 lg:pl-64">
      <Topbar :mobile-nav-open="mobileNavOpen" @toggle-menu="mobileNavOpen = !mobileNavOpen" />
      <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[1800px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
