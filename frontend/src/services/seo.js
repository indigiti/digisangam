/* Route-scoped SEO metadata for public pages. Server-side metadata is a later gate. */
const attribute='data-digisangam-seo'
let initialTitle=null

function addMeta(key,value,type='name') {
  if(!value)return
  const element=document.createElement('meta')
  element.setAttribute(type,key)
  element.setAttribute('content',String(value))
  element.setAttribute(attribute,'1')
  document.head.appendChild(element)
}

export function resetPageSeo() {
  document.head.querySelectorAll('['+attribute+']').forEach(node=>node.remove())
  if(initialTitle!==null){document.title=initialTitle;initialTitle=null}
}

export function setPageSeo({title,description,image,canonical,robots,structuredData}={}) {
  resetPageSeo()
  initialTitle=document.title
  if(title)document.title=String(title)
  if(description){
    addMeta('description',String(description).slice(0,180))
    addMeta('og:description',String(description).slice(0,240),'property')
  }
  addMeta('og:title',title,'property')
  addMeta('og:type',structuredData?'event':'website','property')
  addMeta('og:image',image,'property')
  addMeta('og:url',canonical,'property')
  addMeta('twitter:card',image?'summary_large_image':'summary')
  addMeta('robots',robots||'index,follow')
  if(canonical){
    const link=document.createElement('link')
    link.setAttribute('rel','canonical')
    link.setAttribute('href',canonical)
    link.setAttribute(attribute,'1')
    document.head.appendChild(link)
  }
  if(structuredData){
    const script=document.createElement('script')
    script.type='application/ld+json'
    script.setAttribute(attribute,'1')
    script.textContent=JSON.stringify(structuredData)
    document.head.appendChild(script)
  }
}

export function canonicalPublicPath(path){
  return new URL((import.meta.env.BASE_URL||'/')+String(path).replace(/^\/+/,''),window.location.origin).href
}
