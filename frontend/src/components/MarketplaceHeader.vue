<script setup>
import {computed,onBeforeUnmount,onMounted,ref} from 'vue'
const props=defineProps({
  query:{type:String,default:''},
  city:{type:String,default:'All'},
  cities:{type:Array,default:()=>[]},
  context:{type:String,default:'discovery'}
})
const emit=defineEmits(['update:query','update:city','search'])
const cityOpen=ref(false),cityQuery=ref('')
const visibleCities=computed(()=>{
  const q=cityQuery.value.toLowerCase().trim()
  return ['All',...props.cities.filter(x=>x&&x!=='All')].filter(x=>!q||x.toLowerCase().includes(q))
})
const cityLabel=computed(()=>props.city==='All'?'All cities':props.city)
function chooseCity(city){emit('update:city',city);cityOpen.value=false;cityQuery.value=''}
function onEscape(event){if(event.key==='Escape')cityOpen.value=false}
onMounted(()=>window.addEventListener('keydown',onEscape))
onBeforeUnmount(()=>window.removeEventListener('keydown',onEscape))
</script>

<template>
  <header class="market-header" aria-label="DigiSangam marketplace">
    <div class="market-header__main">
      <RouterLink to="/discover" class="market-header__brand" aria-label="DigiSangam home">
        <span class="market-header__logo" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
        <span class="market-header__word">digi<span>sangam</span><small>EXPERIENCES</small></span>
      </RouterLink>
      <nav class="market-header__nav" aria-label="Marketplace">
        <RouterLink to="/discover" class="market-header__nav-link" :class="{'is-current':context==='discovery'}">For you</RouterLink>
        <RouterLink to="/discover" class="market-header__nav-link" :class="{'is-current':context==='event'}">Events</RouterLink>
      </nav>
      <form class="market-header__search" role="search" @submit.prevent="emit('search')">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m16 16 4.5 4.5" stroke-linecap="round"/></svg>
        <input :value="query" @input="emit('update:query',$event.target.value)" type="search" autocomplete="off" aria-label="Search experiences" placeholder="Search for events, conferences, workshops and more"/>
        <button type="submit" aria-label="Submit event search">Search</button>
      </form>
      <button type="button" class="market-header__city" aria-haspopup="dialog" :aria-expanded="cityOpen" @click="cityOpen=true">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
        <span><small>Explore in</small><strong>{{cityLabel}}</strong></span>
        <span aria-hidden="true" class="market-header__chevron">⌄</span>
      </button>
      <RouterLink to="/access" class="market-header__account">For organizers <span aria-hidden="true">↗</span></RouterLink>
    </div>
    <div class="market-header__mobile-search">
      <form role="search" @submit.prevent="emit('search')">
        <label for="market-mobile-search" class="sr-only">Search experiences</label>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m16 16 4.5 4.5"/></svg>
        <input id="market-mobile-search" :value="query" type="search" placeholder="Search events and experiences" @input="emit('update:query',$event.target.value)"/>
        <button type="submit" aria-label="Search">Go</button>
      </form>
    </div>
  </header>

  <div v-if="cityOpen" class="market-city-overlay" role="presentation" @click.self="cityOpen=false">
    <section role="dialog" aria-modal="true" aria-labelledby="market-city-title" class="market-city-dialog">
      <div class="market-city-dialog__heading"><div><p class="market-city-dialog__eyebrow">Your city, your experiences</p><h2 id="market-city-title">Choose your city</h2></div><button type="button" aria-label="Close city selector" @click="cityOpen=false">✕</button></div>
      <div class="market-city-dialog__search">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m16 16 4.5 4.5"/></svg>
        <input v-model="cityQuery" aria-label="Search city" autofocus type="search" placeholder="Search cities"/>
      </div>
      <p class="market-city-dialog__caption">Only cities with published DigiSangam events appear here. We do not infer your location.</p>
      <div class="market-city-dialog__options">
        <button v-for="item in visibleCities" :key="item" type="button" :aria-pressed="city===item" @click="chooseCity(item)"><span class="market-city-dialog__pin" aria-hidden="true">⌖</span>{{item==='All'?'All cities':item}}<span v-if="city===item" class="market-city-dialog__selected">✓</span></button>
        <p v-if="!visibleCities.length" class="market-city-dialog__empty">No cities match your search.</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.market-header{background:#191126;color:#fff;position:relative;z-index:50;border-bottom:1px solid rgba(255,255,255,.09)}
.market-header__main{height:84px;max-width:1380px;margin:auto;padding:0 28px;display:flex;align-items:center;gap:30px}
.market-header__brand{display:flex;align-items:center;gap:10px;flex:none;min-width:194px}
.market-header__logo{display:grid;grid-template-columns:repeat(2,10px);grid-template-rows:repeat(2,10px);gap:3px;transform:rotate(-12deg)}
.market-header__logo span{background:#ff5a9d;border-radius:4px}.market-header__logo span:nth-child(2){background:#c892ff}.market-header__logo span:nth-child(3){background:#f7c35a}
.market-header__word{font-size:22px;font-weight:850;line-height:1;letter-spacing:-.065em}.market-header__word>span{font-weight:600}.market-header__word small{display:block;font-size:7px;font-weight:800;letter-spacing:.25em;margin-top:5px;color:#b7a9ca}
.market-header__nav{display:flex;align-items:center;gap:6px;align-self:stretch}.market-header__nav-link{display:flex;align-items:center;padding:0 15px;color:#b4a7c3;font-size:13px;font-weight:700;border-bottom:2px solid transparent;white-space:nowrap}.market-header__nav-link:hover,.market-header__nav-link.is-current{color:#fff;border-bottom-color:#ed6baa}
.market-header__search{display:flex;align-items:center;gap:10px;flex:1;min-width:0;max-width:455px;height:46px;border:1px solid rgba(255,255,255,.16);border-radius:13px;padding:0 7px 0 15px;background:rgba(255,255,255,.09)}.market-header__search:focus-within{border-color:#cf9af5}
.market-header__search svg,.market-header__mobile-search svg{height:18px;width:18px;flex:none;color:#b4a7c3}
.market-header__search input,.market-header__mobile-search input{color:#fff;font-size:12px;min-width:0;flex:1;outline:0;background:transparent}.market-header__search input::placeholder,.market-header__mobile-search input::placeholder{color:#b4a7c3}
.market-header__search button{border-radius:9px;background:#f9e9f7;color:#392346;font-size:11px;font-weight:800;padding:9px 13px}
.market-header__city{display:flex;align-items:center;gap:9px;flex:none;max-width:182px;text-align:left;color:#fff}.market-header__city svg{width:19px;height:19px;color:#e8b2fb;flex:none}.market-header__city small{display:block;color:#b4a7c3;font-size:10px;font-weight:600}.market-header__city strong{display:block;max-width:105px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;margin-top:2px}.market-header__chevron{color:#b4a7c3}
.market-header__account{border-radius:12px;padding:12px 13px;border:1px solid rgba(255,255,255,.18);font-size:11px;font-weight:800;white-space:nowrap}.market-header__account:hover{background:rgba(255,255,255,.08)}
.market-header__mobile-search{display:none}
.market-city-overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding:8vh 15px 20px;background:rgba(14,8,25,.7);backdrop-filter:blur(8px)}
.market-city-dialog{max-width:510px;width:100%;max-height:80vh;overflow:auto;border-radius:24px;background:#fff;color:#23172e;box-shadow:0 35px 110px rgba(0,0,0,.4);padding:26px}
.market-city-dialog__heading{display:flex;justify-content:space-between;align-items:start;gap:20px}.market-city-dialog__heading h2{font-size:25px;line-height:1.15;font-weight:850;letter-spacing:-.04em;margin-top:4px}.market-city-dialog__eyebrow{font-size:10px;letter-spacing:.15em;text-transform:uppercase;font-weight:800;color:#a33183}.market-city-dialog__heading button{height:35px;width:35px;border-radius:50%;background:#f5f0f6;font-size:13px}
.market-city-dialog__search{display:flex;align-items:center;gap:10px;border:1px solid #e6dce9;border-radius:12px;padding:13px 14px;margin-top:24px}.market-city-dialog__search svg{height:18px;width:18px;color:#806d8e}.market-city-dialog__search input{width:100%;outline:0;font-size:14px}
.market-city-dialog__caption{font-size:12px;color:#806d8e;line-height:1.5;margin:12px 0 18px}.market-city-dialog__options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.market-city-dialog__options button{display:flex;align-items:center;gap:9px;padding:13px;border:1px solid #ece5ef;border-radius:13px;font-weight:700;text-align:left;font-size:13px}.market-city-dialog__options button:hover,.market-city-dialog__options button[aria-pressed="true"]{background:#fbf3fa;border-color:#d69bc7}.market-city-dialog__pin{font-size:19px;color:#a74b91}.market-city-dialog__selected{margin-left:auto;color:#a42a82}.market-city-dialog__empty{grid-column:span 2;color:#7c7282;padding:15px}
@media(max-width:1180px){.market-header__main{gap:17px}.market-header__brand{min-width:150px}.market-header__search{max-width:380px}.market-header__account{display:none}}
@media(max-width:770px){.market-header__main{height:65px;padding:0 16px;gap:10px;justify-content:space-between}.market-header__brand{min-width:0}.market-header__word{font-size:19px}.market-header__nav,.market-header__search{display:none}.market-header__city{margin-left:auto}.market-header__mobile-search{display:block;padding:0 16px 14px}.market-header__mobile-search form{display:flex;align-items:center;gap:9px;height:43px;border:1px solid rgba(255,255,255,.17);background:rgba(255,255,255,.09);border-radius:12px;padding:0 12px}.market-header__mobile-search button{color:#fff;font-size:11px;font-weight:800}.market-city-dialog__options{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important}}
</style>
