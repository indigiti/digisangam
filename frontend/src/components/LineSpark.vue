<script setup>
import { computed } from 'vue'
const props=defineProps({values:{type:Array,default:()=>[]}})
const points=computed(()=>{const v=props.values.length?props.values:[0];const max=Math.max(...v),min=Math.min(...v),span=Math.max(1,max-min);return v.map((n,i)=>`${(i/(v.length-1||1))*100},${46-((n-min)/span)*38}`).join(' ')})
</script>
<template><svg viewBox="0 0 100 52" preserveAspectRatio="none" class="h-48 w-full overflow-visible"><defs><linearGradient id="sparkFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#4f46e5" stop-opacity=".20"/><stop offset="100%" stop-color="#4f46e5" stop-opacity="0"/></linearGradient></defs><polygon :points="'0,52 '+points+' 100,52'" fill="url(#sparkFill)"/><polyline :points="points" fill="none" stroke="#4f46e5" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/></svg></template>
