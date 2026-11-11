<script setup>
import { computed } from 'vue'
import QrCredential from './QrCredential.vue'
import {getTicketDesign} from '../services/ticketDesigns'

const props=defineProps({
  event:{type:Object,default:()=>({})},
  attendee:{type:Object,default:()=>({})},
  ticket:{type:Object,default:()=>({})},
  credentialPayload:{type:String,default:''},
  themeId:{type:String,default:''},
  preview:{type:Boolean,default:false},
})
const theme=computed(()=>getTicketDesign(props.themeId||props.event?.ticket_design))
const tokens=computed(()=>({
  '--ticket-accent':theme.value.accent,
  '--ticket-surface':theme.value.surface,
  '--ticket-ink':theme.value.ink,
  '--ticket-muted':theme.value.muted,
  '--ticket-hero':theme.value.hero,
  '--ticket-hero-ink':theme.value.heroText,
  '--ticket-line':theme.value.line,
}))
const hero=computed(()=>props.event?.branding?.cover_url||props.event?.branding?.poster_url||'')
const qrValue=computed(()=>props.preview?'digisangam://preview-only/not-valid-for-entry':props.credentialPayload)
const dateLabel=computed(()=>{
  const raw=String(props.event?.start_date||props.event?.date||'')
  if(!/^\d{4}-\d{2}-\d{2}$/.test(raw))return raw||'Date to be announced'
  return new Intl.DateTimeFormat('en-IN',{day:'numeric',month:'short',year:'numeric',timeZone:'UTC'}).format(new Date(raw+'T12:00:00Z'))
})
const venue=computed(()=>props.event?.venue_name||props.event?.location||'Venue to be announced')
const holder=computed(()=>props.attendee?.name||'Sample attendee')
const ticketName=computed(()=>props.ticket?.name||'General Admission')
const seat=computed(()=>props.attendee?.seat_label||props.attendee?.seat||'')
</script>

<template>
  <article class="ds-ticket" :class="['ds-ticket--'+theme.id,{'ds-ticket--serif':theme.serif,'ds-ticket--dark':theme.dark}]" :style="tokens" :aria-label="'Event ticket for '+(event.name||'event')">
    <header class="ds-ticket__hero">
      <img v-if="hero" class="ds-ticket__hero-img" :src="hero" alt="" loading="lazy" decoding="async"/>
      <div class="ds-ticket__hero-inner">
        <div class="ds-ticket__topline"><span>DigiSangam</span><span>EVENT PASS</span></div>
        <div class="ds-ticket__headline">
          <p class="ds-ticket__eyebrow">{{event.category||'Event'}}</p>
          <h2>{{event.name||'Your event name'}}</h2>
          <p class="ds-ticket__date">{{dateLabel}}</p>
        </div>
      </div>
    </header>
    <div class="ds-ticket__body">
      <div class="ds-ticket__cut" aria-hidden="true"></div>
      <div class="ds-ticket__label">{{preview?'DESIGN PREVIEW · NOT VALID FOR ENTRY':'SIGNED ADMISSION CREDENTIAL'}}</div>
      <div class="ds-ticket__qr">
        <QrCredential v-if="qrValue" :value="qrValue"/>
        <div v-else class="ds-ticket__pending">QR available after confirmation</div>
      </div>
      <p v-if="preview" class="ds-ticket__notice">Sample QR — cannot be used for admission</p>
      <p v-else class="ds-ticket__notice">Present this QR at the entrance</p>
      <div class="ds-ticket__rule" aria-hidden="true"></div>
      <dl class="ds-ticket__details">
        <div><dt>Attendee</dt><dd>{{holder}}</dd></div>
        <div><dt>Ticket type</dt><dd>{{ticketName}}</dd></div>
        <div><dt>Date</dt><dd>{{dateLabel}}</dd></div>
        <div><dt>Venue</dt><dd>{{venue}}</dd></div>
        <div v-if="seat"><dt>Seat</dt><dd>{{seat}}</dd></div>
      </dl>
      <div class="ds-ticket__footer"><span>{{event.branding?.brand_name||'DigiSangam Events'}}</span><span>{{preview?'PREVIEW ONLY':'KEEP THIS TICKET SAFE'}}</span></div>
    </div>
  </article>
</template>

<style scoped>
.ds-ticket{max-width:425px;width:100%;overflow:hidden;border:1px solid var(--ticket-line);border-radius:26px;background:var(--ticket-surface);color:var(--ticket-ink);box-shadow:0 24px 72px rgba(15,20,40,.15);font-family:Inter,ui-sans-serif,system-ui,sans-serif}
.ds-ticket__hero{position:relative;isolation:isolate;min-height:226px;background:var(--ticket-hero);color:var(--ticket-hero-ink);overflow:hidden}
.ds-ticket__hero-img{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover}
.ds-ticket__hero:after{content:'';position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(5,8,18,.33),rgba(8,8,25,.12) 30%,rgba(4,5,15,.68))}
.ds-ticket__hero-inner{min-height:226px;display:flex;flex-direction:column;justify-content:space-between;padding:23px 26px 26px}
.ds-ticket__topline{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}
.ds-ticket__eyebrow{font-size:11px;font-weight:800;letter-spacing:.2em;text-transform:uppercase;margin:0 0 8px;opacity:.87}
.ds-ticket__headline h2{font-size:clamp(28px,6vw,38px);line-height:1.1;font-weight:800;letter-spacing:-.045em;margin:0;overflow-wrap:anywhere}
.ds-ticket--serif .ds-ticket__headline h2{font-family:Georgia,'Times New Roman',serif;letter-spacing:-.04em;font-weight:600}
.ds-ticket__date{font-size:13px;margin:10px 0 0;font-weight:650}
.ds-ticket__body{padding:0 25px 24px;position:relative}
.ds-ticket__cut{height:18px;margin:0 -26px 21px;border-top:1px dashed var(--ticket-line);position:relative}
.ds-ticket__cut:before,.ds-ticket__cut:after{content:'';position:absolute;top:-12px;width:24px;height:24px;border-radius:50%;background:var(--ticket-surface);border:1px solid var(--ticket-line)}
.ds-ticket__cut:before{left:-12px}.ds-ticket__cut:after{right:-12px}
.ds-ticket__label{text-align:center;font-size:10px;font-weight:800;letter-spacing:.12em;color:var(--ticket-muted)}
.ds-ticket__qr{min-height:258px;display:grid;place-items:center;padding:11px 0 0}
.ds-ticket__qr :deep(.grid){border:0;border-radius:14px;padding:10px;background:white}
.ds-ticket__qr :deep(img){height:208px;width:208px;max-width:100%;image-rendering:auto}
.ds-ticket__pending{min-height:208px;display:grid;place-items:center;padding:25px;text-align:center;color:var(--ticket-muted);border:1px dashed var(--ticket-line);border-radius:14px}
.ds-ticket__notice{text-align:center;color:var(--ticket-muted);font-size:12px;line-height:1.5;margin:4px 0 24px}
.ds-ticket__rule{border-top:1px dashed var(--ticket-line);margin-bottom:20px}
.ds-ticket__details{display:grid;grid-template-columns:1fr 1fr;column-gap:18px;row-gap:19px;margin:0}
.ds-ticket__details div:nth-child(4){grid-column:span 2}
.ds-ticket__details dt{font-size:11px;color:var(--ticket-muted);margin-bottom:5px;font-weight:650}
.ds-ticket__details dd{margin:0;font-size:14px;line-height:1.4;font-weight:800;overflow-wrap:anywhere}
.ds-ticket__footer{display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--ticket-line);margin-top:24px;padding-top:16px;color:var(--ticket-muted);font-size:10px;font-weight:750;letter-spacing:.04em}
.ds-ticket--grand-theatre-dark .ds-ticket__hero-inner{min-height:260px}
.ds-ticket--grand-theatre-dark .ds-ticket__headline h2{color:#ffe7b1}
.ds-ticket--neon-festival .ds-ticket__hero{min-height:245px}
.ds-ticket--neon-festival .ds-ticket__headline h2{font-size:37px;font-weight:900}
.ds-ticket--commerce-blue .ds-ticket__hero{min-height:206px}
.ds-ticket--soft-luxe-wellness{border-radius:34px}
.ds-ticket--soft-luxe-wellness .ds-ticket__hero-inner{min-height:240px}
@media(max-width:400px){.ds-ticket__hero-inner{padding:20px}.ds-ticket__body{padding:0 19px 20px}.ds-ticket__qr :deep(img){height:192px;width:192px}.ds-ticket__details{column-gap:10px}}
@media print{.ds-ticket{max-width:100%;box-shadow:none;break-inside:avoid;print-color-adjust:exact;-webkit-print-color-adjust:exact}.ds-ticket__hero-img{display:block}}
</style>
