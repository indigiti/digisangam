<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import PublicRegistrationField from '../components/PublicRegistrationField.vue'

const route=useRoute(),router=useRouter()
const loading=ref(true),busy=ref(false),error=ref(''),data=ref(null),step=ref(1),selectedTicket=ref('')
const previewMode=computed(()=>route.name==='event-preview')
const answers=reactive({}),honeypot=ref(''),uploadingField=ref('')
const money=n=>Number(n||0)===0?'Free':new Intl.NumberFormat('en-IN',{style:'currency',currency:data.value?.event?.currency||'INR',maximumFractionDigits:0}).format(n)
const selected=computed(()=>data.value?.tickets?.find(x=>x.id===selectedTicket.value)||null)
const fields=computed(()=>data.value?.registration?.fields||[])

onMounted(async()=>{
  try{
    data.value=previewMode.value?await api.eventPreview(route.params.id):await api.publicEvent(route.params.id)
    for(const field of fields.value) answers[field.id]=field.type==='multiselect'?[]:''
    const category=fields.value.find(x=>x.id==='fld_category')
    if(category&&data.value.registration.categories?.length) answers[category.id]=data.value.registration.categories[0]
  }catch(e){error.value=e.message}
  finally{loading.value=false}
})

async function uploadField(field,file){
  uploadingField.value=field.id
  error.value=''
  try{
    const media=await api.publicUploadMedia(route.params.id,file,'registration_file')
    answers[field.id]=media.id
  }catch(e){error.value=e.message}
  finally{uploadingField.value=''}
}
function visible(field){
  if(field.visibility!=='conditional'||!field.condition) return true
  const source=answers[field.condition.field]
  if(field.condition.operator==='not_equals') return source!==field.condition.value
  if(field.condition.operator==='contains') return Array.isArray(source)?source.includes(field.condition.value):String(source||'').includes(field.condition.value)
  return source===field.condition.value
}
function next(){
  error.value=''
  if(step.value===1&&!selectedTicket.value){error.value='Please select a ticket to continue.';return}
  if(step.value===2){
    for(const field of fields.value.filter(visible)){
      const value=answers[field.id]
      if(field.required&&(value===undefined||value===null||value===''||(Array.isArray(value)&&!value.length))){error.value=field.label+' is required.';return}
    }
  }
  step.value=Math.min(3,step.value+1)
  window.scrollTo({top:0,behavior:'smooth'})
}
function loadRazorpay(){
  return new Promise((resolve,reject)=>{
    if(window.Razorpay){resolve();return}
    const existing=document.querySelector('script[data-digisangam-razorpay]')
    if(existing){existing.addEventListener('load',resolve,{once:true});existing.addEventListener('error',()=>reject(new Error('Unable to load Razorpay Checkout.')),{once:true});return}
    const script=document.createElement('script')
    script.src='https://checkout.razorpay.com/v1/checkout.js'
    script.async=true
    script.dataset.digisangamRazorpay='1'
    script.onload=resolve
    script.onerror=()=>reject(new Error('Unable to load Razorpay Checkout.'))
    document.head.appendChild(script)
  })
}
async function openRazorpay(result){
  await loadRazorpay()
  return new Promise((resolve,reject)=>{
    const payment=result.payment
    const instance=new window.Razorpay({
      key:payment.key_id,
      amount:payment.amount_subunits,
      currency:payment.currency,
      name:data.value.event.name,
      description:payment.description,
      order_id:payment.provider_order_id,
      prefill:payment.prefill,
      theme:{color:data.value?.event?.branding?.primary_color||'#4f46e5'},
      handler:async(response)=>{
        try{
          await api.publicVerifyRazorpay({
            order_id:result.order.id,
            confirmation_token:result.confirmation_token,
            razorpay_order_id:response.razorpay_order_id,
            razorpay_payment_id:response.razorpay_payment_id,
            razorpay_signature:response.razorpay_signature,
          })
          resolve()
        }catch(e){reject(e)}
      },
      modal:{ondismiss:()=>resolve()},
    })
    instance.on('payment.failed',response=>reject(new Error(response?.error?.description||'Payment failed.')))
    instance.open()
  })
}
async function submit(){
  busy.value=true;error.value=''
  try{
    const result=await api.publicRegister(route.params.id,{ticket_id:selectedTicket.value,answers:{...answers},website:honeypot.value})
    if(result.payment?.action==='razorpay_checkout'){
      await openRazorpay(result)
    }
    router.push('/e/'+route.params.id+'/confirmation/'+result.confirmation_token)
  }catch(e){error.value=e.message}
  finally{busy.value=false}
}
</script>
<template>
<main class="min-h-screen bg-[#f5f7fb] text-slate-950">
  <div v-if="previewMode" class="sticky top-0 z-50 flex items-center justify-between gap-3 bg-amber-400 px-4 py-2 text-sm font-bold text-amber-950"><span>Admin preview · this event is not being registered through this screen.</span><RouterLink :to="'/events/'+route.params.id" class="rounded-lg bg-amber-950 px-3 py-1.5 text-xs text-white">Back to event</RouterLink></div>
  <div v-if="loading" class="grid min-h-screen place-items-center"><div class="h-10 w-10 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600"></div></div>
  <div v-else-if="error&&!data" class="grid min-h-screen place-items-center p-6"><div class="max-w-md text-center"><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-50 text-xl text-rose-600">!</div><h1 class="mt-4 text-2xl font-black">Registration unavailable</h1><p class="mt-2 text-sm text-slate-500">{{error}}</p></div></div>
  <template v-else-if="data">
    <section class="relative overflow-hidden text-white" :style="{background:data.event.branding?.background_color||'#0f172a'}">
      <img v-if="data.event.branding?.cover_url" :src="data.event.branding.cover_url" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25"/>
      <div class="absolute inset-0 opacity-60" :style="{background:`radial-gradient(circle at 75% 15%,${data.event.branding?.primary_color||'#6366f1'} 0,transparent 28%),radial-gradient(circle at 20% 80%,${data.event.branding?.secondary_color||'#06b6d4'} 0,transparent 30%)`}"></div>
      <div class="relative mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:py-16">
        <div class="flex items-center justify-between"><div class="flex items-center gap-3"><img v-if="data.event.branding?.logo_url" :src="data.event.branding.logo_url" alt="" class="h-10 w-10 rounded-xl bg-white object-contain p-1"/><div v-else class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-xs font-black">DS</div><span class="text-sm font-bold">{{data.event.branding?.brand_name||data.event.name}}</span></div><span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs">{{data.event.status}}</span></div>
        <div class="mt-14 max-w-3xl"><p class="text-xs font-bold uppercase tracking-[.24em] text-cyan-300">{{data.event.type||'Event'}} · {{data.event.category||'Featured'}}</p><h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">{{data.event.public_page?.headline||data.event.name}}</h1><p class="mt-5 max-w-2xl text-base leading-7 text-slate-300">{{data.event.description||'Join us for this event.'}}</p><div class="mt-8 flex flex-wrap gap-3 text-sm"><span class="rounded-xl bg-white/10 px-4 py-2">◷ {{data.event.start_date||data.event.date}}</span><span class="rounded-xl bg-white/10 px-4 py-2">⌖ {{data.event.location}}</span></div></div>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-8 sm:px-8 lg:py-12">
      <div class="mb-7 grid grid-cols-3 gap-3"><div v-for="(label,i) in ['Choose ticket','Your details','Review & checkout']" :key="label" class="rounded-2xl border p-3 sm:p-4" :class="i+1<=step?'border-indigo-200 bg-indigo-50':'border-slate-200 bg-white'"><div class="flex items-center gap-2"><span class="grid h-7 w-7 place-items-center rounded-full text-xs font-black" :class="i+1<=step?'bg-indigo-600 text-white':'bg-slate-100 text-slate-400'">{{i+1}}</span><b class="hidden text-sm sm:block">{{label}}</b></div></div></div>

      <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <section class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
          <div v-if="step===1"><p class="text-xs font-black uppercase tracking-widest text-indigo-600">Step 1</p><h2 class="mt-2 text-2xl font-black">Choose your ticket</h2><p class="mt-2 text-sm text-slate-500">Select one available ticket type for this registration.</p><div class="mt-6 grid gap-3"><button v-for="ticket in data.tickets" :key="ticket.id" @click="selectedTicket=ticket.id" class="grid gap-3 rounded-2xl border p-4 text-left transition sm:grid-cols-[1fr_auto] sm:items-center" :class="selectedTicket===ticket.id?'border-indigo-500 bg-indigo-50 ring-4 ring-indigo-50':'border-slate-200 hover:border-indigo-200'"><div><div class="flex items-center gap-2"><h3 class="font-black">{{ticket.name}}</h3><span v-if="ticket.quantity-ticket.sold<25" class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">Only {{ticket.quantity-ticket.sold}} left</span></div><p class="mt-1 text-xs text-slate-500">{{ticket.quantity-ticket.sold}} of {{ticket.quantity}} available</p></div><strong class="text-xl">{{money(ticket.price)}}</strong></button><div v-if="!data.tickets.length" class="rounded-2xl bg-amber-50 p-5 text-sm text-amber-800">No tickets are currently available.</div></div></div>

          <div v-else-if="step===2"><p class="text-xs font-black uppercase tracking-widest text-indigo-600">Step 2</p><h2 class="mt-2 text-2xl font-black">{{data.registration.title}}</h2><p class="mt-2 text-sm text-slate-500">Fields marked with * are required.</p><div class="mt-7 grid gap-5 sm:grid-cols-2"><template v-for="field in fields" :key="field.id"><PublicRegistrationField v-if="visible(field)" v-model="answers[field.id]" :field="field" :categories="data.registration.categories" :class="['paragraph','textarea','file'].includes(field.type)?'sm:col-span-2':''" @upload="uploadField(field,$event)"/><p v-if="field.type==='file'&&uploadingField===field.id" class="sm:col-span-2 text-xs font-semibold text-indigo-600">Uploading file…</p></template><label class="hidden"><span>Website</span><input v-model="honeypot" tabindex="-1" autocomplete="off"/></label></div></div>

          <div v-else><p class="text-xs font-black uppercase tracking-widest text-indigo-600">Step 3</p><h2 class="mt-2 text-2xl font-black">Review & checkout</h2><div class="mt-6 rounded-2xl border border-slate-200 p-5"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase text-slate-400">Ticket</p><h3 class="mt-1 font-black">{{selected?.name}}</h3></div><strong class="text-xl">{{money(selected?.price)}}</strong></div></div><div class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200 px-5"><div v-for="field in fields.filter(visible)" :key="field.id" class="flex justify-between gap-6 py-3 text-sm"><span class="text-slate-500">{{field.label}}</span><b class="text-right">{{Array.isArray(answers[field.id])?answers[field.id].join(', '):(answers[field.id]||'—')}}</b></div></div><div v-if="Number(selected?.price||0)>0" class="mt-4 rounded-2xl bg-indigo-50 p-4 text-sm text-indigo-800"><b>Secure payment.</b> If Razorpay is configured, its checkout opens after registration. Otherwise the registration is saved as payment pending and no entry QR is issued.</div><div v-else class="mt-4 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800">This is a free ticket. Your signed QR credential is issued immediately unless organizer approval is required.</div></div>

          <p v-if="error" class="mt-5 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{error}}</p>
          <div class="mt-8 flex justify-between border-t border-slate-100 pt-5"><button v-if="step>1" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold" @click="step--">← Back</button><span v-else></span><button v-if="step<3" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-100" @click="next">Continue →</button><button v-else class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-100 disabled:opacity-50" :disabled="busy||previewMode" @click="submit">{{previewMode?'Preview only':(busy?'Processing…':(Number(selected?.price||0)>0?'Register & Pay':'Complete registration'))}}</button></div>
        </section>

        <aside class="space-y-4"><div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-slate-400">Event summary</p><h3 class="mt-3 text-lg font-black">{{data.event.name}}</h3><div class="mt-4 space-y-3 text-sm text-slate-600"><p>◷ {{data.event.start_date||data.event.date}}</p><p>⌖ {{data.event.location}}</p><p>◎ {{data.registration.approval_mode==='manual'?'Manual approval':data.registration.approval_mode==='invite_only'?'Invitation only':'Instant approval'}}</p></div></div><div class="rounded-[24px] bg-slate-950 p-5 text-white"><p class="text-xs font-black uppercase tracking-widest text-cyan-300">Secure registration</p><p class="mt-3 text-sm leading-6 text-slate-300">Signed credentials, payment verification, inventory locks and private confirmation links are handled by DigiSangam EventOS.</p></div></aside>
      </div>
    </section>
  </template>
</main>
</template>
