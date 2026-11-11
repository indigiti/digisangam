import {test,expect} from '@playwright/test'

const base='/digisangam/'
const published=[
  {id:'evt_music',name:'Pune Music Night',headline:'Pune Music Night',description:'Live sound on stage',
   status:'Published',privacy:'public',category:'Music',type:'Concert',
   start_date:'2027-06-12',end_date:'',location:'Pune, India',city:'Pune',venue_name:'City Arena',
   currency:'INR',min_price:1200,ticket_count:1,available:50,branding:{}},
  {id:'evt_art',name:'Ceramics Lab',headline:'Ceramics Lab',description:'Hands on workshop',
   status:'Published',privacy:'public',category:'Workshops',type:'Activity',
   start_date:'2027-06-15',end_date:'',location:'Mumbai, India',city:'Mumbai',venue_name:'The Studio',
   currency:'INR',min_price:399,ticket_count:1,available:25,branding:{}},
  {id:'evt_free',name:'Free Design Meetup',headline:'Free Design Meetup',description:'Community meet up',
   status:'Published',privacy:'public',category:'Workshops',type:'Meetup',
   start_date:'2027-06-19',end_date:'',location:'Pune, India',city:'Pune',venue_name:'Community Hub',
   currency:'INR',min_price:0,ticket_count:1,available:25,branding:{}},
]
async function mock(page){
  await page.route('**/api/v1/**',route=>{
    const path=new URL(route.request().url()).pathname.split('/api/v1/')[1]
    const body=path==='public/events'?{events:published,categories:['Music','Workshops'],cities:['Mumbai','Pune'],locations:['Pune, India','Mumbai, India'],total:3,has_more:false}:
      path==='auth/status'?{setup_required:false,authenticated:false,user:null}: []
    return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(body)})
  })
}
test('marketplace discovery supports query, city and verified category filters',async({page})=>{
  await mock(page)
  await page.goto(base+'discover')
  await expect(page.getByRole('heading',{name:'Go out. Feel more.'})).toBeVisible()
  await expect(page.getByText('Pune Music Night').first()).toBeVisible()
  await page.getByPlaceholder('Search for events, conferences, workshops and more').fill('Ceramics')
  await expect(page.getByText('Ceramics Lab').first()).toBeVisible()
  await expect(page.getByText('Pune Music Night')).toHaveCount(0)
  await page.getByPlaceholder('Search for events, conferences, workshops and more').fill('')
  await page.getByRole('button',{name:/Explore in/}).click()
  await expect(page.getByRole('dialog',{name:'Choose your city'})).toBeVisible()
  await page.getByRole('button',{name:'Pune',exact:true}).click()
  await expect(page.getByText('Ceramics Lab')).toHaveCount(0)
  await expect(page.getByText('Free Design Meetup').first()).toBeVisible()
  await page.getByRole('button',{name:'Workshops',exact:true}).first().click()
  await expect(page.getByText('Pune Music Night')).toHaveCount(0)
  await expect(page.getByText('Free Design Meetup').first()).toBeVisible()
  await page.getByRole('button',{name:'Clear filters'}).click()
  await expect(page.getByText('Pune Music Night').first()).toBeVisible()
})
test('mobile marketplace search and city picker retain accessible controls',async({page})=>{
  await page.setViewportSize({width:390,height:844})
  await mock(page)
  await page.goto(base+'discover')
  await expect(page.getByPlaceholder('Search events and experiences')).toBeVisible()
  await page.getByPlaceholder('Search events and experiences').fill('Free Design')
  await expect(page.getByText('Free Design Meetup').first()).toBeVisible()
  await expect(page.getByText('Pune Music Night')).toHaveCount(0)
  await page.getByRole('button',{name:/Explore in/}).click()
  await expect(page.getByRole('dialog',{name:'Choose your city'})).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog',{name:'Choose your city'})).toHaveCount(0)
})
