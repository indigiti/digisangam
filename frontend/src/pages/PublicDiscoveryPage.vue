<script setup>
import {computed,onMounted,onUnmounted,ref,watch} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import {api} from '../services/api'
import MarketplaceHeader from '../components/MarketplaceHeader.vue'
import {setPageSeo,resetPageSeo,canonicalPublicPath} from '../services/seo'

const route=useRoute(),router=useRouter()
const loading=ref(true),refreshing=ref(false),error=ref('')
const payload=ref({events:[],categories:[],cities:[],locations:[],total:0})
const query=ref(String(route.query.q||'')),category=ref(String(route.query.category||'All')),city=ref(String(route.query.city||'All'))
const dateFilter=ref(String(route.query.date||'All')),sort=ref('date'),priceFilter=ref('All'),availableOnly=ref(false)
const page=ref(1),heroIndex=ref(0),limit=24,cityInitialized=ref(false)
const dateOptions=['All','Today','Tomorrow','This weekend']
const money=(n,c='INR')=>Number(n||0)===0?'Free':new Intl.NumberFormat('en-IN',{style:'currency',currency:c,maximumFractionDigits:0}).format(Number(n||0))
const catalog=computed(()=>payload.value.events||[])
const categories=computed(()=>payload.value.categories||[])
const cities=computed(()=>{
  const entries=payload.value.cities?.length?payload.value.cities:(payload.value.locations||[]).map(x=>String(x||'').split(',')[0].trim())
  return [...new Set(entries.filter(Boolean))]
})
function eventCity(e){return String(e.city||e.location||'').split(',')[0].trim()}
function dateMatches(value,end,mode){
  if(mode==='All')return true
  if(!value)return false
  const today=new Date(),d=new Date(today.getFullYear(),today.getMonth(),today.getDate())
  const start=String(value).slice(0,10),finish=String(end||value).slice(0,10)
  if(mode==='Today'){const v=localISO(d);return start<=v&&finish>=v}
  if(mode==='Tomorrow'){d.setDate(d.getDate()+1);const v=localISO(d);return start<=v&&finish>=v}
  if(mode==='This weekend'){
    const day=d.getDay(),sat=new Date(d);sat.setDate(d.getDate()+((6-day+7)%7))
    if(day===0)sat.setDate(d.getDate()-1)
    const sun=new Date(sat);sun.setDate(sat.getDate()+1)
    return finish>=localISO(sat)&&start<=localISO(sun)
  }
  return true
}
function localISO(date){return [date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-')}
const filtered=computed(()=>{
  const q=query.value.trim().toLowerCase()
  const list=catalog.value.filter(e=>{
    if(q&&![e.name,e.headline,e.description,e.category,e.type,e.venue_name,e.location].join(' ').toLowerCase().includes(q))return false
    if(category.value!=='All'&&e.category!==category.value)return false
    if(city.value!=='All'&&eventCity(e)!==city.value)return false
    if(!dateMatches(e.start_date,e.end_date,dateFilter.value))return false
    if(availableOnly.value&&Number(e.available||0)<=0)return false
    if(priceFilter.value!=='All'){
      if(!Number(e.ticket_count||0)||String(e.currency||'INR')!=='INR')return false
      const p=Number(e.min_price||0)
      if(priceFilter.value==='Free'&&p!==0)return false
      if(priceFilter.value==='Under ₹500'&&p>=500)return false
      if(priceFilter.value==='Under ₹1000'&&p>=1000)return false
    }
    return true
  })
  return [...list].sort((a,b)=>{
    if(sort.value==='price_asc')return Number(a.min_price||0)-Number(b.min_price||0)
    if(sort.value==='price_desc')return Number(b.min_price||0)-Number(a.min_price||0)
    return String(a.start_date||'9999-12-31').localeCompare(String(b.start_date||'9999-12-31'))
  })
})
const heroEvents=computed(()=>filtered.value.slice(0,4))
const featured=computed(()=>heroEvents.value[Math.min(heroIndex.value,Math.max(0,heroEvents.value.length-1))]||null)
const cards=computed(()=>filtered.value.slice(0,12))
const more=computed(()=>filtered.value.slice(12))
const filtersActive=computed(()=>query.value.trim()||city.value!=='All'||category.value!=='All'||dateFilter.value!=='All'||priceFilter.value!=='All'||availableOnly.value)
const allCount=computed(()=>payload.value.total??filtered.value.length)
function fullDate(value){if(!value)return 'Date to be announced';const date=new Date(String(value).slice(0,10)+'T12:00:00');return new Intl.DateTimeFormat('en-IN',{weekday:'short',day:'numeric',month:'short',year:'numeric'}).format(date)}
function shortDate(value){if(!value)return 'Date TBA';const d=new Date(String(value).slice(0,10)+'T12:00:00');return new Intl.DateTimeFormat('en-IN',{day:'numeric',month:'short'}).format(d)}
function poster(event){return event.branding?.poster_url||event.branding?.cover_url||''}
function background(event){return 'linear-gradient(140deg,'+(event.branding?.primary_color||'#25113e')+','+(event.branding?.secondary_color||'#9c428f')+')'}
function clearFilters(){query.value='';category.value='All';dateFilter.value='All';sort.value='date';priceFilter.value='All';availableOnly.value=false;city.value='All'}
function categoryTone(i){return ['#802899','#d84e8b','#384dbc','#c36139','#177b80','#683da6'][i%6]}
function categorySymbol(name){const n=String(name).toLowerCase();if(/music|concert/.test(n))return '♫';if(/comedy/.test(n))return '☺';if(/workshop|craft/.test(n))return '✦';if(/festival/.test(n))return '✳';if(/business|conference/.test(n))return '◆';return '✧'}
function selectCategory(value){category.value=value;heroIndex.value=0}
function nextHero(delta){if(heroEvents.value.length)heroIndex.value=(heroIndex.value+delta+heroEvents.value.length)%heroEvents.value.length}
function priceArg(){if(priceFilter.value==='Free')return 0;if(priceFilter.value==='Under ₹500')return 499;if(priceFilter.value==='Under ₹1000')return 999;return ''}
let timer=null,requestVersion=0
async function load(append=false){
  const current=++requestVersion
  if(!append){page.value=1;if(!payload.value.events.length)loading.value=true;else refreshing.value=true}
  error.value=''
  try{
    const data=await api.publicEvents({
      q:query.value.trim(),category:category.value==='All'?'':category.value,
      city:city.value==='All'?'':city.value,
      date:{Today:'today',Tomorrow:'tomorrow','This weekend':'weekend'}[dateFilter.value]||'',
      max_price:priceArg(),currency:priceFilter.value==='All'?'':'INR',available_only:availableOnly.value?1:'',
      sort:sort.value,per_page:limit,page:page.value
    })
    if(current!==requestVersion)return
    if(append){
      const original=payload.value.events||[]
      const existing=new Set(original.map(x=>x.id))
      payload.value={...data,events:[...original,...(data.events||[]).filter(e=>!existing.has(e.id))]}
    }else payload.value=data
    if(!cityInitialized.value){
      cityInitialized.value=true
      if(route.query.city)city.value=String(route.query.city)
    }
    heroIndex.value=0
  }catch(e){if(current===requestVersion)error.value=e.message||'Unable to load events.'}
  finally{if(current===requestVersion){loading.value=false;refreshing.value=false}}
}
function scheduleLoad(){
  if(timer!==null)clearTimeout(timer)
  timer=setTimeout(()=>{page.value=1;load()},280)
  router.replace({path:'/discover',query:{
    ...(query.value.trim()?{q:query.value.trim()}:{}),
    ...(city.value!=='All'?{city:city.value}:{}),
    ...(category.value!=='All'?{category:category.value}:{}),
    ...(dateFilter.value!=='All'?{date:dateFilter.value}:{})
  }}).catch(()=>{})
}
watch([query,category,city,dateFilter,priceFilter,availableOnly,sort],scheduleLoad)
function loadMore(){if(loading.value||refreshing.value||!payload.value.has_more)return;page.value++;load(true)}
function searchNow(){if(timer!==null)clearTimeout(timer);page.value=1;load()}
onMounted(()=>{
  setPageSeo({title:'Explore Events & Experiences | DigiSangam',description:'Find live events, comedy, workshops and unique experiences. Browse verified events by city, date and category.',canonical:canonicalPublicPath('discover')})
  load()
})
onUnmounted(()=>{requestVersion++;if(timer!==null)clearTimeout(timer);resetPageSeo()})
</script>

<template>
<main class="marketplace-page">
  <MarketplaceHeader v-model:query="query" v-model:city="city" :cities="cities" @search="searchNow"/>

  <nav class="marketplace-categories" aria-label="Event categories">
    <div class="marketplace-container marketplace-categories__inner">
      <button type="button" :aria-pressed="category==='All'" @click="selectCategory('All')">All experiences</button>
      <button v-for="name in categories" :key="name" type="button" :aria-pressed="category===name" @click="selectCategory(name)">{{name}}</button>
      <RouterLink to="/access" class="marketplace-categories__organizer">List your event <span aria-hidden="true">↗</span></RouterLink>
    </div>
  </nav>

  <div v-if="loading" class="marketplace-container marketplace-loading" aria-label="Loading events">
    <div class="marketplace-loading__hero"></div>
    <div class="marketplace-loading__grid"><div v-for="n in 6" :key="n"></div></div>
  </div>
  <section v-else-if="error&&!catalog.length" class="marketplace-container marketplace-error" role="alert">
    <div class="marketplace-error__icon">!</div><h1>We couldn't load events</h1><p>{{error}}</p><button type="button" @click="searchNow">Try again</button>
  </section>

  <template v-else>
    <section class="marketplace-hero-shell">
      <div class="marketplace-container">
        <div class="marketplace-intro"><span class="marketplace-intro__eyebrow"><span class="marketplace-intro__spark">✦</span> Discover what moves you</span>
          <h1>Go out. <em>Feel more.</em></h1>
          <p>Find a story worth stepping out for — live shows, workshops, festivals and experiences in {{city==='All'?'your city':city}}.</p>
        </div>
        <div v-if="featured" class="marketplace-hero">
          <RouterLink :to="'/e/'+featured.id" class="marketplace-hero__main">
            <div class="marketplace-hero__photo" :style="{background:background(featured)}"><img v-if="featured.branding?.cover_url||poster(featured)" :src="featured.branding?.cover_url||poster(featured)" :alt="featured.name" decoding="async"/></div>
            <div class="marketplace-hero__gradient"></div>
            <div class="marketplace-hero__text">
              <span class="marketplace-hero__label">FEATURED EXPERIENCE <span aria-hidden="true">✦</span> {{featured.category}}</span>
              <h2>{{featured.headline||featured.name}}</h2>
              <p>{{shortDate(featured.start_date)}} <span>·</span> {{eventCity(featured)||'Online'}} <span>·</span> {{featured.venue_name||featured.type}}</p>
              <span class="marketplace-hero__cta">Explore event <span aria-hidden="true">↗</span></span>
            </div>
          </RouterLink>
          <div class="marketplace-hero__controls" v-if="heroEvents.length>1" aria-label="Featured event controls">
            <button type="button" aria-label="Previous featured event" @click="nextHero(-1)">←</button>
            <span>{{heroIndex+1}} / {{heroEvents.length}}</span>
            <button type="button" aria-label="Next featured event" @click="nextHero(1)">→</button>
          </div>
        </div>
        <div v-else class="marketplace-hero marketplace-hero--empty">
          <div class="marketplace-hero__empty"><span>✦</span><h2>More memorable days start here.</h2><p>No published experiences match these preferences yet. Explore all events or create your own.</p><button @click="clearFilters" type="button">Explore all events</button></div>
        </div>
      </div>
    </section>

    <section v-if="categories.length" class="marketplace-container marketplace-section marketplace-category-section">
      <div class="marketplace-section__heading"><div><span class="marketplace-section__eyebrow">PICK YOUR MOOD</span><h2>What are you in the mood for?</h2></div></div>
      <div class="marketplace-category-grid">
        <button v-for="(name,i) in categories.slice(0,8)" :key="name" type="button" class="marketplace-category-card" :aria-pressed="category===name" :style="{'--category-color':categoryTone(i)}" @click="selectCategory(name)">
          <span class="marketplace-category-card__art" aria-hidden="true">{{categorySymbol(name)}}</span>
          <span>{{name}}</span><small>Explore <span aria-hidden="true">↗</span></small>
        </button>
      </div>
    </section>

    <section class="marketplace-container marketplace-section" id="explore-events">
      <div class="marketplace-section__heading"><div><span class="marketplace-section__eyebrow">DISCOVER YOUR NEXT PLAN</span><h2>Explore experiences <span v-if="city!=='All'">in {{city}}</span></h2><p>Only published DigiSangam events. No invented listings.</p></div>
        <span class="marketplace-result-count">{{allCount}} matching event{{allCount===1?'':'s'}}</span>
      </div>
      <div class="marketplace-filterbar" aria-label="Filter events">
        <div class="marketplace-filterbar__dates" role="group" aria-label="Date filter">
          <button v-for="option in dateOptions" :key="option" type="button" :aria-pressed="dateFilter===option" @click="dateFilter=option">{{option}}</button>
        </div>
        <div class="marketplace-filterbar__secondary">
          <label><span class="sr-only">Price filter</span><select v-model="priceFilter" aria-label="Price filter"><option>All</option><option>Free</option><option>Under ₹500</option><option>Under ₹1000</option></select></label>
          <label><span class="sr-only">Sort events</span><select v-model="sort" aria-label="Sort events"><option value="date">Soonest first</option><option value="price_asc">Price: Low to high</option><option value="price_desc">Price: High to low</option></select></label>
          <label class="marketplace-filterbar__availability"><input v-model="availableOnly" type="checkbox"/>Tickets available</label>
          <button v-if="filtersActive" type="button" class="marketplace-filterbar__reset" @click="clearFilters">Clear filters</button>
        </div>
      </div>
      <p v-if="error" class="marketplace-inline-error" role="alert">{{error}} <button type="button" @click="searchNow">Retry</button></p>
      <div v-if="filtered.length" class="marketplace-event-grid" :class="{'is-refreshing':refreshing}">
        <RouterLink v-for="event in cards" :key="event.id" class="marketplace-event-card" :to="'/e/'+event.id">
          <div class="marketplace-event-card__media" :style="{background:background(event)}">
            <img v-if="poster(event)" :src="poster(event)" :alt="event.name" loading="lazy" decoding="async"/>
            <span class="marketplace-event-card__date">{{shortDate(event.start_date)}}</span>
            <span v-if="event.available<=0&&event.ticket_count>0" class="marketplace-event-card__sold">SOLD OUT</span>
          </div>
          <div class="marketplace-event-card__details">
            <span class="marketplace-event-card__tag">{{event.category||event.type}}</span>
            <h3>{{event.name}}</h3>
            <p class="marketplace-event-card__meta">{{fullDate(event.start_date)}}</p>
            <p class="marketplace-event-card__meta">{{event.venue_name||eventCity(event)||'Venue TBA'}}<span v-if="event.venue_name&&eventCity(event)">, {{eventCity(event)}}</span></p>
            <p class="marketplace-event-card__price">{{event.ticket_count>0?money(event.min_price,event.currency):'Tickets TBA'}} <span v-if="event.ticket_count>0">onwards</span></p>
          </div>
        </RouterLink>
      </div>
      <div v-else class="marketplace-no-results"><span aria-hidden="true">✦</span><h3>No experiences found</h3><p>Try another category, city or date. New events appear when organizers publish them.</p><button type="button" @click="clearFilters">Show all events</button></div>
      <div v-if="more.length" class="marketplace-more-grid">
        <RouterLink v-for="event in more" :key="event.id" :to="'/e/'+event.id" class="marketplace-more-card">
          <div :style="{background:background(event)}"><img v-if="poster(event)" :src="poster(event)" :alt="event.name" loading="lazy" decoding="async"/></div>
          <section><span>{{event.category}}</span><h3>{{event.name}}</h3><p>{{shortDate(event.start_date)}} · {{eventCity(event)}}</p><strong>{{event.ticket_count>0?money(event.min_price,event.currency):'Tickets TBA'}}</strong></section>
          <b aria-hidden="true">↗</b>
        </RouterLink>
      </div>
      <button v-if="payload.has_more" type="button" class="marketplace-load-more" :disabled="refreshing" @click="loadMore">{{refreshing?'Loading…':'Show more experiences ↓'}}</button>
    </section>

    <section class="marketplace-organizer-band">
      <div class="marketplace-container marketplace-organizer-band__inner">
        <div><span>MADE FOR EVENT MAKERS</span><h2>Bring your next big idea to life.</h2><p>From discovery and ticketing to signed QR check-in and reporting. Run it with DigiSangam.</p></div>
        <RouterLink to="/access">Start organizing <span aria-hidden="true">↗</span></RouterLink>
      </div>
    </section>
    <footer class="marketplace-footer">
      <div class="marketplace-container"><div><b>DigiSangam</b><span>Discover. Book. Experience.</span></div><nav aria-label="Footer"><RouterLink to="/discover">Explore events</RouterLink><RouterLink to="/access">Organizer workspace</RouterLink></nav></div>
    </footer>
  </template>
</main>
</template>

<style scoped>
.marketplace-page{background:#f9f8fb;color:#241d2a;min-height:100vh;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
.marketplace-container{max-width:1330px;margin:auto;padding-left:27px;padding-right:27px}
.marketplace-categories{background:#241630;color:#cfbedb;border-bottom:1px solid rgba(255,255,255,.1)}
.marketplace-categories__inner{display:flex;gap:8px;overflow-x:auto;align-items:center;min-height:48px;scrollbar-width:none}.marketplace-categories__inner button{padding:11px 16px;border-radius:9px;font-size:12px;font-weight:700;white-space:nowrap}.marketplace-categories__inner button[aria-pressed="true"],.marketplace-categories__inner button:hover{color:#fff;background:#493053}.marketplace-categories__organizer{margin-left:auto;white-space:nowrap;font-size:12px;font-weight:700;color:#f5c6e1}
.marketplace-hero-shell{background:radial-gradient(ellipse at 20% 5%,#3b244a 0%,#21132e 65%,#1a1127);padding:42px 0 56px}
.marketplace-intro{text-align:center;color:#fff;margin:0 auto 30px;max-width:760px}.marketplace-intro__eyebrow{font-size:11px;font-weight:850;letter-spacing:.15em;text-transform:uppercase;color:#e1b7db}.marketplace-intro__spark{color:#ffb2d9;margin-right:6px}.marketplace-intro h1{font-size:clamp(34px,5vw,67px);line-height:1.08;font-weight:850;letter-spacing:-.066em;margin:12px 0 12px}.marketplace-intro h1 em{font-style:normal;color:#f18fbf}.marketplace-intro p{font-size:14px;color:#c1b0cb;line-height:1.7}
.marketplace-hero{position:relative;overflow:hidden;border-radius:23px;max-height:415px;min-height:370px;background:#322047;box-shadow:0 24px 65px rgba(4,2,9,.3)}
.marketplace-hero__main{display:block;height:100%;min-height:370px;position:relative;color:#fff;isolation:isolate}.marketplace-hero__photo{position:absolute;inset:0;z-index:-3}.marketplace-hero__photo img{width:100%;height:100%;object-fit:cover}.marketplace-hero__gradient{position:absolute;inset:0;z-index:-2;background:linear-gradient(90deg,rgba(17,9,27,.94),rgba(24,10,34,.78) 48%,rgba(26,12,32,.03))}.marketplace-hero__text{max-width:650px;min-height:370px;padding:51px 57px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center}.marketplace-hero__label{font-size:11px;font-weight:850;letter-spacing:.13em;text-transform:uppercase;color:#fac2de}.marketplace-hero__text h2{font-size:clamp(30px,4.5vw,53px);line-height:1.07;letter-spacing:-.045em;font-weight:850;margin:19px 0 18px;text-wrap:balance}.marketplace-hero__text p{font-size:13px;font-weight:600;line-height:1.7;color:#e3d7e6}.marketplace-hero__text p span{padding:0 8px;color:#d8a8ca}.marketplace-hero__cta{display:flex;align-items:center;gap:22px;background:#fff;color:#2c1737;padding:13px 18px;border-radius:11px;margin-top:26px;font-size:13px;font-weight:850}
.marketplace-hero__main:hover .marketplace-hero__cta{background:#ffcae4}.marketplace-hero__controls{position:absolute;bottom:17px;right:25px;display:flex;align-items:center;gap:15px;padding:5px 7px;border-radius:28px;background:rgba(13,7,22,.6);backdrop-filter:blur(9px);color:#fff;font-size:11px;font-weight:750}.marketplace-hero__controls button{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2)}
.marketplace-hero--empty{display:grid;place-items:center;padding:35px}.marketplace-hero__empty{text-align:center;color:#fff;max-width:430px}.marketplace-hero__empty>span{font-size:35px;color:#ff9ccc}.marketplace-hero__empty h2{font-size:33px;font-weight:800;letter-spacing:-.045em;margin:12px 0}.marketplace-hero__empty p{color:#ddc8e3;font-size:13px;line-height:1.7}.marketplace-hero__empty button{margin-top:20px;background:#fff;color:#291a33;padding:11px 18px;border-radius:10px;font-weight:800}
.marketplace-section{padding-top:53px;padding-bottom:35px}.marketplace-section__heading{display:flex;align-items:end;justify-content:space-between;gap:15px;margin-bottom:24px}.marketplace-section__eyebrow{font-size:10px;font-weight:850;letter-spacing:.13em;color:#9f518c}.marketplace-section__heading h2{margin-top:7px;font-size:clamp(22px,2.6vw,33px);letter-spacing:-.045em;font-weight:850;line-height:1.16}.marketplace-section__heading p{color:#8c8391;font-size:12px;margin-top:9px}.marketplace-result-count{font-size:12px;font-weight:750;color:#836e8c}
.marketplace-category-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:13px}.marketplace-category-card{min-height:152px;border-radius:17px;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:end;align-items:start;gap:7px;color:#fff;padding:19px;background:linear-gradient(150deg,var(--category-color),#29132f);text-align:left;transition:transform .2s ease,box-shadow .2s ease}.marketplace-category-card:hover{transform:translateY(-3px);box-shadow:0 15px 30px rgba(30,15,50,.16)}.marketplace-category-card[aria-pressed="true"]{outline:3px solid #d587b5;outline-offset:2px}.marketplace-category-card>span:nth-child(2){font-size:15px;font-weight:850;line-height:1.2}.marketplace-category-card small{font-size:11px;color:#e8c8dc;font-weight:700}.marketplace-category-card__art{position:absolute;right:12px;top:-14px;font-family:Georgia,serif;font-size:105px;line-height:1;color:rgba(255,255,255,.19);transform:rotate(-12deg)}
.marketplace-filterbar{background:#fff;border:1px solid #eee9f0;border-radius:16px;padding:13px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px;box-shadow:0 4px 16px rgba(35,18,55,.03)}
.marketplace-filterbar__dates,.marketplace-filterbar__secondary{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.marketplace-filterbar__dates button{padding:10px 14px;border-radius:9px;border:1px solid transparent;font-size:12px;font-weight:800;color:#594e61}.marketplace-filterbar__dates button[aria-pressed="true"]{background:#332040;color:#fff}.marketplace-filterbar__dates button:hover:not([aria-pressed="true"]){background:#f7edf6}
.marketplace-filterbar__secondary select{max-width:175px;border:1px solid #e9e1ea;border-radius:9px;padding:9px 11px;color:#4e4059;font-size:12px;font-weight:700;background:#fff;cursor:pointer}
.marketplace-filterbar__availability{display:flex;align-items:center;gap:7px;color:#5f5363;font-size:11px;font-weight:750}.marketplace-filterbar__availability input{accent-color:#823e83}.marketplace-filterbar__reset{font-size:12px;font-weight:800;color:#a23980}
.marketplace-event-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:25px 17px;transition:opacity .18s}.marketplace-event-grid.is-refreshing{opacity:.57}
.marketplace-event-card{overflow:hidden;background:#fff;border:1px solid #ece5ee;border-radius:17px;box-shadow:0 7px 22px rgba(27,17,41,.045);transition:transform .18s,box-shadow .18s;display:block;min-width:0}.marketplace-event-card:hover{transform:translateY(-4px);box-shadow:0 17px 45px rgba(27,17,41,.12)}
.marketplace-event-card__media{position:relative;aspect-ratio:1.16/1;overflow:hidden}.marketplace-event-card__media img{width:100%;height:100%;object-fit:cover;transition:transform .35s}.marketplace-event-card:hover img{transform:scale(1.035)}.marketplace-event-card__date{position:absolute;bottom:11px;left:12px;background:#fff;color:#392942;border-radius:8px;padding:7px 9px;font-size:11px;font-weight:850}.marketplace-event-card__sold{position:absolute;top:10px;right:10px;border-radius:7px;background:#21142b;color:#fff;padding:6px 8px;font-size:9px;font-weight:800;letter-spacing:.06em}
.marketplace-event-card__details{padding:16px 16px 19px}.marketplace-event-card__tag{font-size:10px;font-weight:850;letter-spacing:.08em;text-transform:uppercase;color:#a13d85}.marketplace-event-card__details h3{font-size:16px;font-weight:850;letter-spacing:-.027em;line-height:1.32;min-height:41px;margin:7px 0 11px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.marketplace-event-card__meta{font-size:11px;color:#807785;line-height:1.5;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin:3px 0}.marketplace-event-card__price{margin-top:15px;border-top:1px solid #f1ebf1;padding-top:13px;font-size:16px;font-weight:850;color:#271b2e}.marketplace-event-card__price span{font-size:11px;color:#948997;font-weight:600;margin-left:4px}
.marketplace-more-grid{margin-top:25px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.marketplace-more-card{display:flex;min-width:0;align-items:center;gap:15px;padding:12px;border:1px solid #eae3ec;background:#fff;border-radius:14px}.marketplace-more-card>div{width:90px;aspect-ratio:4/3;flex:none;overflow:hidden;border-radius:10px}.marketplace-more-card img{width:100%;height:100%;object-fit:cover}.marketplace-more-card section{min-width:0;flex:1}.marketplace-more-card section span{color:#9f4c89;font-size:10px;font-weight:800}.marketplace-more-card section h3{font-weight:850;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.marketplace-more-card section p{font-size:11px;color:#877a8b;margin-top:3px}.marketplace-more-card section strong{font-size:12px;margin-top:4px;display:block}.marketplace-more-card>b{padding:10px;color:#82517b}
.marketplace-no-results{border:1px dashed #ddcedf;text-align:center;padding:70px 18px;background:#fff;border-radius:18px}.marketplace-no-results>span{font-size:34px;color:#c089b5}.marketplace-no-results h3{font-size:21px;font-weight:850;margin-top:8px}.marketplace-no-results p{font-size:13px;color:#8a7d8c;max-width:450px;margin:10px auto}.marketplace-no-results button{background:#34203e;color:#fff;border-radius:10px;padding:11px 20px;margin-top:12px;font-size:12px;font-weight:800}
.marketplace-load-more{display:block;margin:27px auto;border:1px solid #dac6dc;border-radius:12px;padding:12px 24px;font-size:12px;font-weight:850;color:#7c4678}.marketplace-load-more:hover{background:#f9edf8}.marketplace-load-more:disabled{opacity:.5}
.marketplace-organizer-band{margin-top:50px;background:linear-gradient(112deg,#21152d,#66306b 70%,#923f7b);color:#fff}.marketplace-organizer-band__inner{padding-top:55px;padding-bottom:55px;display:flex;align-items:center;justify-content:space-between;gap:26px}.marketplace-organizer-band__inner span{font-size:10px;font-weight:850;letter-spacing:.13em;color:#f1b9df}.marketplace-organizer-band__inner h2{font-size:clamp(23px,3vw,37px);letter-spacing:-.04em;font-weight:850;margin:8px 0}.marketplace-organizer-band__inner p{max-width:650px;color:#e1c9e3;font-size:13px;line-height:1.6}.marketplace-organizer-band__inner>a{border-radius:12px;background:#fff;color:#422044;font-weight:850;padding:14px 22px;font-size:13px;white-space:nowrap}
.marketplace-footer{background:#1c1524;color:#c6b8ca}.marketplace-footer .marketplace-container{display:flex;align-items:center;justify-content:space-between;gap:20px;min-height:95px}.marketplace-footer b{color:#fff;font-size:18px;letter-spacing:-.04em}.marketplace-footer span{display:block;margin-top:4px;font-size:11px}.marketplace-footer nav{display:flex;gap:23px;font-size:12px;font-weight:700}
.marketplace-loading{padding-top:30px;padding-bottom:70px}.marketplace-loading__hero{height:380px;border-radius:22px;background:#e9e3ec;animation:pulse 1.7s infinite}.marketplace-loading__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-top:35px}.marketplace-loading__grid div{height:290px;background:#e9e3ec;border-radius:16px;animation:pulse 1.7s infinite}
.marketplace-error{text-align:center;padding-top:110px;padding-bottom:150px}.marketplace-error__icon{font-size:40px;color:#ac4c88}.marketplace-error h1{font-size:26px;font-weight:850;margin-top:8px}.marketplace-error p{color:#6f6276;margin:10px}.marketplace-error button{background:#352340;border-radius:9px;color:#fff;font-weight:750;padding:11px 22px;margin-top:13px}.marketplace-inline-error{font-size:12px;color:#b91c1c;margin-bottom:12px}.marketplace-inline-error button{text-decoration:underline}
@keyframes pulse{50%{opacity:.55}}
@media(max-width:1100px){.marketplace-category-grid{grid-template-columns:repeat(4,1fr)}.marketplace-event-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:770px){.marketplace-container{padding-left:16px;padding-right:16px}.marketplace-hero-shell{padding:32px 0}.marketplace-intro h1{font-size:39px}.marketplace-intro p{font-size:12px}.marketplace-hero,.marketplace-hero__main{min-height:330px}.marketplace-hero__text{min-height:330px;padding:30px}.marketplace-hero__text h2{font-size:31px}.marketplace-hero__gradient{background:linear-gradient(90deg,rgba(14,6,25,.9),rgba(14,6,25,.43))}.marketplace-hero__controls{right:12px;bottom:11px}.marketplace-categories__organizer{display:none}.marketplace-section{padding-top:35px;padding-bottom:20px}.marketplace-category-grid{grid-template-columns:repeat(3,1fr)}.marketplace-event-grid{grid-template-columns:repeat(2,1fr);gap:13px}.marketplace-more-grid{grid-template-columns:1fr}.marketplace-organizer-band__inner{flex-direction:column;align-items:start}.marketplace-loading__grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:470px){.marketplace-hero__text h2{font-size:28px}.marketplace-category-grid{grid-template-columns:repeat(2,1fr)}.marketplace-category-card{min-height:125px}.marketplace-event-card__media{aspect-ratio:3/3.4}.marketplace-event-card__details{padding:12px}.marketplace-event-card__details h3{font-size:13px;min-height:36px}.marketplace-event-card__price{font-size:13px}.marketplace-event-card__meta{font-size:10px}.marketplace-filterbar__dates button{padding:9px 10px;font-size:11px}.marketplace-result-count{display:none}.marketplace-footer .marketplace-container{align-items:start;flex-direction:column;padding-top:22px;padding-bottom:22px;min-height:0}}
@media(prefers-reduced-motion:reduce){.marketplace-event-card,.marketplace-category-card,.marketplace-event-card__media img{transition:none}.marketplace-event-card:hover,.marketplace-category-card:hover{transform:none}}
</style>
