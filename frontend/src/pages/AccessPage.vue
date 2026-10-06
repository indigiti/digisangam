<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const busy = ref(false)
const form = reactive({ name: '', email: '', password: '' })
const title = computed(() => auth.setupRequired ? 'Create the first administrator' : 'Sign in to DigiSangam')
const subtitle = computed(() => auth.setupRequired ? 'No default credentials are shipped. This first account becomes the platform super administrator.' : 'Use your workspace account to continue.')

async function submit() {
  busy.value = true
  try {
    if (auth.setupRequired) await auth.setup(form)
    else await auth.login(form)
    router.replace('/')
  } catch {}
  finally { busy.value = false }
}
</script>

<template>
  <main class="min-h-screen bg-slate-950 p-4 text-slate-900 sm:p-8">
    <div class="mx-auto grid min-h-[calc(100vh-2rem)] max-w-6xl overflow-hidden rounded-[28px] bg-white shadow-2xl lg:grid-cols-[1fr_.8fr]">
      <section class="hidden bg-gradient-to-br from-indigo-700 via-violet-600 to-cyan-500 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div><div class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15 font-black">DS</div><h1 class="mt-8 max-w-md text-4xl font-black tracking-tight">Operate every event from one intelligent control plane.</h1><p class="mt-4 max-w-lg text-sm leading-6 text-indigo-100">DigiSangam EventOS Core keeps events, registrations, people, tickets, commerce and analytics inside one workspace.</p></div>
        <div class="grid grid-cols-3 gap-3 text-xs"><div class="rounded-2xl bg-white/10 p-4"><b class="block text-lg">Phase 1</b>EventOS Core</div><div class="rounded-2xl bg-white/10 p-4"><b class="block text-lg">No DB</b>JSON persistence</div><div class="rounded-2xl bg-white/10 p-4"><b class="block text-lg">Secure</b>RBAC + CSRF</div></div>
      </section>
      <section class="grid place-items-center p-6 sm:p-10">
        <form class="w-full max-w-md" @submit.prevent="submit">
          <p class="eyebrow">DigiSangam access</p><h2 class="mt-2 text-3xl font-black tracking-tight">{{ title }}</h2><p class="mt-3 text-sm leading-6 text-slate-500">{{ subtitle }}</p>
          <div class="mt-8 space-y-4">
            <label v-if="auth.setupRequired" class="field"><span>Your name</span><input v-model="form.name" required autocomplete="name"/></label>
            <label class="field"><span>Email</span><input v-model="form.email" required type="email" autocomplete="email"/></label>
            <label class="field"><span>Password</span><input v-model="form.password" required type="password" :autocomplete="auth.setupRequired?'new-password':'current-password'"/></label>
          </div>
          <p v-if="auth.error" class="mt-4 rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700">{{ auth.error }}</p>
          <button class="btn-primary mt-6 w-full py-3" :disabled="busy">{{ busy ? 'Please wait…' : (auth.setupRequired ? 'Create administrator' : 'Sign in') }}</button>
          <p v-if="auth.setupRequired" class="mt-4 text-center text-xs text-slate-400">Password must contain at least 10 characters.</p>
        </form>
      </section>
    </div>
  </main>
</template>
