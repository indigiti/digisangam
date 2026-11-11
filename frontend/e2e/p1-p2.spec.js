import { test, expect } from '@playwright/test'

const base='/digisangam/'
const event={
  id:'evt_public',name:'DigiSangam Experience 2027',description:'An immersive conference in Pune',
  status:'Published',privacy:'public',type:'Conference',category:'Technology',
  format:'in_person',start_date:'2027-01-15',date:'2027-01-15',
  location:'Pune',venue_name:'Expo Centre',currency:'INR',progress:75,registrations:24,
  branding:{primary_color:'#f4dc3b',secondary_color:'#5145dc',brand_name:'DigiSangam Event'},
  public_page:{headline:'Meet the future of events'},
  organizer:{name:'DigiSangam'},
}
const tickets=[
  {id:'tk_sold',name:'Early bird',quantity:10,sold:10,reserved:0,price:0,status:'Active'},
  {id:'tk_free',name:'General admission',quantity:100,sold:10,reserved:0,price:0,status:'Active'},
]
const form={
  title:'Join the experience',approval_mode:'auto',categories:['General'],
  fields:[
    {id:'fld_name',label:'Full Name',required:true,type:'text',visibility:'always'},
    {id:'fld_email',label:'Email Address',required:true,type:'email',visibility:'always'},
  ],
}

async function mockBackend(page, onRegistration = () => {}, eventFixture = event) {
  await page.route('**/api/v1/**',route=>{
    const url=new URL(route.request().url())
    const path=url.pathname.split('/api/v1/')[1]||''
    const json=(body,status=200)=>route.fulfill({status,contentType:'application/json',body:JSON.stringify(body)})
    if(path==='auth/status')return json({setup_required:false,csrf_token:'csrf_fixture',user:{id:'usr',name:'QA Organizer',role:'super_admin'}})
    if(path==='events')return json([eventFixture,{...eventFixture,id:'evt_two',name:'Second Summit',status:'Draft',location:'Mumbai'}])
    if(path==='public/events')return json({events:[eventFixture],categories:['Technology'],locations:['Pune']})
    if(path==='public/events/evt_public')return json({event:eventFixture,tickets,registration:form})
    if(path==='public/events/evt_public/register'&&route.request().method()==='POST'){
      onRegistration(route.request().postDataJSON())
      return json({confirmation_token:'demo-token'})
    }
    if(path==='public/confirmations/demo-token')return json({
      attendee:{id:'att_1',name:'QA Attendee',email:'qa@example.test',category:'General',status:'Confirmed'},
      event:eventFixture,order:{status:'paid',amount:0},ticket:tickets[1],
      credential:{payload:'digisangam://credential/example'},
    })
    if(path.startsWith('dashboard'))return json({
      event:eventFixture,stats:[{label:'Registrations',value:'24',delta:'',tone:'blue'}],
      trend:[2,4,3,7,11,12],categories:[],metrics:{},
    })
    return json([])
  })
}

test('P1 theme survives reload without leaking theme into public pages',async({page})=>{
  await mockBackend(page)
  await page.goto(base)
  await page.getByRole('button',{name:'Switch to dark mode'}).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark')
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('data-theme','dark')
  await page.getByRole('button',{name:'Switch to light mode'}).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme','light')
})

test('P1 real event portfolio filters and restores without fabricated data',async({page})=>{
  await mockBackend(page)
  await page.goto(base+'events')
  await expect(page.getByRole('link',{name:'DigiSangam Experience 2027'})).toBeVisible()
  await page.getByLabel('Search events').fill('Second')
  await expect(page.getByRole('link',{name:'Second Summit'})).toBeVisible()
  await expect(page.getByRole('link',{name:'DigiSangam Experience 2027'})).toHaveCount(0)
  await page.getByLabel('Search events').fill('')
  await page.getByRole('button',{name:/Draft/}).click()
  await expect(page.getByRole('link',{name:'Second Summit'})).toBeVisible()
  await expect(page.getByRole('link',{name:'DigiSangam Experience 2027'})).toHaveCount(0)
})

test('P2 public event metadata, brand contrast and sold-out protection',async({page})=>{
  await mockBackend(page)
  await page.goto(base+'e/evt_public')
  await expect(page.getByRole('heading',{name:'Meet the future of events'})).toBeVisible()
  await expect(page).toHaveTitle('Meet the future of events | DigiSangam')
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content','index,follow')
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href',/\/digisangam\/e\/evt_public$/)
  await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(1)
  const accent=await page.locator('main.public-event').evaluate(node=>getComputedStyle(node).getPropertyValue('--event-accent').trim())
  expect(accent).toBe('#f4dc3b')
  await expect(page.getByRole('button',{name:/Early bird/})).toBeDisabled()
  await expect(page.getByRole('button',{name:/General admission/})).toBeEnabled()
})

test('P2 valid free ticket completes server registration and shows verified state',async({page})=>{
  let registered=null
  await mockBackend(page,payload=>{registered=payload})
  await page.goto(base+'e/evt_public')
  await page.getByRole('button',{name:/General admission/}).click()
  await page.getByRole('button',{name:/Continue/}).click()
  await page.getByLabel(/Full Name/).fill('QA Attendee')
  await page.getByLabel(/Email Address/).fill('qa@example.test')
  await page.getByRole('button',{name:/Continue/}).click()
  await page.getByRole('button',{name:'Complete registration'}).click()
  await expect(page).toHaveURL(/\/e\/evt_public\/confirmation\/demo-token$/)
  await expect(page.getByRole('heading',{name:'You’re registered.'})).toBeVisible()
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content','noindex,nofollow')
  expect(registered?.ticket_id).toBe('tk_free')
  expect(registered?.answers?.fld_name).toBe('QA Attendee')
})

const ticketDesignIds=['editorial-mono','grand-theatre-dark','neon-festival','commerce-blue','soft-luxe-wellness']
for (const designId of ticketDesignIds) {
  test('P2 '+designId+' preserves organizer branding and renders signed ticket',async({page})=>{
    const customEvent={...event,ticket_design:designId}
    await mockBackend(page,()=>{},customEvent)
    await page.goto(base+'e/evt_public')
    const shell=page.locator('main.public-event')
    await expect(shell).toHaveClass(new RegExp('public-event--'+designId))
    const color=await shell.evaluate(node=>getComputedStyle(node).getPropertyValue('--event-accent').trim())
    expect(color).toBe('#f4dc3b')
    const contrast=await shell.evaluate(node=>getComputedStyle(node).getPropertyValue('--event-accent-contrast').trim())
    expect(contrast).toBe('#142039')
    await page.goto(base+'e/evt_public/confirmation/demo-token')
    const ticket=page.locator('article.ds-ticket')
    await expect(ticket).toHaveClass(new RegExp('ds-ticket--'+designId))
    await expect(ticket.getByText('QA Attendee')).toBeVisible()
    await expect(ticket.locator('img[alt="DigiSangam signed attendee QR credential"]')).toBeVisible()
  })
}
