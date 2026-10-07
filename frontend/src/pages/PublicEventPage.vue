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
const startingPrice=computed(()=>{const prices=(data.value?.tickets||[]).map(t=>Number(t.price||0));return prices.length?Math.min(...prices):0})

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
function scrollToRegistration(){document.getElementById('registration-flow')?.scrollIntoView({behavior:'smooth'})}
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
<main class="min-h-screen bg-[#f5f5f7] text-[#202124]">
  <div v-if="previewMode" class="sticky top-0 z-[60] flex items-center justify-between gap-3 bg-amber-400 px-4 py-2 text-sm font-bold text-amber-950"><span>Admin preview · registration is disabled.</span><RouterLink :to="'/events/'+route.params.id" class="rounded-lg bg-amber-950 px-3 py-1.5 text-xs text-white">Back to event</RouterLink></div>

  <header class="sticky top-0 z-50 border-b border-black/5 bg-white/95 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-[1280px] items-center gap-4 px-4 sm:px-6">
      <RouterLink to="/discover" class="flex shrink-0 items-center gap-2.5"><span class="grid h-9 w-9 place-items-center rounded-xl bg-[#f84464] text-xs font-black text-white">DS</span><span class="hidden text-lg font-black sm:block">DigiSangam</span></RouterLink>
      <RouterLink to="/discover" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-400">⌕ Search events, conferences and workshops</RouterLink>
      <RouterLink to="/access" class="rounded-lg bg-[#f84464] px-4 py-2 text-xs font-bold text-white">Organizer</RouterLink>
    </div>
  </header>

  <div v-if="loading" class="grid min-h-[70vh] place-items-center"><div class="h-10 w-10 animate-spin rounded-full border-4 border-rose-100 border-t-[#f84464]"></div></div>
  <div v-else-if="error&&!data" class="grid min-h-[70vh] place-items-center p-6"><div class="max-w-md text-center"><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-50 text-xl text-rose-600">!</div><h1 class="mt-4 text-2xl font-black">Registration unavailable</h1><p class="mt-2 text-sm text-slate-500">{{error}}</p><RouterLink to="/discover" class="mt-5 inline-flex rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-bold text-white">Explore other events</RouterLink></div></div>

  <template v-else-if="data">
    <section class="relative overflow-hidden bg-[#222733] text-white">
      <img v-if="data.event.branding?.cover_url" :src="data.event.branding.cover_url" alt="" class="absolute inset-0 h-full w-full object-cover opacity-20 blur-[1px]"/>
      <div class="absolute inset-0 bg-gradient-to-r from-[#20242f] via-[#20242f]/95 to-[#20242f]/75"></div>
      <div class="relative mx-auto grid max-w-[1280px] gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[280px_1fr] lg:items-center lg:py-10">
        <div class="mx-auto w-full max-w-[280px]">
          <div class="aspect-[4/5] overflow-hidden rounded-2xl bg-slate-800 shadow-2xl ring-1 ring-white/10">
            <img v-if="data.event.branding?.cover_url" :src="data.event.branding.cover_url" :alt="data.event.name" class="h-full w-full object-cover"/>
            <div v-else class="grid h-full place-items-end p-6" :style="{background:'linear-gradient(145deg,'+(data.event.branding?.primary_color||'#f84464')+','+(data.event.branding?.secondary_color||'#7c3aed')+')'}"><div><p class="text-xs font-black uppercase tracking-[.18em] text-white/65">{{data.event.type}}</p><h2 class="mt-2 text-2xl font-black">{{data.event.name}}</h2></div></div>
          </div>
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap gap-2"><span class="rounded-full bg-[#f84464] px-3 py-1.5 text-[10px] font-black uppercase tracking-wider">{{data.event.status}}</span><span class="rounded-full bg-white/10 px-3 py-1.5 text-[10px] font-bold">{{data.event.category}}</span><span class="rounded-full bg-white/10 px-3 py-1.5 text-[10px] font-bold">{{data.event.format?.replace('_',' ')}}</span></div>
          <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-5xl">{{data.event.public_page?.headline||data.event.name}}</h1>
          <p class="mt-4 max-w-3xl text-sm leading-6 text-slate-300 sm:text-base">{{data.event.description||'Discover this event and register securely with DigiSangam.'}}</p>
          <div class="mt-6 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-slate-200"><span>◷ {{data.event.start_date||data.event.date}}{{data.event.end_date&&data.event.end_date!==data.event.start_date?' – '+data.event.end_date:''}}</span><span>⌖ {{data.event.venue_name||data.event.location||'Venue TBA'}}</span><span>◎ {{data.registration.approval_mode==='manual'?'Approval required':data.registration.approval_mode==='invite_only'?'Invite only':'Instant confirmation'}}</span></div>
          <div class="mt-7 flex flex-wrap items-center gap-4"><button class="rounded-xl bg-[#f84464] px-6 py-3 text-sm font-black text-white shadow-lg shadow-rose-950/20" @click="scrollToRegistration">Register now</button><span class="text-sm text-slate-400">From <b class="text-lg text-white">{{money(startingPrice,data.event.currency)}}</b></span></div>
        </div>
      </div>
    </section>

    <section id="registration-flow" class="mx-auto max-w-[1280px] px-4 py-8 pb-28 sm:px-6 lg:py-12 lg:pb-12">
      <div class="mb-7 flex items-center gap-2 overflow-x-auto">
        <div v-for="(label,i) in ['Tickets','Details','Checkout']" :key="label" class="flex shrink-0 items-center gap-2 rounded-full px-3 py-2 text-xs font-bold" :class="i+1<=step?'bg-[#f84464] text-white':'bg-white text-slate-400'"><span>{{i+1}}</span><span>{{label}}</span></div>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <section class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
          <div v-if="step===1">
            <p class="text-xs font-black uppercase tracking-[.16em] text-[#f84464]">Select tickets</p><h2 class="mt-2 text-2xl font-black">Choose your experience</h2><p class="mt-2 text-sm text-slate-500">Pick one ticket type to continue.</p>
            <div class="mt-6 grid gap-3">
              <button v-for="ticket in data.tickets" :key="ticket.id" @click="selectedTicket=ticket.id" class="grid gap-3 rounded-2xl border p-4 text-left transition sm:grid-cols-[1fr_auto] sm:items-center" :class="selectedTicket===ticket.id?'border-[#f84464] bg-rose-50 ring-4 ring-rose-50':'border-slate-200 hover:border-rose-200'">
                <div><div class="flex items-center gap-2"><h3 class="font-black">{{ticket.name}}</h3><span v-if="ticket.quantity-ticket.sold<25" class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">Only {{ticket.quantity-ticket.sold}} left</span></div><p class="mt-1 text-xs text-slate-500">{{ticket.quantity-ticket.sold}} of {{ticket.quantity}} available</p></div><strong class="text-xl">{{money(ticket.price)}}</strong>
              </button>
              <div v-if="!data.tickets.length" class="rounded-2xl bg-amber-50 p-5 text-sm text-amber-800">No tickets are currently available.</div>
            </div>
          </div>

          <div v-else-if="step===2">
            <p class="text-xs font-black uppercase tracking-[.16em] text-[#f84464]">Attendee details</p><h2 class="mt-2 text-2xl font-black">{{data.registration.title}}</h2><p class="mt-2 text-sm text-slate-500">Fields marked with * are required.</p>
            <div class="mt-7 grid gap-5 sm:grid-cols-2"><template v-for="field in fields" :key="field.id"><PublicRegistrationField v-if="visible(field)" v-model="answers[field.id]" :field="field" :categories="data.registration.categories" :class="['paragraph','textarea','file'].includes(field.type)?'sm:col-span-2':''" @upload="uploadField(field,$event)"/><p v-if="field.type==='file'&&uploadingField===field.id" class="sm:col-span-2 text-xs font-semibold text-[#f84464]">Uploading file…</p></template><label class="hidden"><span>Website</span><input v-model="honeypot" tabindex="-1" autocomplete="off"/></label></div>
          </div>

          <div v-else>
            <p class="text-xs font-black uppercase tracking-[.16em] text-[#f84464]">Review & checkout</p><h2 class="mt-2 text-2xl font-black">Almost there</h2>
            <div class="mt-6 rounded-2xl border border-slate-200 p-5"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase text-slate-400">Ticket</p><h3 class="mt-1 font-black">{{selected?.name}}</h3></div><strong class="text-xl">{{money(selected?.price)}}</strong></div></div>
            <div class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200 px-5"><div v-for="field in fields.filter(visible)" :key="field.id" class="flex justify-between gap-6 py-3 text-sm"><span class="text-slate-500">{{field.label}}</span><b class="text-right">{{Array.isArray(answers[field.id])?answers[field.id].join(', '):(answers[field.id]||'—')}}</b></div></div>
            <div v-if="Number(selected?.price||0)>0" class="mt-4 rounded-2xl bg-indigo-50 p-4 text-sm text-indigo-800"><b>Secure payment.</b> Payment verification and ticket inventory are managed server-side.</div>
            <div v-else class="mt-4 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800">Free ticket. Your signed credential is issued immediately unless organizer approval is required.</div>
          </div>

          <p v-if="error" class="mt-5 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{error}}</p>
          <div class="mt-8 flex justify-between border-t border-slate-100 pt-5"><button v-if="step>1" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold" @click="step--">← Back</button><span v-else></span><button v-if="step<3" class="rounded-xl bg-[#f84464] px-5 py-2.5 text-sm font-black text-white" @click="next">Continue →</button><button v-else class="rounded-xl bg-[#f84464] px-5 py-2.5 text-sm font-black text-white disabled:opacity-50" :disabled="busy||previewMode" @click="submit">{{previewMode?'Preview only':(busy?'Processing…':(Number(selected?.price||0)>0?'Register & Pay':'Complete registration'))}}</button></div>
        </section>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
          <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-widest text-slate-400">Booking summary</p><h3 class="mt-3 text-lg font-black">{{data.event.name}}</h3><div class="mt-4 space-y-3 text-sm text-slate-600"><p>◷ {{data.event.start_date||data.event.date}}</p><p>⌖ {{data.event.venue_name||data.event.location}}</p><p>◎ {{data.registration.approval_mode==='manual'?'Manual approval':data.registration.approval_mode==='invite_only'?'Invitation only':'Instant approval'}}</p></div><div v-if="selected" class="mt-5 border-t border-slate-100 pt-4"><div class="flex justify-between text-sm"><span>{{selected.name}}</span><b>{{money(selected.price)}}</b></div></div></div>
          <div class="rounded-[24px] bg-[#2b3140] p-5 text-white"><p class="text-xs font-black uppercase tracking-widest text-rose-300">DigiSangam Secure</p><p class="mt-3 text-sm leading-6 text-slate-300">Signed credentials, verified payment state, inventory locks and private confirmation links protect every registration.</p></div>
        </aside>
      </div>
    </section>

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 p-3 backdrop-blur lg:hidden">
      <div class="mx-auto flex max-w-lg items-center justify-between gap-3"><div><p class="text-[10px] font-bold uppercase text-slate-400">{{selected?'Selected ticket':'Tickets from'}}</p><p class="text-sm font-black">{{selected?selected.name:money(Math.min(...data.tickets.map(t=>Number(t.price||0))),data.event.currency)}}</p></div><button class="rounded-xl bg-[#f84464] px-5 py-3 text-sm font-black text-white" @click="scrollToRegistration">{{selected?'Continue booking':'View tickets'}}</button></div>
    </div>
  </template>
</main>
</template>
