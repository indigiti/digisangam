import { defineStore } from 'pinia'

const KEY='digisangam-ui-theme'
const allowed=['light','dark','system']

export const useThemeStore=defineStore('visual-theme',{
  state:()=>({mode:'system'}),
  getters:{
    isDark(state){
      if(state.mode==='dark') return true
      if(state.mode==='light') return false
      return typeof window!=='undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches
    },
  },
  actions:{
    initialize(){
      const saved=localStorage.getItem(KEY)
      this.mode=allowed.includes(saved)?saved:'light'
      this.apply()
      this._listener=()=>{if(this.mode==='system')this.apply()}
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change',this._listener)
    },
    apply(){
      document.documentElement.dataset.theme=this.isDark?'dark':'light'
      document.documentElement.style.colorScheme=this.isDark?'dark':'light'
    },
    setMode(mode){
      if(!allowed.includes(mode))return
      this.mode=mode
      localStorage.setItem(KEY,mode)
      this.apply()
    },
    toggle(){this.setMode(this.isDark?'light':'dark')},
  },
})
