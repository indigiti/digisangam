<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'

const eventId='evt_001'
const palette=[
  ['Text Field','text'],['Email','email'],['Phone','phone'],['Dropdown','select'],['Multi Select','multiselect'],
  ['Radio Button','radio'],['Date Picker','date'],['File Upload','file'],['Paragraph','paragraph']
]
const schema=reactive({title:'Event Registration',approval_mode:'auto',categories:[],fields:[]})
const selected=ref(0),saving=ref(false),saved=ref(false),inviteEmail=ref(''),invites=ref([])

onMounted(async()=>{
  Object.assign(schema,await api.registration(eventId))
  invites.value=await api.invitations()
})

function add(label,type){
  schema.fields.push({id:'fld_'+Date.now(),label,type,required:false,visibility:'always'})
  selected.value=schema.fields.length-1
}
function remove(){
  if(schema.fields.length){schema.fields.splice(selected.value,1);selected.value=Math.max(0,selected.value-1)}
}
async function save(){
  saving.value=true;saved.value=false
  try{Object.assign(schema,await api.saveRegistration(eventId,schema));saved.value=true}
  finally{saving.value=false}
}
async function invite(){
  if(!inviteEmail.value)return
  const row=await api.createInvitation({email:inviteEmail.value,category:'General'})
  invites.value.unshift(row);inviteEmail.value=''
}
</script>
<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="eyebrow">Registration</p><h1 class="page-title">Registration Form Builder</h1><p class="page-subtitle">Persistent schema, categories, approval mode and invite-only access.</p></div><div class="flex items-center gap-2"><span v-if="saved" class="text-xs font-bold text-emerald-600">Saved</span><button class="btn-secondary">Preview</button><button class="btn-primary" :disabled="saving" @click="save">{{saving?'Saving…':'Save Form'}}</button></div></section>
<section class="grid gap-4 xl:grid-cols-[220px_1fr_300px]">
<aside class="space-y-4">
  <div class="panel p-4"><p class="panel-kicker">Add field</p><div class="mt-3 space-y-2"><button v-for="[label,type] in palette" :key="type" @click="add(label,type)" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-left text-xs font-semibold hover:border-indigo-300 hover:bg-indigo-50">{{label}}</button></div></div>
  <div class="panel p-4"><p class="panel-kicker">Invitations</p><p class="mt-2 text-xs text-slate-500">{{invites.length}} private invitations created.</p><div class="mt-3 flex gap-2"><input v-model="inviteEmail" class="control min-w-0" type="email" placeholder="email@example.com"/><button class="btn-primary px-3" @click="invite">+</button></div></div>
</aside>
<article class="panel p-5"><div class="mx-auto max-w-xl"><div class="grid gap-3 sm:grid-cols-[1fr_170px]"><label class="field"><span>Form title</span><input v-model="schema.title"/></label><label class="field"><span>Approval mode</span><select v-model="schema.approval_mode"><option value="auto">Auto approve</option><option value="manual">Manual approval</option><option value="invite_only">Invite only</option></select></label></div><p class="panel-kicker mt-6">Form canvas</p><div class="mt-4 space-y-3"><button v-for="(f,i) in schema.fields" :key="f.id||i" @click="selected=i" class="w-full rounded-xl border p-3 text-left transition" :class="selected===i?'border-indigo-500 ring-4 ring-indigo-50':'border-slate-200 hover:border-slate-300'"><div class="flex justify-between"><span class="text-xs font-bold">{{f.label}} <i v-if="f.required" class="text-rose-500">*</i></span><span class="text-[10px] uppercase text-slate-400">{{f.type}}</span></div><div class="mt-2 h-9 rounded-lg border border-slate-200 bg-slate-50"></div></button></div></div></article>
<aside class="panel p-5" v-if="schema.fields[selected]"><p class="panel-kicker">Field settings</p><div class="mt-4 space-y-4"><label class="field"><span>Label</span><input v-model="schema.fields[selected].label"/></label><label class="flex items-center justify-between text-sm font-semibold">Required <input type="checkbox" v-model="schema.fields[selected].required" class="h-4 w-4 accent-indigo-600"/></label><label class="field"><span>Visibility</span><select v-model="schema.fields[selected].visibility"><option value="always">Always</option><option value="conditional">Conditional</option></select></label><button @click="remove" class="w-full rounded-xl border border-rose-200 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50">Delete field</button></div></aside>
</section></div></template>
