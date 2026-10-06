import * as demo from '../data/demo'
const fallback={'/dashboard':demo.dashboard,'/events':demo.events,'/attendees':demo.attendees,'/tickets':demo.tickets}
async function request(path,options={}){
  try{
    const response=await fetch('/api/v1'+path,{headers:{'Content-Type':'application/json',...(options.headers||{})},...options})
    if(!response.ok) throw new Error('API '+response.status)
    return await response.json()
  }catch(error){
    if(options.method&&options.method!=='GET') throw error
    return structuredClone(fallback[path]??null)
  }
}
export const api={dashboard:()=>request('/dashboard'),events:()=>request('/events'),attendees:()=>request('/attendees'),tickets:()=>request('/tickets'),createEvent:(payload)=>request('/events',{method:'POST',body:JSON.stringify(payload)})}
