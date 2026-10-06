<script setup>
import { ref } from 'vue'
import { api } from '../services/api'

const props=defineProps({token:{type:String,required:true}})
const question=ref('What sessions should I attend?'),busy=ref(false),messages=ref([])
const prompts=['What sessions should I attend?','Which exhibitors should I visit?','Can I enter the event?']

async function ask(text=question.value){
  const q=String(text||'').trim()
  if(!q||busy.value)return
  messages.value.push({role:'user',text:q})
  question.value=''
  busy.value=true
  try{
    const result=await api.publicConcierge(props.token,q)
    messages.value.push({role:'assistant',text:result.answer})
  }catch(e){
    messages.value.push({role:'assistant',text:e.message})
  }finally{busy.value=false}
}
</script>

<template>
<section class="no-print mt-5 overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
  <div class="bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white">
    <p class="text-[10px] font-black uppercase tracking-[.2em] text-indigo-100">Attendee Concierge</p>
    <h2 class="mt-1 text-xl font-black">Ask DigiSangam</h2>
    <p class="mt-1 text-sm text-indigo-100">Personalized from your registration, agenda and event information.</p>
  </div>
  <div class="p-5">
    <div v-if="!messages.length" class="grid gap-2 sm:grid-cols-3">
      <button v-for="p in prompts" :key="p" class="rounded-xl border border-slate-200 p-3 text-left text-xs font-bold text-slate-600 hover:bg-slate-50" @click="ask(p)">{{p}}</button>
    </div>
    <div v-else class="mb-4 max-h-64 space-y-2 overflow-y-auto">
      <div v-for="(m,i) in messages" :key="i" class="rounded-2xl p-3 text-sm leading-6" :class="m.role==='user'?'ml-8 bg-indigo-600 text-white':'mr-6 bg-slate-50 text-slate-700'">{{m.text}}</div>
      <div v-if="busy" class="mr-10 animate-pulse rounded-2xl bg-slate-50 p-3 text-sm text-slate-400">Checking your event recommendations…</div>
    </div>
    <form class="flex gap-2" @submit.prevent="ask()"><input v-model="question" class="control" placeholder="Ask about sessions, exhibitors, access…"/><button class="btn-primary" :disabled="busy">Ask</button></form>
  </div>
</section>
</template>
