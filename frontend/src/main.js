import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useThemeStore } from './stores/theme'
import { offlineStore } from './services/offline'
import './style.css'

const app=createApp(App)
const pinia=createPinia()
const auth=useAuthStore(pinia)
useThemeStore(pinia).initialize()

router.beforeEach(async(to)=>{
  if(!auth.initialized){
    try{await auth.bootstrap()}catch{
      auth.initialized=true
      // A prepared device may resume OnGround only; no other private route is exposed.
      if(to.name==='onground'){
        try{
          auth.offlineMode=await offlineStore.hasPreparedSnapshot(localStorage.getItem('digisangam_event_id')||'')
        }catch{auth.offlineMode=false}
      }
    }
  }
  if(to.meta.public){
    if(to.path==='/access'&&auth.user) return '/'
    return true
  }
  if(!auth.user){
    if(to.name==='onground'&&auth.offlineMode) return true
    return to.path==='/'?'/discover':'/access'
  }
  return true
})

app.use(pinia).use(router).mount('#app')

if('serviceWorker' in navigator && import.meta.env.PROD){
  window.addEventListener('load',()=>navigator.serviceWorker.register((import.meta.env.BASE_URL||'/')+'sw.js').catch(()=>{}))
}
