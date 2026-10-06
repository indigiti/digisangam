<script setup>
const props=defineProps({
  field:{type:Object,required:true},
  modelValue:{default:''},
  categories:{type:Array,default:()=>[]},
})
const emit=defineEmits(['update:modelValue'])
const options=()=>props.field.id==='fld_category'?props.categories:(props.field.options||[])
function update(value){emit('update:modelValue',value)}
function fileChanged(event){const file=event.target.files?.[0];update(file?file.name:'')}
</script>
<template>
  <label class="grid gap-2">
    <span class="text-sm font-bold text-slate-800">{{field.label}} <i v-if="field.required" class="not-italic text-rose-500">*</i></span>
    <textarea v-if="field.type==='paragraph'||field.type==='textarea'" :value="modelValue" rows="4" class="public-control" @input="update($event.target.value)"></textarea>
    <select v-else-if="['select','dropdown'].includes(field.type)" :value="modelValue" class="public-control" @change="update($event.target.value)">
      <option value="">Select {{field.label}}</option><option v-for="o in options()" :key="String(o)" :value="o">{{o}}</option>
    </select>
    <div v-else-if="field.type==='radio'" class="flex flex-wrap gap-2">
      <label v-for="o in options()" :key="String(o)" class="cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-sm"><input class="mr-2 accent-indigo-600" type="radio" :value="o" :checked="modelValue===o" @change="update(o)"/>{{o}}</label>
    </div>
    <div v-else-if="field.type==='multiselect'" class="grid gap-2 sm:grid-cols-2">
      <label v-for="o in options()" :key="String(o)" class="cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-sm"><input class="mr-2 accent-indigo-600" type="checkbox" :value="o" :checked="Array.isArray(modelValue)&&modelValue.includes(o)" @change="update($event.target.checked?[...(Array.isArray(modelValue)?modelValue:[]),o]:(Array.isArray(modelValue)?modelValue:[]).filter(x=>x!==o))"/>{{o}}</label>
    </div>
    <input v-else-if="field.type==='file'" type="file" class="public-control" @change="fileChanged"/>
    <input v-else :type="field.type==='phone'?'tel':field.type==='date'?'date':field.type==='email'?'email':'text'" :value="modelValue" class="public-control" :required="field.required" @input="update($event.target.value)"/>
    <small v-if="field.help" class="text-xs text-slate-400">{{field.help}}</small>
  </label>
</template>
