<script setup>
import {computed,onMounted,reactive,ref,watch} from 'vue'
import {api} from '../services/api'
import {useEventStore} from '../stores/event'

const events=useEventStore()
const palette=[
  ['Text Field','text'],['Email','email'],['Phone','phone'],['Dropdown','select'],
  ['Multi Select','multiselect'],['Radio Button','radio'],['Date Picker','date'],['Paragraph','paragraph']
]
const schema=reactive({title:'Event Registration',approval_mode:'auto',categories:[],fields:[]})
const selected=ref(0),saving=ref(false),saved=ref(false),error=ref(''),inviteEmail=ref(''),inviteCategory=ref('General'),invites=ref([])
const categoriesText=ref('')
const eventId=computed(()=>events.currentId)
const currentField=computed(()=>schema.fields[selected.value]||null)
const coreFieldIds=['fld_name','fld_email','fld_category']
const conditionalSources=computed(()=>schema.fields.filter((_,i)=>i!==selected.value))
const choiceTypes=['select','dropdown','multiselect','radio']

async function load(){
  error.value='';saved.value=false
  if(!events.loaded) await events.load()
  if(!eventId.value){schema.fields=[];invites.value=[];return}
  try{
    const [s,i]=await Promise.all([api.registration(eventId.value),api.invitations(eventId.value)])
    Object.assign(schema,s)
    categoriesText.value=(s.categories||[]).join(', ')
    invites.value=i
    inviteCategory.value=s.categories?.[0]||'General'
    selected.value=Math.min(selected.value,Math.max(0,schema.fields.length-1))
  }catch(e){error.value=e.message}
}
onMounted(load);watch(eventId,load)

function add(label,type){
  const field={id:'fld_'+Date.now(),label,type,required:false,visibility:'always'}
  if(choiceTypes.includes(type)) field.options=['Option 1','Option 2']
  schema.fields.push(field)
  selected.value=schema.fields.length-1
}
function remove(){
  const field=currentField.value
  if(!field||coreFieldIds.includes(field.id))return
  schema.fields.splice(selected.value,1)
  selected.value=Math.max(0,selected.value-1)
}
function optionsText(field){return (field?.options||[]).join(', ')}
function setOptions(field,value){field.options=String(value||'').split(',').map(x=>x.trim()).filter(Boolean)}
function setVisibility(field,value){
  field.visibility=value
  if(value==='conditional'){
    const source=conditionalSources.value[0]
    field.condition=field.condition||{field:source?.id||'',operator:'equals',value:''}
  }else{
    delete field.condition
  }
}
async function save(){
  if(!eventId.value)return
  saving.value=true;saved.value=false;error.value=''
  try{
    schema.categories=categoriesText.value.split(',').map(x=>x.trim()).filter(Boolean)
    Object.assign(schema,await api.saveRegistration(eventId.value,schema))
    categoriesText.value=(schema.categories||[]).join(', ')
    saved.value=true
  }catch(e){error.value=e.message}
  finally{saving.value=false}
}
async function invite(){
  if(!eventId.value||!inviteEmail.value)return
  error.value=''
  try{
    const row=await api.createInvitation({event_id:eventId.value,email:inviteEmail.value,category:inviteCategory.value})
    invites.value.unshift(row);inviteEmail.value=''
  }catch(e){error.value=e.message}
}
</script>

<template><div class="space-y-6">
<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
  <div><p class="eyebrow">Registration</p><h1 class="page-title">Registration Form Builder</h1><p class="page-subtitle">{{events.current?.name||'Select an event'}} · form schema, categories, approvals and private invitations.</p></div>
  <div class="flex items-center gap-2"><span v-if="saved" class="text-xs font-bold text-emerald-600">Saved</span><RouterLink v-if="eventId" :to="'/events/'+eventId+'/preview'" class="btn-secondary">Preview</RouterLink><button class="btn-primary" :disabled="saving||!eventId" @click="save">{{saving?'Saving…':'Save Form'}}</button></div>
</section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>
<section v-if="!eventId" class="panel p-10 text-center"><RouterLink to="/events/create" class="btn-primary">Create an event first</RouterLink></section>

<section v-else class="grid gap-4 xl:grid-cols-[220px_1fr_320px]">
<aside class="space-y-4">
  <div class="panel p-4">
    <p class="panel-kicker">Add field</p>
    <div class="mt-3 space-y-2"><button v-for="[label,type] in palette" :key="type" @click="add(label,type)" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-left text-xs font-semibold hover:border-indigo-300 hover:bg-indigo-50">{{label}}</button></div>
    <p class="mt-3 text-[11px] leading-5 text-slate-400">File upload is intentionally hidden until binary storage/download is implemented; no filename-only fake upload is exposed.</p>
  </div>
  <div class="panel p-4">
    <p class="panel-kicker">Invitations</p>
    <p class="mt-2 text-xs text-slate-500">{{invites.length}} invitations for this event.</p>
    <div class="mt-3 space-y-2"><input v-model="inviteEmail" class="control" type="email" placeholder="email@example.com"/><select v-model="inviteCategory" class="control"><option v-for="c in schema.categories" :key="c">{{c}}</option></select><button class="btn-primary w-full" @click="invite">Create invitation</button></div>
  </div>
</aside>

<article class="panel p-5">
  <div class="mx-auto max-w-xl">
    <div class="grid gap-3 sm:grid-cols-[1fr_170px]"><label class="field"><span>Form title</span><input v-model="schema.title"/></label><label class="field"><span>Approval mode</span><select v-model="schema.approval_mode"><option value="auto">Auto approve</option><option value="manual">Manual approval</option><option value="invite_only">Invite only</option></select></label></div>
    <label class="field mt-4"><span>Categories</span><input v-model="categoriesText" placeholder="General, VIP, Speaker"/></label>
    <p class="panel-kicker mt-6">Form canvas</p>
    <div class="mt-4 space-y-3">
      <button v-for="(field,i) in schema.fields" :key="field.id||i" @click="selected=i" class="w-full rounded-xl border p-3 text-left transition" :class="selected===i?'border-indigo-500 ring-4 ring-indigo-50':'border-slate-200 hover:border-slate-300'">
        <div class="flex justify-between"><span class="text-xs font-bold">{{field.label}} <i v-if="field.required" class="text-rose-500">*</i></span><span class="text-[10px] uppercase text-slate-400">{{field.type}}</span></div>
        <div class="mt-2 h-9 rounded-lg border border-slate-200 bg-slate-50"></div>
        <p v-if="field.visibility==='conditional'" class="mt-2 text-[10px] font-semibold text-indigo-500">Conditional · {{field.condition?.field}} {{field.condition?.operator}} {{field.condition?.value}}</p>
      </button>
    </div>
  </div>
</article>

<aside class="panel p-5" v-if="currentField">
  <p class="panel-kicker">Field settings</p>
  <div class="mt-4 space-y-4">
    <label class="field"><span>Label</span><input v-model="currentField.label"/></label>
    <label class="field"><span>Help text</span><input v-model="currentField.help" placeholder="Optional guidance"/></label>
    <label class="flex items-center justify-between text-sm font-semibold">Required <input type="checkbox" v-model="currentField.required" :disabled="coreFieldIds.includes(currentField.id)" class="h-4 w-4 accent-indigo-600"/></label>

    <label v-if="choiceTypes.includes(currentField.type)&&currentField.id!=='fld_category'" class="field">
      <span>Options (comma separated)</span>
      <input :value="optionsText(currentField)" @input="setOptions(currentField,$event.target.value)" placeholder="Option 1, Option 2"/>
    </label>

    <label class="field"><span>Visibility</span><select :value="currentField.visibility" @change="setVisibility(currentField,$event.target.value)"><option value="always">Always</option><option value="conditional">Conditional</option></select></label>
    <div v-if="currentField.visibility==='conditional'" class="space-y-3 rounded-2xl bg-slate-50 p-3">
      <label class="field"><span>Show when field</span><select v-model="currentField.condition.field"><option value="">Select source field</option><option v-for="field in conditionalSources" :key="field.id" :value="field.id">{{field.label}}</option></select></label>
      <label class="field"><span>Operator</span><select v-model="currentField.condition.operator"><option value="equals">Equals</option><option value="not_equals">Does not equal</option><option value="contains">Contains</option></select></label>
      <label class="field"><span>Value</span><input v-model="currentField.condition.value"/></label>
    </div>

    <div v-if="coreFieldIds.includes(currentField.id)" class="rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">This is a system identity field required by registration and cannot be deleted.</div>
    <button v-else @click="remove" class="w-full rounded-xl border border-rose-200 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50">Delete field</button>
  </div>
</aside>
</section>
</div></template>
