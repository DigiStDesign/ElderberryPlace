<template>
  <section class="p-4">
    <h2 class="page-title">{{ isEdit ? 'Edit Visitor' : 'New Visitor' }}</h2>

    <form @submit.prevent="onSubmit" class="form">
      <!-- Names -->
      <div class="row">
        <div class="col">
          <label class="lbl">First name</label>
          <input v-model.trim="first_name" class="input" required autofocus />
        </div>
        <div class="col">
          <label class="lbl">Last name</label>
          <input v-model.trim="last_name" class="input" required />
        </div>
      </div>

      <!-- Contact -->
      <div class="row">
        <div class="col">
          <label class="lbl">Email</label>
          <input v-model.trim="email" type="email" class="input" required />
        </div>
        <div class="col">
          <label class="lbl">Phone</label>
          <input v-model.trim="phone" class="input" placeholder="Optional" />
        </div>
      </div>

      <!-- Account (users table) -->
      <div class="row">
        <div class="col">
          <label class="lbl">Username</label>
          <input v-model.trim="username" class="input" required />
        </div>
        <div class="col" v-if="!isEdit">
          <label class="lbl">Password</label>
          <input v-model="password" type="password" class="input" minlength="4" :required="!isEdit" />
        </div>
      </div>

      <!-- Status (is_active) -->
      <div class="row">
        <div class="col">
          <label class="lbl">Status</label>
          <select v-model="status" class="input">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>

      <!-- Verification (visitor_profiles table) -->
      <div class="row">
        <div class="col">
          <label class="lbl inline">
            <input type="checkbox" v-model="verified" /> Verified
          </label>
          <div v-if="verified" class="hint">Will set <code>verified_at</code> and <code>verified_by</code> automatically.</div>
        </div>
      </div>

      <!-- Actions -->
      <div class="actions space-x-2">
        <button class="btn btn-primary" type="submit" :disabled="loading">{{ isEdit ? 'Save' : 'Create' }}</button>
        <button class="btn" type="button" @click="goBack" :disabled="loading">Cancel</button>
        <button class="btn" type="button" @click="refresh" v-if="isEdit" :disabled="loading">Refresh</button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Visitors from '@/api/visitors'
import AuthAPI from '@/api/auth'

const route = useRoute()
const router = useRouter()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = computed(() => !!id)

// form state
const first_name = ref('')
const last_name  = ref('')
const username   = ref('')
const email      = ref('')
const phone      = ref('')
const password   = ref('') // create only
const verified   = ref(false)
const status     = ref('active') // maps to is_active (1/0)

const error = ref('')
const loading = ref(false)

const full_name = computed(() => [first_name.value, last_name.value].filter(Boolean).join(' '))

function getCurrentUserId() {
  try {
    const saved = localStorage.getItem('user')
    if (saved) {
      const u = JSON.parse(saved)
      return u?.id || u?.user?.id || null
    }
  } catch (_) {}
  return null
}

async function refresh() {
  if (!isEdit.value) return
  error.value = ''
  loading.value = true
  try {
    const resp = await Visitors.get(id)
    const data = resp?.data?.data ?? resp?.data ?? resp
    first_name.value = data.first_name || ''
    last_name.value  = data.last_name || ''
    username.value   = data.username || ''
    email.value      = data.email || ''
    phone.value      = data.phone || data.profile?.phone || ''
    verified.value   = !!(data.verified ?? data.profile?.verified)
    status.value     = (data.is_active ?? 1) ? 'active' : 'inactive'
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Failed to load visitor'
  } finally {
    loading.value = false
  }
}

function buildPayload() {
  const payload = {
    // users table
    username: username.value,
    first_name: first_name.value,
    last_name: last_name.value,
    full_name: full_name.value,
    email: email.value,
    ...(isEdit.value ? {} : { password: password.value }),
    // visitor_profiles table
    phone: phone.value || undefined,
    verified: verified.value ? 1 : 0,
    is_active: status.value === 'active' ? 1 : 0,
  }
  if (verified.value) {
    const now = new Date()
    const ts = now.toISOString().slice(0, 19).replace('T', ' ')
    payload.verified_at = ts
    const uid = getCurrentUserId()
    if (uid) payload.verified_by = uid
  }
  return payload
}

async function onSubmit() {
  error.value = ''
  loading.value = true
  try {
    // Ensure CSRF token is present (backend requires it)
    try { await AuthAPI.csrf() } catch (_) {}
    const payload = buildPayload()
    if (isEdit.value) {
      await Visitors.update(id, payload)
    } else {
      await Visitors.create(payload)
    }
    router.push('/visitors')
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Save failed'
  } finally {
    loading.value = false
  }
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/visitors')
}

onMounted(() => {
  if (isEdit.value) refresh()
})
</script>

<style scoped>
.page-title { font-size: 20px; font-weight: 600; margin: 16px 0; }
.form { max-width: 880px; }
.row { display:flex; gap: 12px; margin-bottom: 10px; }
.col { flex:1; }
.lbl { display:block; font-size:12px; color:#555; margin-bottom:4px; }
.lbl.inline { display:inline-flex; align-items:center; gap:8px; margin-top:8px; }
.input { width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:4px; }
.actions { margin-top: 12px; }
.btn { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 4px; background:#f9fafb; cursor:pointer; }
.btn-primary { background:#2563eb; color:#fff; border-color:#1d4ed8; }
.error { color:#b00; margin-top:8px; }
.hint { color:#667085; font-size:12px; margin-top:4px; }
.p-4 { padding: 16px; }
.space-x-2 > * + * { margin-left: 8px; }
</style>