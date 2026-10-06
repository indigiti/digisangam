import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './style.css'

const app=createApp(App)
const pinia=createPinia()
const auth=useAuthStore(pinia)

router.beforeEach(async(to)=>{
  if(!auth.initialized){
    try{await auth.bootstrap()}catch{auth.initialized=true}
  }
  if(to.meta.public){
    if(to.path==='/access'&&auth.user) return '/'
    return true
  }
  if(!auth.user) return '/access'
  return true
})

app.use(pinia).use(router).mount('#app')
