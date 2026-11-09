<script setup>
defineProps({label:String,value:[String,Number],delta:String,tone:{type:String,default:'blue'}})
</script>
<template>
  <article class="stat-card transition-colors hover:border-indigo-200">
    <div class="flex items-center justify-between gap-3">
      <p class="text-[12px] font-semibold text-slate-500">{{label}}</p>
      <span class="h-2 w-2 rounded-full" :class="{'bg-indigo-500':tone==='blue','bg-emerald-500':tone==='mint','bg-cyan-500':tone==='cyan','bg-rose-500':tone==='rose'}" aria-hidden="true"></span>
    </div>
    <div class="mt-3 flex items-end justify-between gap-3">
      <p class="text-[28px] font-extrabold leading-none tracking-[-.045em] text-slate-900">{{value}}</p>
      <span v-if="delta" class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1 text-[11px] font-bold text-slate-600">{{delta}}</span>
    </div>
  </article>
</template>
