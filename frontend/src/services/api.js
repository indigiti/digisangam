import * as demo from '../data/demo'
import { useAuthStore } from '../stores/auth'

const fallback={'/dashboard':demo.dashboard,'/events':demo.events,'/attendees':demo.attendees,'/tickets':demo.tickets}

async function parseResponse(response){
  const text=await response.text()
  let data=null
  try{data=text?JSON.parse(text):null}catch{data={error:text||'Invalid server response'}}
  if(!response.ok) throw new Error(data?.error||('API '+response.status))
  return data
}

async function publicRequest(path,options={}){
  const response=await fetch('/api/v1'+path,{
    credentials:'same-origin',
    headers:{'Content-Type':'application/json',...(options.headers||{})},
    ...options,
  })
  return parseResponse(response)
}

async function request(path,options={}){
  const auth=useAuthStore()
  const method=(options.method||'GET').toUpperCase()
  const headers={'Content-Type':'application/json',...(options.headers||{})}
  if(!['GET','HEAD'].includes(method)&&auth.csrf) headers['X-CSRF-Token']=auth.csrf
  try{
    const response=await fetch('/api/v1'+path,{credentials:'same-origin',headers,...options})
    if(response.status===401||response.status===428){
      auth.user=null
      if(location.pathname!=='/access') location.assign('/access')
    }
    return await parseResponse(response)
  }catch(error){
    if(import.meta.env.DEV&&method==='GET'&&Object.prototype.hasOwnProperty.call(fallback,path)) return structuredClone(fallback[path])
    throw error
  }
}

export const api={
  publicEvent:(id)=>publicRequest('/public/events/'+encodeURIComponent(id)),
  publicRegister:(id,payload)=>publicRequest('/public/events/'+encodeURIComponent(id)+'/register',{method:'POST',body:JSON.stringify(payload)}),
  publicConfirmation:(token)=>publicRequest('/public/confirmations/'+encodeURIComponent(token)),
  dashboard:()=>request('/dashboard'),
  events:()=>request('/events'),
  event:(id)=>request('/events/'+encodeURIComponent(id)),
  createEvent:(payload)=>request('/events',{method:'POST',body:JSON.stringify(payload)}),
  updateEvent:(id,payload)=>request('/events/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  registration:(eventId='evt_001')=>request('/events/'+encodeURIComponent(eventId)+'/registration'),
  saveRegistration:(eventId,payload)=>request('/events/'+encodeURIComponent(eventId)+'/registration',{method:'PUT',body:JSON.stringify(payload)}),
  invitations:()=>request('/invitations'),
  createInvitation:(payload)=>request('/invitations',{method:'POST',body:JSON.stringify(payload)}),
  attendees:()=>request('/attendees'),
  attendee:(id)=>request('/attendees/'+encodeURIComponent(id)),
  createAttendee:(payload)=>request('/attendees',{method:'POST',body:JSON.stringify(payload)}),
  updateAttendee:(id,payload)=>request('/attendees/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  importAttendees:(csv)=>request('/attendees/import',{method:'POST',body:JSON.stringify({csv})}),
  credential:(id)=>request('/attendees/'+encodeURIComponent(id)+'/credential'),
  tickets:()=>request('/tickets'),
  createTicket:(payload)=>request('/tickets',{method:'POST',body:JSON.stringify(payload)}),
  updateTicket:(id,payload)=>request('/tickets/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  orders:()=>request('/orders'),
  createOrder:(payload)=>request('/orders',{method:'POST',body:JSON.stringify(payload)}),
  workspace:()=>request('/workspace'),
  updateWorkspace:(payload)=>request('/workspace',{method:'PATCH',body:JSON.stringify(payload)}),
}
