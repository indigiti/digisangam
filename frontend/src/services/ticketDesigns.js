export const TICKET_DESIGNS=Object.freeze([
  {id:'editorial-mono',number:'01',name:'Editorial Mono',description:'Gallery-like, beautifully restrained ticketing for conferences and curated performances.',recommended:'Conference · Culture · Gallery',accent:'#ca185e',surface:'#fffbf7',ink:'#19161a',muted:'#6d6370',hero:'linear-gradient(138deg,#3c2030,#ca185e 84%)',heroText:'#ffffff',line:'#eee1e4',serif:true,dark:false},
  {id:'grand-theatre-dark',number:'02',name:'Grand Theatre Dark',description:'Cinematic midnight and champagne for theatre, awards and premium occasions.',recommended:'Theatre · Gala · Luxury',accent:'#bd8a49',surface:'#121821',ink:'#f8e9ce',muted:'#c3b3a2',hero:'linear-gradient(132deg,#080b13,#342a29 75%,#a2753d)',heroText:'#fff5db',line:'#514535',serif:true,dark:true},
  {id:'neon-festival',number:'03',name:'Neon Festival',description:'A vibrant magenta-to-violet identity built for concerts and energetic audiences.',recommended:'Concert · Festival · DJ',accent:'#ec1889',surface:'#160f36',ink:'#ffffff',muted:'#d7c7ee',hero:'linear-gradient(126deg,#321088,#bb168c 58%,#087bd8)',heroText:'#ffffff',line:'#623f9a',serif:false,dark:true},
  {id:'commerce-blue',number:'04',name:'Commerce Blue',description:'Confident modern commerce for large events, exhibitions and reserved seating.',recommended:'Exhibition · Arena · Business',accent:'#105de8',surface:'#ffffff',ink:'#15233c',muted:'#67758a',hero:'linear-gradient(135deg,#0d2048,#1463cd 77%,#23a5dc)',heroText:'#ffffff',line:'#e3eaf4',serif:false,dark:false},
  {id:'soft-luxe-wellness',number:'05',name:'Soft Luxe Wellness',description:'Airy lavender and blush for workshops, retreats and thoughtful gatherings.',recommended:'Wellness · Workshop · Retreat',accent:'#8f62b7',surface:'#fffaff',ink:'#392d52',muted:'#796e89',hero:'linear-gradient(132deg,#7355a4,#b88ac3 64%,#efbfd3)',heroText:'#ffffff',line:'#eadff0',serif:true,dark:false}
])
export const DEFAULT_TICKET_DESIGN='editorial-mono'
export function getTicketDesign(id){
  return TICKET_DESIGNS.find(item=>item.id===id)||TICKET_DESIGNS[0]
}
