import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useEventStore=defineStore('event-context',()=>{
  const events=ref([])
  const currentId=ref(localStorage.getItem('digisangam_event_id')||'')
  const loaded=ref(false)
  const current=computed(()=>events.value.find(x=>x.id===currentId.value)||null)

  async function load(){
    events.value=await api.events()
    const exists=events.value.some(x=>x.id===currentId.value)
    if(!exists) currentId.value=events.value[0]?.id||''
    if(currentId.value) localStorage.setItem('digisangam_event_id',currentId.value)
    else localStorage.removeItem('digisangam_event_id')
    loaded.value=true
    return events.value
  }

  function select(id){
    currentId.value=id||''
    if(currentId.value) localStorage.setItem('digisangam_event_id',currentId.value)
    else localStorage.removeItem('digisangam_event_id')
  }

  function add(event){
    events.value.unshift(event)
    select(event.id)
  }

  return {events,currentId,current,loaded,load,select,add}
})
