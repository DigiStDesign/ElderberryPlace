<template>
  <section style="max-width: 720px;">
    <h2>{{ isEdit ? 'Edit Resident' : 'New Resident' }}</h2>
    <p v-if="loading">Saving/Loading…</p>

    <form @submit.prevent="save">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div>
          <label>Username</label>
          <input
            v-model.trim="model.username"
            :class="{'err': submitted && !model.username}"
            placeholder="e.g., jsmith"
          />
          <small v-if="submitted && !model.username" class="help">Required</small>
        </div>

        <div>
          <label>Full name</label>
          <input
            v-model.trim="model.full_name"
            :class="{'err': submitted && !model.full_name}"
            placeholder="e.g., John Smith"
          />
          <small v-if="submitted && !model.full_name" class="help">Required</small>
        </div>

        <div>
          <label>Email</label>
          <input v-model.trim="model.email" type="email" placeholder="name@example.com" />
        </div>

        <div>
          <label>Password <span v-if="isEdit">(leave blank to keep)</span></label>
          <input
            type="password"
            v-model.trim="model.password"
            :class="{'err': submitted && !isEdit && !model.password}"
            placeholder="Minimum 6 characters"
          />
          <small v-if="submitted && !isEdit && !model.password" class="help">Required for new resident</small>
        </div>

        <div>
          <label>Room number</label>
          <input v-model.trim="model.room_number" placeholder="e.g., 12B" />
        </div>

        <div>
          <label>Date of birth</label>
          <input v-model="model.dob" type="date" />
        </div>

        <div style="grid-column: 1 / -1;">
          <label>Care notes</label>
          <textarea v-model.trim="model.care_notes" rows="4" style="width:100%;" placeholder="Optional notes…"></textarea>
        </div>
      </div>

      <div style="margin-top:12px; display:flex; gap:8px;">
        <button type="submit" :disabled="loading">{{ isEdit ? 'Update' : 'Create' }}</button>
        <button type="button" @click="goBack" :disabled="loading">Cancel</button>
      </div>

      <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
      <p v-if="saved" style="color:#070; margin-top:8px;">Saved.</p>
    </form>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Residents from '@/api/residents'

const route = useRoute()
const router = useRouter()
const id = route.params.id
const isEdit = computed(() => !!id)

const model = reactive({
  username: '',
  full_name: '',
  email: '',
  password: '',
  room_number: '',
  dob: '',
  care_notes: ''
})

const error = ref('')
const saved = ref(false)
const loading = ref(false)
const submitted = ref(false)

function normalizeDate(v) {
  if (!v) return ''
  // Accept 'YYYY-MM-DD', ISO timestamps, or Date objects
  try {
    if (typeof v === 'string') return v.slice(0, 10)
    if (v instanceof Date) return v.toISOString().slice(0, 10)
  } catch (_) {}
  return ''
}

function pickItem(payload) {
  if (!payload) return null
  if (payload.data?.item) return payload.data.item
  if (Array.isArray(payload.data?.items)) return payload.data.items[0] || null
  if (Array.isArray(payload.items)) return payload.items[0] || null
  if (Array.isArray(payload.data)) return payload.data[0] || null
  if (Array.isArray(payload)) return payload[0] || null
  if (Array.isArray(payload.residents)) return payload.residents[0] || null
  return payload.data || payload
}

async function load() {
  if (!isEdit.value) return
  error.value = ''
  loading.value = true
  try {
    const resp = await Residents.detail(id)
    const entity = pickItem(resp?.data)
    if (entity && typeof entity === 'object') {
      const user    = entity.user || entity.users || {}
      const profile = entity.profile || entity.resident_profile || {}
      Object.assign(model, {
        username:    entity.username ?? user.username ?? '',
        full_name:   entity.full_name ?? user.full_name ?? [entity.first_name, entity.last_name].filter(Boolean).join(' '),
        email:       entity.email ?? user.email ?? '',
        password:    '', // never prefill password
        room_number: entity.room_number ?? entity.room_no ?? profile.room_number ?? '',
        dob:         normalizeDate(entity.dob ?? profile.dob ?? ''),
        care_notes:  entity.care_notes ?? profile.care_notes ?? ''
      })
    } else {
      throw new Error('Resident not found')
    }
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Failed to load resident'
  } finally {
    loading.value = false
  }
}

async function save() {
  submitted.value = true
  error.value = ''
  saved.value = false

  if (!model.username || !model.full_name || (!isEdit.value && !model.password)) {
    error.value = 'Please fill username, full name, and password (for new resident).'
    return
  }

  loading.value = true
  try {
    const payload = {
      // User fields
      username:    (model.username || '').trim(),
      full_name:   (model.full_name || '').trim(),
      email:       model.email ? model.email.trim() : null,
      // Resident profile fields
      room_number: model.room_number ? model.room_number.trim() : null,
      dob:         model.dob || null,
      care_notes:  model.care_notes ? model.care_notes.trim() : null
    }
    if (model.password) payload.password = model.password

    if (isEdit.value) {
      await Residents.update(id, payload)
    } else {
      await Residents.create(payload)
    }
    saved.value = true
    router.push('/residents')
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Save failed'
  } finally {
    loading.value = false
  }
}

function goBack() { router.push('/residents') }

onMounted(load)
</script>

<style scoped>
input, textarea, button {
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