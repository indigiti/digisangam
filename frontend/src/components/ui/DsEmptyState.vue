<script setup>
defineProps({title:{type:String,required:true},description:{type:String,default:''}})
</script>
<template>
  <div class="premium-empty">
    <div class="mx-auto max-w-lg">
      <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 text-2xl text-indigo-600" aria-hidden="true"><slot name="icon">◇</slot></div>
      <h3 class="mt-5 text-lg font-extrabold tracking-tight">{{title}}</h3>
      <p v-if="description" class="mt-2 text-sm leading-6 text-slate-500">{{description}}</p>
      <div v-if="$slots.actions" class="mt-5 flex flex-wrap justify-center gap-2"><slot name="actions"/></div>
    </div>
  </div>
</template>
