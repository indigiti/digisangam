<script setup>
import { onMounted, ref, watch } from 'vue'
import QRCode from 'qrcode'
const props=defineProps({value:{type:String,default:''}})
const src=ref('')
async function render(){src.value=props.value?await QRCode.toDataURL(props.value,{width:240,margin:1,errorCorrectionLevel:'M'}):''}
onMounted(render)
watch(()=>props.value,render)
</script>
<template><div class="grid place-items-center rounded-2xl border border-slate-200 bg-white p-4"><img v-if="src" :src="src" alt="DigiSangam signed attendee QR credential" class="h-52 w-52"/><div v-else class="h-52 w-52 animate-pulse rounded-xl bg-slate-100"></div></div></template>
