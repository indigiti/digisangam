<script setup>
import SidebarNav from '../components/SidebarNav.vue'
import Topbar from '../components/Topbar.vue'
</script>
<template><div class="app-bg min-h-screen text-slate-900"><SidebarNav/><div class="lg:pl-64"><Topbar/><main class="px-4 py-5 sm:px-6 lg:px-8 lg:py-7"><RouterView/></main></div></div></template>
