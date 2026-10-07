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
<main class="min-h-screen bg-[#f5f5f5] text-[#20242f]">
  <div v-if="previewMode" class="sticky top-0 z-[70] flex items-center justify-between gap-3 bg-amber-400 px-4 py-2 text-[12px] font-bold text-amber-950"><span>Admin preview · registration is disabled.</span><RouterLink :to="'/events/'+route.params.id" class="rounded bg-amber-950 px-3 py-1 text-white">Back to event</RouterLink></div>

  <header class="sticky top-0 z-50 bg-white shadow-[0_1px_0_rgba(0,0,0,.07)]">
    <div class="mx-auto flex h-[66px] max-w-[1240px] items-center gap-4 px-4 sm:px-6">
      <RouterLink to="/discover" class="flex shrink-0 items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-[10px] bg-[#f84464] text-[11px] font-black text-white">DS</span><span class="hidden text-[19px] font-black tracking-[-.04em] sm:block">DigiSangam</span></RouterLink>
      <RouterLink to="/discover" class="relative min-w-0 flex-1 sm:max-w-[620px]"><span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span><span class="flex h-10 items-center rounded-[8px] border border-[#dedfe3] pl-11 pr-4 text-[12px] text-slate-400">Search for events, conferences, workshops and more</span></RouterLink>
      <RouterLink to="/access" class="ml-auto rounded-[7px] bg-[#f84464] px-4 py-2 text-[11px] font-bold text-white">Organizer</RouterLink>
    </div>
  </header>

  <div v-if="loading" class="mx-auto max-w-[1240px] px-4 py-8 sm:px-6"><div class="h-[380px] animate-pulse rounded-[18px] bg-slate-200"></div></div>
  <div v-else-if="error&&!data" class="grid min-h-[70vh] place-items-center p-6"><div class="max-w-md text-center"><div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-rose-50 font-black text-rose-600">!</div><h1 class="mt-4 text-2xl font-black">Registration unavailable</h1><p class="mt-2 text-sm text-slate-500">{{error}}</p><RouterLink to="/discover" class="mt-5 inline-flex rounded-lg bg-[#f84464] px-5 py-2.5 text-sm font-bold text-white">Explore events</RouterLink></div></div>

  <template v-else-if="data">
    <section class="relative overflow-hidden bg-[#222733] text-white">
      <img v-if="data.event.branding?.cover_url" :src="data.event.branding.cover_url" alt="" class="absolute inset-0 h-full w-full object-cover opacity-[.16] blur-xl"/>
      <div class="absolute inset-0 bg-gradient-to-r from-[#20242f] via-[#20242f]/95 to-[#20242f]/80"></div>

      <div class="relative mx-auto grid max-w-[1240px] gap-7 px-4 py-7 sm:px-6 lg:grid-cols-[250px_minmax(0,1fr)_230px] lg:items-center lg:py-9">
        <div class="mx-auto w-full max-w-[250px]">
          <div class="aspect-[2/3] overflow-hidden rounded-[12px] bg-slate-800 shadow-[0_16px_45px_rgba(0,0,0,.35)]">
            <img v-if="data.event.branding?.poster_url||data.event.branding?.cover_url" :src="data.event.branding?.poster_url||data.event.branding?.cover_url" :alt="data.event.name" class="h-full w-full object-cover"/>
            <div v-else class="flex h-full items-end p-5" :style="{background:'linear-gradient(145deg,'+(data.event.branding?.primary_color||'#f84464')+','+(data.event.branding?.secondary_color||'#6d28d9')+')'}"><div><p class="text-[10px] font-black uppercase tracking-[.18em] text-white/65">{{data.event.category}}</p><h2 class="mt-2 text-xl font-black">{{data.event.name}}</h2></div></div>
          </div>
        </div>

        <div class="min-w-0 py-2">
          <div class="flex flex-wrap gap-2"><span class="rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold">{{data.event.type}}</span><span class="rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold">{{data.event.category}}</span><span v-if="data.event.status==='Live'" class="rounded-full bg-[#f84464] px-3 py-1 text-[10px] font-black">LIVE</span></div>
          <h1 class="mt-4 text-[30px] font-black leading-[1.08] tracking-[-.035em] sm:text-[40px]">{{data.event.public_page?.headline||data.event.name}}</h1>
          <p class="mt-4 max-w-2xl text-[13px] leading-6 text-slate-300">{{data.event.description||'Discover this event and register securely with DigiSangam.'}}</p>

          <div class="mt-6 space-y-3 text-[12px] font-semibold text-slate-200">
            <div class="flex gap-3"><span class="w-5 text-center text-slate-400">◷</span><span>{{data.event.start_date||data.event.date}}{{data.event.end_date&&data.event.end_date!==data.event.start_date?' – '+data.event.end_date:''}}</span></div>
            <div class="flex gap-3"><span class="w-5 text-center text-slate-400">⌖</span><span>{{data.event.venue_name||data.event.location||'Venue to be announced'}}</span></div>
            <div class="flex gap-3"><span class="w-5 text-center text-slate-400">◎</span><span>{{data.event.format?.replace('_',' ')}} · {{data.registration.approval_mode==='manual'?'Approval required':data.registration.approval_mode==='invite_only'?'Invite only':'Instant confirmation'}}</span></div>
          </div>
        </div>

        <aside class="hidden lg:block">
          <div class="rounded-[14px] bg-white p-5 text-slate-900 shadow-xl">
            <p class="text-[10px] font-black uppercase tracking-[.14em] text-slate-400">Tickets from</p>
            <p class="mt-1 text-[24px] font-black">{{money(startingPrice,data.event.currency)}}</p>
            <p class="mt-2 text-[11px] leading-5 text-slate-500">{{data.tickets.length}} ticket type{{data.tickets.length===1?'':'s'}} available</p>
            <button class="mt-5 w-full rounded-[8px] bg-[#f84464] px-4 py-3 text-[12px] font-black text-white" @click="scrollToRegistration">Book / Register</button>
          </div>
        </aside>
      </div>
    </section>

    <section class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-[1240px] gap-6 overflow-x-auto px-4 py-4 text-[12px] font-semibold text-slate-600 sm:px-6">
        <button class="shrink-0 text-[#f84464]" @click="scrollToRegistration">Tickets</button><span class="shrink-0">About</span><span class="shrink-0">Venue</span><span class="shrink-0">Organizer</span>
      </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-4 py-8 sm:px-6">
      <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
          <article class="rounded-[14px] bg-white p-6 shadow-sm">
            <h2 class="text-[20px] font-black">About the event</h2>
            <p class="mt-3 whitespace-pre-line text-[13px] leading-6 text-slate-600">{{data.event.description||'Event details will be announced by the organizer.'}}</p>
          </article>
          <article class="rounded-[14px] bg-white p-6 shadow-sm">
            <h2 class="text-[20px] font-black">Event details</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
              <div class="rounded-[10px] bg-[#f8f8f9] p-4"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Date</p><p class="mt-2 text-[13px] font-bold">{{data.event.start_date||'TBA'}}</p></div>
              <div class="rounded-[10px] bg-[#f8f8f9] p-4"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Venue</p><p class="mt-2 text-[13px] font-bold">{{data.event.venue_name||data.event.location||'TBA'}}</p></div>
              <div class="rounded-[10px] bg-[#f8f8f9] p-4"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Format</p><p class="mt-2 text-[13px] font-bold capitalize">{{data.event.format?.replace('_',' ')}}</p></div>
              <div class="rounded-[10px] bg-[#f8f8f9] p-4"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Category</p><p class="mt-2 text-[13px] font-bold">{{data.event.category}}</p></div>
            </div>
          </article>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-[86px] lg:self-start">
          <div class="rounded-[14px] bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Organizer</p>
            <h3 class="mt-2 text-[15px] font-black">{{data.event.organizer?.name||data.event.branding?.brand_name||'Event Organizer'}}</h3>
            <p v-if="data.event.organizer?.email" class="mt-2 text-[11px] text-slate-500">{{data.event.organizer.email}}</p>
          </div>
          <div class="rounded-[14px] bg-[#2b3140] p-5 text-white">
            <p class="text-[10px] font-black uppercase tracking-wider text-rose-300">DigiSangam Secure</p><p class="mt-3 text-[12px] leading-5 text-slate-300">Verified payment state, signed credentials and protected confirmation links are built into every registration.</p>
          </div>
        </aside>
      </div>
    </section>

    <section id="registration-flow" class="bg-white py-9 pb-28 lg:pb-10">
      <div class="mx-auto max-w-[1240px] px-4 sm:px-6">
        <div class="mb-6"><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#f84464]">Registration</p><h2 class="mt-1 text-[24px] font-black tracking-[-.025em]">Book your place</h2></div>

        <div class="mb-6 flex gap-2 overflow-x-auto">
          <div v-for="(label,i) in ['Choose ticket','Your details','Review & pay']" :key="label" class="flex shrink-0 items-center gap-2 rounded-full px-3 py-2 text-[11px] font-bold" :class="i+1<=step?'bg-[#f84464] text-white':'bg-[#f2f2f4] text-slate-400'"><span class="grid h-5 w-5 place-items-center rounded-full bg-white/15">{{i+1}}</span>{{label}}</div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
          <section class="rounded-[14px] border border-slate-200 bg-white p-5 sm:p-6">
            <div v-if="step===1">
              <h3 class="text-[19px] font-black">Select a ticket</h3><p class="mt-1 text-[12px] text-slate-500">Choose one available ticket type.</p>
              <div class="mt-5 grid gap-3">
                <button v-for="ticket in data.tickets" :key="ticket.id" @click="selectedTicket=ticket.id" class="grid gap-3 rounded-[12px] border p-4 text-left transition sm:grid-cols-[1fr_auto] sm:items-center" :class="selectedTicket===ticket.id?'border-[#f84464] bg-rose-50':'border-slate-200 hover:border-rose-200'">
                  <div><div class="flex items-center gap-2"><h4 class="text-[14px] font-black">{{ticket.name}}</h4><span v-if="ticket.quantity-ticket.sold<25" class="rounded-full bg-amber-50 px-2 py-1 text-[9px] font-bold text-amber-700">{{ticket.quantity-ticket.sold}} left</span></div><p class="mt-1 text-[11px] text-slate-500">{{ticket.quantity-ticket.sold}} available · {{ticket.status}}</p></div><strong class="text-[18px]">{{money(ticket.price)}}</strong>
                </button>
                <div v-if="!data.tickets.length" class="rounded-[12px] bg-amber-50 p-5 text-[12px] text-amber-800">No tickets are currently available.</div>
              </div>
            </div>

            <div v-else-if="step===2">
              <h3 class="text-[19px] font-black">{{data.registration.title}}</h3><p class="mt-1 text-[12px] text-slate-500">Fields marked with * are required.</p>
              <div class="mt-6 grid gap-5 sm:grid-cols-2"><template v-for="field in fields" :key="field.id"><PublicRegistrationField v-if="visible(field)" v-model="answers[field.id]" :field="field" :categories="data.registration.categories" :class="['paragraph','textarea','file'].includes(field.type)?'sm:col-span-2':''" @upload="uploadField(field,$event)"/><p v-if="field.type==='file'&&uploadingField===field.id" class="sm:col-span-2 text-[11px] font-semibold text-[#f84464]">Uploading file…</p></template><label class="hidden"><span>Website</span><input v-model="honeypot" tabindex="-1" autocomplete="off"/></label></div>
            </div>

            <div v-else>
              <h3 class="text-[19px] font-black">Review your registration</h3>
              <div class="mt-5 rounded-[12px] border border-slate-200 p-4"><div class="flex items-center justify-between"><div><p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Ticket</p><h4 class="mt-1 text-[14px] font-black">{{selected?.name}}</h4></div><strong class="text-[18px]">{{money(selected?.price)}}</strong></div></div>
              <div class="mt-4 divide-y divide-slate-100 rounded-[12px] border border-slate-200 px-4"><div v-for="field in fields.filter(visible)" :key="field.id" class="flex justify-between gap-5 py-3 text-[12px]"><span class="text-slate-500">{{field.label}}</span><b class="text-right">{{Array.isArray(answers[field.id])?answers[field.id].join(', '):(answers[field.id]||'—')}}</b></div></div>
              <div v-if="Number(selected?.price||0)>0" class="mt-4 rounded-[12px] bg-indigo-50 p-4 text-[12px] leading-5 text-indigo-800"><b>Secure payment.</b> Payment verification and ticket inventory are handled server-side.</div>
              <div v-else class="mt-4 rounded-[12px] bg-emerald-50 p-4 text-[12px] leading-5 text-emerald-800">Free ticket. Your signed credential is issued immediately unless organizer approval is required.</div>
            </div>

            <p v-if="error" class="mt-5 rounded-[10px] bg-rose-50 p-3 text-[12px] font-semibold text-rose-700">{{error}}</p>
            <div class="mt-7 flex justify-between border-t border-slate-100 pt-5"><button v-if="step>1" class="rounded-[8px] border border-slate-200 px-4 py-2.5 text-[12px] font-bold" @click="step--">← Back</button><span v-else></span><button v-if="step<3" class="rounded-[8px] bg-[#f84464] px-5 py-2.5 text-[12px] font-black text-white" @click="next">Continue →</button><button v-else class="rounded-[8px] bg-[#f84464] px-5 py-2.5 text-[12px] font-black text-white disabled:opacity-50" :disabled="busy||previewMode" @click="submit">{{previewMode?'Preview only':(busy?'Processing…':(Number(selected?.price||0)>0?'Register & Pay':'Complete registration'))}}</button></div>
          </section>

          <aside class="rounded-[14px] bg-[#f7f7f8] p-5 lg:sticky lg:top-[86px] lg:self-start">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Booking summary</p><h3 class="mt-2 text-[15px] font-black">{{data.event.name}}</h3>
            <div class="mt-4 space-y-2.5 text-[11px] text-slate-600"><p>◷ {{data.event.start_date||data.event.date}}</p><p>⌖ {{data.event.venue_name||data.event.location}}</p><p>◎ {{data.registration.approval_mode==='manual'?'Manual approval':data.registration.approval_mode==='invite_only'?'Invitation only':'Instant approval'}}</p></div>
            <div v-if="selected" class="mt-5 border-t border-slate-200 pt-4"><div class="flex items-center justify-between text-[12px]"><span>{{selected.name}}</span><b>{{money(selected.price)}}</b></div></div>
          </aside>
        </div>
      </div>
    </section>

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white p-3 shadow-[0_-8px_30px_rgba(15,23,42,.08)] lg:hidden">
      <div class="mx-auto flex max-w-lg items-center justify-between gap-3"><div><p class="text-[9px] font-black uppercase text-slate-400">{{selected?'Selected ticket':'Tickets from'}}</p><p class="text-[13px] font-black">{{selected?selected.name:money(startingPrice,data.event.currency)}}</p></div><button class="rounded-[8px] bg-[#f84464] px-5 py-3 text-[12px] font-black text-white" @click="scrollToRegistration">{{selected?'Continue':'Book now'}}</button></div>
    </div>
  </template>
</main>
</template>
