<script setup>
defineOptions({inheritAttrs:false})
const props=defineProps({
  to:{type:[String,Object],default:null},
  variant:{type:String,default:'primary',validator:value=>['primary','secondary','ghost'].includes(value)},
  size:{type:String,default:'md',validator:value=>['sm','md'].includes(value)},
  type:{type:String,default:'button'},
  disabled:{type:Boolean,default:false},
  loading:{type:Boolean,default:false},
})
const classes=()=>[
  'inline-flex items-center justify-center gap-2 font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2',
  props.variant==='primary'?'btn-primary':props.variant==='secondary'?'btn-secondary':'ds-button-ghost',
  props.size==='sm'?'min-h-[36px] px-3 text-xs':'min-h-10 px-4 text-sm',
  (props.disabled||props.loading)?'pointer-events-none opacity-55':'',
]
</script>

<template>
  <RouterLink v-if="to" v-bind="$attrs" :to="to" :class="classes()" :aria-disabled="disabled||loading" :tabindex="disabled||loading?-1:undefined">
    <span v-if="loading" class="ds-loader" aria-hidden="true"></span>
    <slot />
  </RouterLink>
  <button v-else v-bind="$attrs" :type="type" :class="classes()" :disabled="disabled||loading" :aria-busy="loading||undefined">
    <span v-if="loading" class="ds-loader" aria-hidden="true"></span>
    <slot />
  </button>
</template>
