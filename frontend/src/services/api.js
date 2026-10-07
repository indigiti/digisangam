import { useAuthStore } from '../stores/auth'

const apiBase=(import.meta.env.BASE_URL||'/').replace(/\/$/,'')+'/api/v1'

async function parseResponse(response){
  const text=await response.text()
  let data=null
  try{data=text?JSON.parse(text):null}catch{data={error:text||'Invalid server response'}}
  if(!response.ok) throw new Error(data?.error||('API '+response.status))
  return data
}

async function publicRequest(path,options={}){
  const response=await fetch(apiBase+path,{credentials:'same-origin',headers:{'Content-Type':'application/json',...(options.headers||{})},...options})
  return parseResponse(response)
}

async function uploadRequest(path,formData,{publicMode=false}={}){
  const auth=useAuthStore()
  const headers={}
  if(!publicMode&&auth.csrf) headers['X-CSRF-Token']=auth.csrf
  const response=await fetch(apiBase+path,{method:'POST',credentials:'same-origin',headers,body:formData})
  return parseResponse(response)
}

async function request(path,options={}){
  const auth=useAuthStore()
  const method=(options.method||'GET').toUpperCase()
  const headers={'Content-Type':'application/json',...(options.headers||{})}
  if(!['GET','HEAD'].includes(method)&&auth.csrf) headers['X-CSRF-Token']=auth.csrf
  try{
    const response=await fetch(apiBase+path,{credentials:'same-origin',headers,...options})
    if(response.status===401||response.status===428){
      auth.user=null
      const access=(import.meta.env.BASE_URL||'/')+'access'
      if(!location.pathname.endsWith('/access')) location.assign(access)
    }
    return await parseResponse(response)
  }catch(error){
    throw error
  }
}

export const api={
  publicEvents:()=>publicRequest('/public/events'),
  publicInvitation:(token)=>publicRequest('/public/invitations/'+encodeURIComponent(token)),
  publicEvent:(id)=>publicRequest('/public/events/'+encodeURIComponent(id)),
  publicRegister:(id,payload)=>publicRequest('/public/events/'+encodeURIComponent(id)+'/register',{method:'POST',body:JSON.stringify(payload)}),
  publicVerifyRazorpay:(payload)=>publicRequest('/public/payments/razorpay/verify',{method:'POST',body:JSON.stringify(payload)}),
  publicConfirmation:(token)=>publicRequest('/public/confirmations/'+encodeURIComponent(token)),
  retryPublicPayment:(token)=>publicRequest('/public/confirmations/'+encodeURIComponent(token)+'/retry-payment',{method:'POST',body:'{}'}),
  publicConcierge:(token,question)=>publicRequest('/public/concierge/'+encodeURIComponent(token),{method:'POST',body:JSON.stringify({question})}),
  dashboard:(eventId='')=>request('/dashboard'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  intelligenceOverview:(eventId)=>request('/intelligence/overview?event_id='+encodeURIComponent(eventId)),
  intelligenceGraph:(eventId)=>request('/intelligence/graph?event_id='+encodeURIComponent(eventId)),
  copilot:(payload)=>request('/intelligence/copilot',{method:'POST',body:JSON.stringify(payload)}),
  intelligenceActions:(eventId='')=>request('/intelligence/actions'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createIntelligenceAction:(payload)=>request('/intelligence/actions',{method:'POST',body:JSON.stringify(payload)}),
  decideIntelligenceAction:(id,decision)=>request('/intelligence/actions/'+encodeURIComponent(id)+'/'+(decision==='approved'?'approve':'reject'),{method:'POST',body:'{}'}),
  events:()=>request('/events'),
  event:(id)=>request('/events/'+encodeURIComponent(id)),
  eventPreview:(id)=>request('/events/'+encodeURIComponent(id)+'/preview'),
  createEvent:(payload)=>request('/events',{method:'POST',body:JSON.stringify(payload)}),
  updateEvent:(id,payload)=>request('/events/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  registration:(eventId)=>request('/events/'+encodeURIComponent(eventId)+'/registration'),
  saveRegistration:(eventId,payload)=>request('/events/'+encodeURIComponent(eventId)+'/registration',{method:'PUT',body:JSON.stringify(payload)}),
  invitations:(eventId='')=>request('/invitations'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createInvitation:(payload)=>request('/invitations',{method:'POST',body:JSON.stringify(payload)}),
  sendInvitation:(id)=>request('/invitations/'+encodeURIComponent(id)+'/send',{method:'POST',body:'{}'}),
  revokeInvitation:(id)=>request('/invitations/'+encodeURIComponent(id)+'/revoke',{method:'POST',body:'{}'}),
  attendees:(eventId='')=>request('/attendees'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  attendee:(id)=>request('/attendees/'+encodeURIComponent(id)),
  createAttendee:(payload)=>request('/attendees',{method:'POST',body:JSON.stringify(payload)}),
  updateAttendee:(id,payload)=>request('/attendees/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  importAttendees:(csv,eventId)=>request('/attendees/import'+(eventId?'?event_id='+encodeURIComponent(eventId):''),{method:'POST',body:JSON.stringify({csv,event_id:eventId})}),
  credential:(id)=>request('/attendees/'+encodeURIComponent(id)+'/credential'),
  scannerVerify:(payload,zone_id='',event_id='')=>request('/scanner/verify',{method:'POST',body:JSON.stringify({payload,zone_id,event_id})}),
  scannerCheckin:(payload,zone_id='',event_id='')=>request('/scanner/checkin',{method:'POST',body:JSON.stringify({payload,zone_id,event_id})}),
  scannerExit:(payload,zone_id='',event_id='')=>request('/scanner/exit',{method:'POST',body:JSON.stringify({payload,zone_id,event_id})}),
  ongroundLive:(eventId,limit=100)=>request('/onground/live?event_id='+encodeURIComponent(eventId)+'&limit='+encodeURIComponent(limit)),
  ongroundSnapshot:(eventId)=>request('/onground/snapshot/'+encodeURIComponent(eventId)),
  ongroundSync:(items)=>request('/onground/sync',{method:'POST',body:JSON.stringify({items})}),
  campaigns:(eventId='')=>request('/campaigns'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createCampaign:(payload)=>request('/campaigns',{method:'POST',body:JSON.stringify(payload)}),
  updateCampaign:(id,payload)=>request('/campaigns/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  dispatchCampaign:(id)=>request('/campaigns/'+encodeURIComponent(id)+'/dispatch',{method:'POST',body:'{}'}),
  automations:(eventId='')=>request('/automations'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createAutomation:(payload)=>request('/automations',{method:'POST',body:JSON.stringify(payload)}),
  updateAutomation:(id,payload)=>request('/automations/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  fireAutomation:(payload)=>request('/automations/fire',{method:'POST',body:JSON.stringify(payload)}),
  badges:(eventId='')=>request('/badges'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  badgePrints:(eventId='')=>request('/badge-prints'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createBadgePrint:(payload)=>request('/badge-prints',{method:'POST',body:JSON.stringify(payload)}),
  updateBadgePrint:(id,payload)=>request('/badge-prints/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  createBadge:(payload)=>request('/badges',{method:'POST',body:JSON.stringify(payload)}),
  updateBadge:(id,payload)=>request('/badges/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  sessions:(eventId='')=>request('/sessions'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  sessionAttendance:(id)=>request('/sessions/'+encodeURIComponent(id)+'/attendance'),
  sessionEnter:(id,payload)=>request('/sessions/'+encodeURIComponent(id)+'/enter',{method:'POST',body:JSON.stringify({payload})}),
  createSession:(payload)=>request('/sessions',{method:'POST',body:JSON.stringify(payload)}),
  updateSession:(id,payload)=>request('/sessions/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  exhibitors:(eventId='')=>request('/exhibitors'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  leads:(eventId='')=>request('/leads'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createLead:(payload)=>request('/leads',{method:'POST',body:JSON.stringify(payload)}),
  meetings:(eventId='')=>request('/meetings'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createMeeting:(payload)=>request('/meetings',{method:'POST',body:JSON.stringify(payload)}),
  updateMeeting:(id,payload)=>request('/meetings/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  createExhibitor:(payload)=>request('/exhibitors',{method:'POST',body:JSON.stringify(payload)}),
  updateExhibitor:(id,payload)=>request('/exhibitors/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  venue:(eventId)=>request('/venue/'+encodeURIComponent(eventId)),
  seatAssignments:(eventId)=>request('/venue/'+encodeURIComponent(eventId)+'/seats'),
  assignSeat:(eventId,payload)=>request('/venue/'+encodeURIComponent(eventId)+'/seats',{method:'POST',body:JSON.stringify(payload)}),
  updateVenue:(eventId,payload)=>request('/venue/'+encodeURIComponent(eventId),{method:'PATCH',body:JSON.stringify(payload)}),
  tickets:(eventId='')=>request('/tickets'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createTicket:(payload)=>request('/tickets',{method:'POST',body:JSON.stringify(payload)}),
  updateTicket:(id,payload)=>request('/tickets/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  orders:(eventId='')=>request('/orders'+(eventId?'?event_id='+encodeURIComponent(eventId):'')),
  createOrder:(payload)=>request('/orders',{method:'POST',body:JSON.stringify(payload)}),
  captureOrder:(id,payload={})=>request('/orders/'+encodeURIComponent(id)+'/capture',{method:'POST',body:JSON.stringify(payload)}),
  refundOrder:(id,payload={})=>request('/orders/'+encodeURIComponent(id)+'/refund',{method:'POST',body:JSON.stringify(payload)}),
  accreditation:(eventId)=>request('/accreditation?event_id='+encodeURIComponent(eventId)),
  createAccreditation:(payload)=>request('/accreditation',{method:'POST',body:JSON.stringify(payload)}),
  updateAccreditation:(id,payload)=>request('/accreditation/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  walkIn:(payload)=>request('/onground/walk-in',{method:'POST',body:JSON.stringify(payload)}),
  reports:(eventId)=>request('/reports?event_id='+encodeURIComponent(eventId)),
  reportDefinitions:(eventId)=>request('/report-definitions?event_id='+encodeURIComponent(eventId)),
  createReportDefinition:(payload)=>request('/report-definitions',{method:'POST',body:JSON.stringify(payload)}),
  updateReportDefinition:(id,payload)=>request('/report-definitions/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  deleteReportDefinition:(id)=>request('/report-definitions/'+encodeURIComponent(id),{method:'DELETE'}),
  reportDefinitionExportUrl:(id)=>apiBase+'/reports/export-definition/'+encodeURIComponent(id),
  reportExportUrl:(eventId,type)=>apiBase+'/reports/export?event_id='+encodeURIComponent(eventId)+'&type='+encodeURIComponent(type),
  eventBlueprint:(prompt)=>request('/intelligence/event-builder',{method:'POST',body:JSON.stringify({prompt})}),
  uploadMedia:(eventId,file,kind='asset',isPublic=true)=>{
    const data=new FormData();data.append('event_id',eventId);data.append('kind',kind);data.append('public',isPublic?'1':'');data.append('file',file)
    return uploadRequest('/media',data)
  },
  publicUploadMedia:(eventId,file,kind='registration_file')=>{
    const data=new FormData();data.append('kind',kind);data.append('file',file)
    return uploadRequest('/public/events/'+encodeURIComponent(eventId)+'/media',data,{publicMode:true})
  },
  publicMediaUrl:(id)=>apiBase+'/public/media/'+encodeURIComponent(id),
  mediaUrl:(id)=>apiBase+'/media/'+encodeURIComponent(id),
  issueWallet:(token,platform)=>publicRequest('/public/confirmations/'+encodeURIComponent(token)+'/wallet',{method:'POST',body:JSON.stringify({platform})}),
  walletPasses:(eventId)=>request('/wallet-passes?event_id='+encodeURIComponent(eventId)),
  createWalletPass:(payload)=>request('/wallet-passes',{method:'POST',body:JSON.stringify(payload)}),
  credentialBindings:(eventId)=>request('/credential-bindings?event_id='+encodeURIComponent(eventId)),
  bindCredential:(payload)=>request('/credential-bindings',{method:'POST',body:JSON.stringify(payload)}),
  revokeCredentialBinding:(id)=>request('/credential-bindings/'+encodeURIComponent(id)+'/revoke',{method:'POST',body:'{}'}),
  developerKeys:()=>request('/developer/keys'),
  createDeveloperKey:(payload)=>request('/developer/keys',{method:'POST',body:JSON.stringify(payload)}),
  revokeDeveloperKey:(id)=>request('/developer/keys/'+encodeURIComponent(id)+'/revoke',{method:'POST',body:'{}'}),
  developerWebhooks:(eventId)=>request('/developer/webhooks?event_id='+encodeURIComponent(eventId)),
  developerWebhookDeliveries:(eventId)=>request('/developer/webhook-deliveries?event_id='+encodeURIComponent(eventId)),
  createDeveloperWebhook:(payload)=>request('/developer/webhooks',{method:'POST',body:JSON.stringify(payload)}),
  updateDeveloperWebhook:(id,payload)=>request('/developer/webhooks/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  operationsHealth:()=>request('/operations/health'),
  workspace:()=>request('/workspace'),
  updateWorkspace:(payload)=>request('/workspace',{method:'PATCH',body:JSON.stringify(payload)}),
  team:()=>request('/team'),
  createTeamUser:(payload)=>request('/team',{method:'POST',body:JSON.stringify(payload)}),
  updateTeamUser:(id,payload)=>request('/team/'+encodeURIComponent(id),{method:'PATCH',body:JSON.stringify(payload)}),
  attendeeExportUrl:(eventId='')=>apiBase+'/attendees/export'+(eventId?'?event_id='+encodeURIComponent(eventId):''),
}
