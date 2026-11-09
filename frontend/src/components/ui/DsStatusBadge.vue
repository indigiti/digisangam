<script setup>
import {computed} from 'vue'
const props=defineProps({status:{type:String,default:'Draft'}})
const style=computed(()=>({
  Live:'bg-emerald-100 text-emerald-800',
  Published:'bg-sky-100 text-sky-800',
  Draft:'bg-amber-100 text-amber-800',
  Completed:'bg-slate-100 text-slate-700',
  Archived:'bg-slate-100 text-slate-600',
}[props.status]||'bg-slate-100 text-slate-700'))
</script>
<template><span class="status-badge inline-flex items-center gap-1.5" :class="style"><span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>{{status}}</span></template>
