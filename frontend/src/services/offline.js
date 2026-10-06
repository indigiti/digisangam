const DB_NAME='digisangam-onground'
const DB_VERSION=1
const STORE_SNAPSHOTS='snapshots'
const STORE_QUEUE='checkin_queue'

function openDb(){
  return new Promise((resolve,reject)=>{
    const request=indexedDB.open(DB_NAME,DB_VERSION)
    request.onupgradeneeded=()=>{
      const db=request.result
      if(!db.objectStoreNames.contains(STORE_SNAPSHOTS)) db.createObjectStore(STORE_SNAPSHOTS,{keyPath:'event_id'})
      if(!db.objectStoreNames.contains(STORE_QUEUE)) db.createObjectStore(STORE_QUEUE,{keyPath:'local_id'})
    }
    request.onsuccess=()=>resolve(request.result)
    request.onerror=()=>reject(request.error)
  })
}
async function put(store,value){
  const db=await openDb()
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(store,'readwrite')
    tx.objectStore(store).put(value)
    tx.oncomplete=()=>{db.close();resolve(value)}
    tx.onerror=()=>{db.close();reject(tx.error)}
  })
}
async function get(store,key){
  const db=await openDb()
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(store,'readonly')
    const req=tx.objectStore(store).get(key)
    req.onsuccess=()=>{db.close();resolve(req.result||null)}
    req.onerror=()=>{db.close();reject(req.error)}
  })
}
async function all(store){
  const db=await openDb()
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(store,'readonly')
    const req=tx.objectStore(store).getAll()
    req.onsuccess=()=>{db.close();resolve(req.result||[])}
    req.onerror=()=>{db.close();reject(req.error)}
  })
}
async function remove(store,key){
  const db=await openDb()
  return new Promise((resolve,reject)=>{
    const tx=db.transaction(store,'readwrite')
    tx.objectStore(store).delete(key)
    tx.oncomplete=()=>{db.close();resolve()}
    tx.onerror=()=>{db.close();reject(tx.error)}
  })
}

export const offlineStore={
  saveSnapshot:snapshot=>put(STORE_SNAPSHOTS,snapshot),
  snapshot:eventId=>get(STORE_SNAPSHOTS,eventId),
  queued:()=>all(STORE_QUEUE),
  queueCheckin:item=>put(STORE_QUEUE,item),
  removeQueued:id=>remove(STORE_QUEUE,id),
  async findCredential(eventId,payload){
    const snapshot=await get(STORE_SNAPSHOTS,eventId)
    if(!snapshot) return null
    return snapshot.credentials?.find(x=>x.payload===payload)||null
  },
}
