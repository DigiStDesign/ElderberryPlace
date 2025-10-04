<template>
  <section style="max-width: 720px;">
    <h2>{{ isEdit ? 'Edit Staff' : 'New Staff' }}</h2>
    <p v-if="loading">Saving/Loading…</p>

    <form @submit.prevent="save">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div>
          <label>Username</label>
          <input v-model.trim="model.username" :class="{'err': submitted && !model.username}" placeholder="e.g., jdoe" />
          <small v-if="submitted && !model.username" class="help">Required</small>
        </div>

        <div>
          <label>Full name</label>
          <input v-model.trim="model.full_name" :class="{'err': submitted && !model.full_name}" placeholder="e.g., Jane Doe" />
          <small v-if="submitted && !model.full_name" class="help">Required</small>
        </div>

        <div>
          <label>Email</label>
          <input v-model.trim="model.email" type="email" :class="{'err': submitted && !emailOk}" placeholder="name@example.com" />
          <small v-if="submitted && !emailOk" class="help">Valid email required</small>
        </div>

        <div>
          <label>Password <span v-if="isEdit">(leave blank to keep)</span></label>
          <input type="password" v-model.trim="model.password" :class="{'err': submitted && !isEdit && !model.password}" placeholder="Minimum 6 chars" />
          <small v-if="submitted && !isEdit && !model.password" class="help">Required for new staff</small>
        </div>

        <div>
          <label>Role</label>
          <select v-model="model.staff_job_id" :class="{'err': submitted && !model.staff_job_id}">
            <option disabled value="">Select a role</option>
            <option v-for="j in jobOptions" :key="j.id" :value="j.id">
              {{ j.title || j.name || ('Job #' + j.id) }}
            </option>
          </select>
          <small v-if="submitted && !model.staff_job_id" class="help">Required</small>
        </div>

        <div>
          <label>Started on</label>
          <input type="date" v-model="model.started_on" />
        </div>

        <div style="grid-column: 1 / -1;">
          <label>Notes</label>
          <textarea v-model.trim="model.notes" rows="4"></textarea>
        </div>
      </div>

      <div style="margin-top:12px; display:flex; gap:8px;">
        <button type="submit" :disabled="loading">{{ isEdit ? 'Update' : 'Create' }}</button>
        <button type="button" @click="goBack" :disabled="loading">Cancel</button>
      </div>

      <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
      <p v-if="saved" style="color:#0a0;">Saved!</p>
    </form>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Staff from '@/api/staff'

const route = useRoute()
const router = useRouter()

const id = route.params.id
const isEdit = computed(() => !!id)

const model = reactive({
  // user fields
  username: '',
  full_name: '',
  email: '',
  password: '', // only required on create
  // staff_profiles fields
  staff_job_id: '',
  started_on: '',
  notes: ''
})

const jobOptions = ref([])

const error = ref('')
const saved = ref(false)
const loading = ref(false)
const submitted = ref(false)

const emailOk = computed(() => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(model.email || ''))

async function loadJobs() {
  try {
    const resp = await (Staff.jobs ? Staff.jobs() : Staff.list({ jobs: 1 }))
    const payload = resp?.data
    const arr = Array.isArray(payload) ? payload : (payload?.data ?? payload?.items ?? [])
    jobOptions.value = arr.map(j => ({ id: j.id ?? j.job_id ?? j.value ?? '', title: j.title ?? j.name ?? j.label ?? '' })).filter(j => j.id)
  } catch (e) {
    console.warn('[StaffForm] Failed to load jobs', e)
    jobOptions.value = []
  }
}

async function load() {
  error.value = ''
  loading.value = true
  try {
    await loadJobs()
    if (!isEdit.value) return
    const resp = await Staff.get(id)
    const payload = resp?.data
    const entity = (payload && payload.data) ? payload.data : payload
    if (entity && typeof entity === 'object') {
      Object.assign(model, {
        username:     entity.username ?? '',
        full_name:    entity.full_name ?? [entity.first_name, entity.last_name].filter(Boolean).join(' '),
        email:        entity.email ?? '',
        password:     '', // never prefill
        staff_job_id: entity.staff_job_id ?? entity.role_id ?? '',
        started_on:   entity.started_on ?? entity.start_date ?? '',
        notes:        entity.notes ?? ''
      })
    }
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load staff'
  } finally {
    loading.value = false
  }
}

async function save() {
  submitted.value = true
  error.value = ''
  saved.value = false

  // basic validation aligned to backend expectations
  if (!model.username || !model.full_name || !emailOk.value || !model.staff_job_id || (!isEdit.value && !model.password)) {
    error.value = 'Please fill username, full name, valid email, role, and password (for new staff).'
    return
  }

  loading.value = true
  try {
    const payload = {
      // user
      username:     model.username,
      full_name:    model.full_name,
      email:        model.email,
      // staff profile
      staff_job_id: model.staff_job_id,
      started_on:   model.started_on || null,
      notes:        model.notes || null
    }
    if (!isEdit.value && model.password) payload.password = model.password
    if (isEdit.value && model.password)  payload.password = model.password

    if (isEdit.value) {
      await Staff.update(id, payload)
    } else {
      await Staff.create(payload)
    }
    saved.value = true
    router.push('/staff')
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Save failed'
  } finally {
    loading.value = false
  }
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/staff')
}

onMounted(load)
</script>

<style scoped>
input, select, textarea, button {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  box-sizing: border-box;
}
label {
  display:block;
  font-weight:600;
  margin-bottom:4px;
}
textarea { resize: vertical; }
.help { color:#b00; font-size:12px; }
.err { border-color:#dc2626; outline-color:#dc2626; }
</style>