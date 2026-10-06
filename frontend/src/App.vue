<script setup>
const modules = [
  'Events',
  'Registrations',
  'Attendees',
  'Tickets',
  'Commerce',
  'Analytics',
]
</script>

<template>
  <main class="min-h-screen bg-slate-50 text-slate-950">
    <div class="mx-auto max-w-7xl p-6 lg:p-10">
      <header class="mb-8 flex items-start justify-between gap-6">
        <div>
          <p class="text-sm font-semibold text-indigo-600">DigiSangam · Phase 1</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight">EventOS Core</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">
            Plain PHP backend, Vue 3 admin, Tailwind CSS and storage abstraction ready for the no-database build.
          </p>
        </div>
        <button class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">
          Create event
        </button>
      </header>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="module in modules"
          :key="module"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Module</p>
          <h2 class="mt-2 text-lg font-semibold">{{ module }}</h2>
          <p class="mt-2 text-sm text-slate-500">Foundation scaffold ready for implementation.</p>
        </article>
      </section>
    </div>
  </main>
</template>
