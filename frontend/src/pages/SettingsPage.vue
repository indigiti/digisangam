<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
const auth=useAuthStore(),saved=ref(false),busy=ref(false)
const form=reactive({name:'',brand:'',legal_name:'',gstin:'',billing_address:'',timezone:'Asia/Kolkata',currency:'INR',country:'IN'})
onMounted(async()=>Object.assign(form,await api.workspace()))
async function save(){busy.value=true;saved.value=false;try{Object.assign(form,await api.updateWorkspace(form));saved.value=true}catch{}finally{busy.value=false}}
</script>
<template><div class="mx-auto max-w-5xl space-y-6">
<section><p class="eyebrow">Workspace</p><h1 class="page-title">Settings</h1><p class="page-subtitle">Workspace identity, invoice details, regional defaults and access context.</p></section>
<section class="grid gap-5 lg:grid-cols-[1fr_.65fr]"><form class="panel p-6" @submit.prevent="save"><h2 class="text-lg font-bold">Workspace profile</h2><div class="mt-6 grid gap-5 sm:grid-cols-2"><label class="field sm:col-span-2"><span>Workspace name</span><input v-model="form.name"/></label><label class="field sm:col-span-2"><span>Brand name</span><input v-model="form.brand"/></label><label class="field sm:col-span-2"><span>Legal / billing name</span><input v-model="form.legal_name" placeholder="Company or organizer legal name"/></label><label class="field"><span>GSTIN</span><input v-model="form.gstin" placeholder="Optional"/></label><label class="field"><span>Country</span><input v-model="form.country"/></label><label class="field sm:col-span-2"><span>Billing address</span><textarea v-model="form.billing_address" rows="3"></textarea></label><label class="field"><span>Timezone</span><input v-model="form.timezone"/></label><label class="field"><span>Currency</span><input v-model="form.currency"/></label></div><div class="mt-6 flex items-center gap-3"><button class="btn-primary" :disabled="busy">Save settings</button><span v-if="saved" class="text-xs font-bold text-emerald-600">Saved</span></div></form>
<aside class="panel p-6"><p class="panel-kicker">Signed in as</p><h2 class="mt-2 font-bold">{{auth.user?.name}}</h2><p class="mt-1 text-sm text-slate-500">{{auth.user?.email}}</p><div class="mt-4"><span class="soft-pill">{{auth.user?.role}}</span></div><div class="mt-8 rounded-xl bg-slate-50 p-4 text-xs leading-5 text-slate-500">Legal name, GSTIN and billing address are used on printable receipts when configured.</div><button class="btn-secondary mt-4 w-full" @click="auth.logout().then(()=>location.assign('/access'))">Sign out</button></aside></section>
</div></template>
