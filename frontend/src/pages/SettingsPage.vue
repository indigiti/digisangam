<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const auth=useAuthStore(),router=useRouter()
const saved=ref(false),busy=ref(false),error=ref(''),tab=ref('workspace')
const team=ref([]),roles=ref([]),showUser=ref(false),teamBusy=ref(false),ops=ref(null),opsBusy=ref(false)
const canManageTeam=computed(()=>['super_admin','workspace_admin'].includes(auth.user?.role))
const form=reactive({name:'',brand:'',legal_name:'',gstin:'',billing_address:'',timezone:'Asia/Kolkata',currency:'INR',country:'IN'})
const userForm=reactive({name:'',email:'',password:'',role:'viewer'})
const roleLabel=role=>String(role||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())

async function load(){
  error.value=''
  try{
    const [workspace,teamData]=await Promise.all([api.workspace(),api.team()])
    Object.assign(form,workspace)
    team.value=teamData.users||[]
    roles.value=teamData.roles||[]
  }catch(e){error.value=e.message}
}
onMounted(load)

async function loadOperations(){
  if(!canManageTeam.value)return
  opsBusy.value=true;error.value=''
  try{ops.value=await api.operationsHealth()}catch(e){error.value=e.message}finally{opsBusy.value=false}
}
async function save(){
  busy.value=true;saved.value=false;error.value=''
  try{Object.assign(form,await api.updateWorkspace(form));saved.value=true}
  catch(e){error.value=e.message}
  finally{busy.value=false}
}
async function createUser(){
  teamBusy.value=true;error.value=''
  try{
    const user=await api.createTeamUser(userForm)
    team.value.push(user)
    Object.assign(userForm,{name:'',email:'',password:'',role:'viewer'})
    showUser.value=false
  }catch(e){error.value=e.message}
  finally{teamBusy.value=false}
}
async function updateUser(user,payload){
  teamBusy.value=true;error.value=''
  try{Object.assign(user,await api.updateTeamUser(user.id,payload))}
  catch(e){error.value=e.message}
  finally{teamBusy.value=false}
}
async function resetPassword(user){
  const password=window.prompt('Enter a new password (minimum 10 characters).')
  if(!password)return
  await updateUser(user,{password})
}
async function signout(){await auth.logout();router.replace('/access')}
</script>

<template><div class="mx-auto max-w-6xl space-y-6">
<section><p class="eyebrow">Workspace</p><h1 class="page-title">Settings</h1><p class="page-subtitle">Workspace identity, billing defaults, users and role-based access.</p></section>
<p v-if="error" class="rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{error}}</p>

<div class="panel flex flex-wrap gap-1 p-2">
  <button class="tab-btn" :class="{active:tab==='workspace'}" @click="tab='workspace'">Workspace</button>
  <button class="tab-btn" :class="{active:tab==='team'}" @click="tab='team'">Team & Roles</button>
  <button v-if="canManageTeam" class="tab-btn" :class="{active:tab==='operations'}" @click="tab='operations';loadOperations()">Operations</button>
  <button class="tab-btn" :class="{active:tab==='account'}" @click="tab='account'">My Account</button>
</div>

<section v-if="tab==='workspace'" class="grid gap-5 lg:grid-cols-[1fr_.65fr]">
  <form class="panel p-6" @submit.prevent="save">
    <h2 class="text-lg font-bold">Workspace profile</h2>
    <p class="mt-1 text-sm text-slate-500">Defaults used across newly created events and billing documents.</p>
    <div class="mt-6 grid gap-5 sm:grid-cols-2">
      <label class="field sm:col-span-2"><span>Workspace name</span><input v-model="form.name"/></label>
      <label class="field sm:col-span-2"><span>Brand name</span><input v-model="form.brand"/></label>
      <label class="field sm:col-span-2"><span>Legal / billing name</span><input v-model="form.legal_name" placeholder="Company or organizer legal name"/></label>
      <label class="field"><span>GSTIN</span><input v-model="form.gstin" placeholder="Optional"/></label>
      <label class="field"><span>Country</span><input v-model="form.country"/></label>
      <label class="field sm:col-span-2"><span>Billing address</span><textarea v-model="form.billing_address" rows="3"></textarea></label>
      <label class="field"><span>Timezone</span><input v-model="form.timezone"/></label>
      <label class="field"><span>Currency</span><input v-model="form.currency"/></label>
    </div>
    <div class="mt-6 flex items-center gap-3"><button class="btn-primary" :disabled="busy">{{busy?'Saving…':'Save settings'}}</button><span v-if="saved" class="text-xs font-bold text-emerald-600">Saved</span></div>
  </form>
  <aside class="panel p-6">
    <p class="panel-kicker">What this controls</p>
    <div class="mt-4 space-y-3 text-sm text-slate-600">
      <p><b>Legal name/GSTIN</b> feed invoice and receipt metadata.</p>
      <p><b>Timezone/currency</b> become defaults for event setup.</p>
      <p><b>Brand name</b> identifies the organizer workspace; each event keeps independent public branding.</p>
    </div>
  </aside>
</section>

<section v-else-if="tab==='team'" class="space-y-5">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div><p class="panel-kicker">Access control</p><h2 class="panel-title">Team members</h2><p class="mt-1 text-sm text-slate-500">Users are stored locally and permissions are enforced server-side for every protected API.</p></div>
    <button v-if="canManageTeam" class="btn-primary" @click="showUser=true">+ Add User</button>
  </div>
  <section class="panel overflow-hidden">
    <div class="overflow-x-auto"><table class="data-table">
      <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <tr v-for="user in team" :key="user.id">
          <td><b>{{user.name}}</b><p>{{user.email}}</p></td>
          <td>
            <select v-if="canManageTeam" :value="user.role" class="control min-w-48 py-2" :disabled="teamBusy" @change="updateUser(user,{role:$event.target.value})">
              <option v-for="role in roles" :key="role" :value="role">{{roleLabel(role)}}</option>
            </select>
            <span v-else class="soft-pill">{{roleLabel(user.role)}}</span>
          </td>
          <td><span class="status-badge" :class="user.active?'badge-published':'badge-draft'">{{user.active?'Active':'Disabled'}}</span></td>
          <td>{{user.created_at?.slice(0,10)||'—'}}</td>
          <td>
            <div v-if="canManageTeam" class="flex flex-wrap gap-2">
              <button class="btn-secondary py-1.5" :disabled="teamBusy" @click="updateUser(user,{active:!user.active})">{{user.active?'Disable':'Enable'}}</button>
              <button class="btn-secondary py-1.5" :disabled="teamBusy" @click="resetPassword(user)">Reset password</button>
            </div>
            <span v-else class="text-xs text-slate-400">Read only</span>
          </td>
        </tr>
      </tbody>
    </table></div>
  </section>
  <section class="panel p-5">
    <p class="panel-kicker">Role model</p>
    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="role in roles" :key="role" class="rounded-2xl border border-slate-200 p-4"><b class="text-sm">{{roleLabel(role)}}</b><p class="mt-1 text-xs text-slate-500">{{role==='super_admin'?'Full platform access':role==='workspace_admin'?'Workspace and all event operations':role==='event_manager'?'Event setup and operations':role==='registration_manager'?'Registration and attendee operations':role==='finance'?'Commerce and financial reporting':role==='onsite'?'Check-in and onsite operations':'Read-only operational access'}}</p></div>
    </div>
  </section>
</section>

<section v-else-if="tab==='operations'" class="space-y-5">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><p class="panel-kicker">Runtime operations</p><h2 class="panel-title">Background worker health</h2><p class="mt-1 text-sm text-slate-500">Workers should run on schedule. Stale or never-run workers can cause delayed messages, webhooks, printing, expired payment holds or orphan media.</p></div><button class="btn-secondary" :disabled="opsBusy" @click="loadOperations">{{opsBusy?'Refreshing…':'Refresh'}}</button></div>
  <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <article v-for="w in ops?.workers||[]" :key="w.name" class="panel p-5"><div class="flex items-center justify-between gap-2"><p class="panel-kicker">{{w.name}}</p><span class="status-badge" :class="w.state==='healthy'?'badge-published':w.state==='stale'?'bg-amber-100 text-amber-700':'bg-rose-100 text-rose-700'">{{w.state}}</span></div><p class="mt-3 text-xs text-slate-500">{{w.last_run_at?('Last '+w.last_run_at.slice(0,19).replace('T',' ')):'No heartbeat recorded'}}</p><p v-if="w.age_seconds!==null" class="mt-1 text-[10px] text-slate-400">{{Math.round(w.age_seconds/60)}} min ago</p></article>
  </section>
  <section class="panel overflow-hidden"><div class="border-b p-5"><p class="panel-kicker">Provider readiness</p><h2 class="panel-title">Live integrations vs safe fallbacks</h2><p class="mt-1 text-xs text-slate-500">A green worker only proves the queue runner is alive. These checks show whether work is reaching a real external provider.</p></div><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Capability</th><th>Mode</th><th>State</th><th>Behavior</th></tr></thead><tbody><tr v-for="p in ops?.providers||[]" :key="p.name"><td class="font-semibold">{{p.name}}</td><td class="font-mono text-xs">{{p.mode}}</td><td><span class="status-badge" :class="p.state==='live'?'badge-published':p.state==='fallback'?'bg-amber-100 text-amber-700':'bg-rose-100 text-rose-700'">{{p.state.replace('_',' ')}}</span></td><td class="max-w-md text-xs text-slate-500">{{p.detail}}</td></tr></tbody></table></div></section>
  <section class="panel overflow-hidden"><div class="border-b p-5"><p class="panel-kicker">Queues</p><h2 class="panel-title">Pending and failed work</h2></div><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Queue</th><th>Pending</th><th>Failed</th></tr></thead><tbody><tr v-for="(q,name) in ops?.queues||{}" :key="name"><td class="font-semibold">{{name.replaceAll('_',' ')}}</td><td>{{q.pending??q.pending_cleanup??0}}</td><td>{{q.failed??0}}</td></tr></tbody></table></div></section>
  <p class="rounded-2xl bg-amber-50 p-4 text-xs leading-5 text-amber-800">Production scheduling should run notifications, webhooks, badge printing and order-expiry about every 5 minutes, and media cleanup hourly. A worker showing <b>never_run</b> after deployment means its server cron is not configured.</p>
</section>

<section v-else class="grid gap-5 lg:grid-cols-[1fr_.65fr]">
  <article class="panel p-6"><p class="panel-kicker">Signed in as</p><h2 class="mt-2 text-xl font-bold">{{auth.user?.name}}</h2><p class="mt-1 text-sm text-slate-500">{{auth.user?.email}}</p><div class="mt-4"><span class="soft-pill">{{roleLabel(auth.user?.role)}}</span></div><button class="btn-secondary mt-6" @click="signout">Sign out</button></article>
  <aside class="panel p-6"><p class="panel-kicker">Session security</p><p class="mt-3 text-sm leading-6 text-slate-500">Authenticated admin requests use an HTTP-only session cookie and CSRF token. Role permissions are checked by the PHP API, not only hidden in the interface.</p></aside>
</section>

<div v-if="showUser" class="modal-backdrop" @click.self="showUser=false">
  <form class="modal-card" @submit.prevent="createUser">
    <h2 class="text-lg font-bold">Add team member</h2>
    <p class="mt-1 text-sm text-slate-500">Create a local DigiSangam administrator account.</p>
    <div class="mt-5 grid gap-4">
      <label class="field"><span>Name</span><input v-model="userForm.name" required/></label>
      <label class="field"><span>Email</span><input v-model="userForm.email" type="email" required/></label>
      <label class="field"><span>Temporary password</span><input v-model="userForm.password" type="password" minlength="10" required/></label>
      <label class="field"><span>Role</span><select v-model="userForm.role"><option v-for="role in roles" :key="role" :value="role">{{roleLabel(role)}}</option></select></label>
    </div>
    <div class="mt-6 flex justify-end gap-2"><button type="button" class="btn-secondary" @click="showUser=false">Cancel</button><button class="btn-primary" :disabled="teamBusy">{{teamBusy?'Creating…':'Create user'}}</button></div>
  </form>
</div>
</div></template>
