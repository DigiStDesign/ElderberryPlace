

<template>
  <section style="max-width: 760px;">
    <h2>{{ isEdit ? 'Edit Visit' : 'New Visit' }}</h2>
    <p v-if="loading">Saving/Loading…</p>

    <form @submit.prevent="save">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div>
          <label>Resident</label>
          <select v-model="model.resident_id" :class="{'err': submitted && !model.resident_id}">
            <option :value="''" disabled>Select resident</option>
            <option v-for="r in residentOptions" :key="r.id" :value="r.id">
              {{ r.first_name }} {{ r.last_name }} (Room {{ r.room_no || '—' }})
            </option>
          </select>
          <small v-if="submitted && !model.resident_id" class="help">Required</small>
        </div>

        <div>
          <label>Visitor</label>
          <select v-model="model.visitor_id" :class="{'err': submitted && !model.visitor_id}">
            <option :value="''" disabled>Select visitor</option>
            <option v-for="v in visitorOptions" :key="v.id" :value="v.id">
              {{ v.full_name }} — {{ v.phone || 'no phone' }}
            </option>
          </select>
          <small v-if="submitted && !model.visitor_id" class="help">Required</small>
        </div>

        <div>
          <label>Check-in</label>
          <input type="datetime-local" v-model="model.check_in_at" :class="{'err': submitted && !model.check_in_at}" />
          <small v-if="submitted && !model.check_in_at" class="help">Required</small>
        </div>

        <div>
          <label>Check-out (optional)</label>
          <input type="datetime-local" v-model="model.check_out_at" />
        </div>

        <div style="grid-column: 1 / -1;">
          <label>Purpose</label>
          <input v-model.trim="model.purpose" placeholder="e.g., Family visit" />
        </div>

        <div style="grid-column: 1 / -1;">
          <label>Notes</label>
          <textarea v-model.trim="model.notes" rows="3" placeholder="Optional notes…"></textarea>
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
import Visits from '@/api/visits'
import Residents from '@/api/residents'
import Visitors from '@/api/visitors'

const route = useRoute()
const router = useRouter()

const id = route.params.id
const isEdit = computed(() => !!id)

const model = reactive({
  resident_id: '',
  visitor_id: '',
  check_in_at: '',
  check_out_at: '',
  purpose: '',
  notes: ''
})

const residentOptions = ref([])
const visitorOptions = ref([])

const error = ref('')
const saved = ref(false)
const loading = ref(false)
const submitted = ref(false)

async function loadOptions() {
  try {
    // Load a reasonable page of options; adjust params to match your API if needed
    const [rRes, vRes] = await Promise.all([
      Residents.list({ page: 1, per_page: 100 }),
      Visitors.list({ page: 1, per_page: 100 })
    ])
    const rPayload = rRes?.data
    const vPayload = vRes?.data
    residentOptions.value = Array.isArray(rPayload) ? rPayload : (rPayload?.data ?? [])
    visitorOptions.value = Array.isArray(vPayload) ? vPayload : (vPayload?.data ?? [])
  } catch (e) {
    console.error(e)
    // Non-fatal; we still allow manual save if IDs were prefilled
  }
}

async function loadEntity() {
  if (!isEdit.value) return
  try {
    const resp = await Visits.get(id)
    const payload = resp?.data
    const entity = (payload && payload.data) ? payload.data : payload
    if (entity && typeof entity === 'object') {
      Object.assign(model, {
        resident_id: entity.resident_id ?? '',
        visitor_id:  entity.visitor_id ?? '',
        check_in_at: (entity.check_in_at || '').slice(0,16), // expect ISO; trim seconds/zone for input
        check_out_at:(entity.check_out_at || '').slice(0,16),
        purpose:     entity.purpose ?? '',
        notes:       entity.notes ?? ''
      })
    }
  } catch (e) {
    console.error(e)
    throw e
  }
}

async function load() {
  error.value = ''
  loading.value = true
  try {
    await Promise.all([loadOptions(), loadEntity()])
  } catch (e) {
    error.value = 'Failed to load visit'
  } finally {
    loading.value = false
  }
}

async function save() {
  submitted.value = true
  error.value = ''
  saved.value = false

  if (!model.resident_id || !model.visitor_id || !model.check_in_at) {
    error.value = 'Please fill the required fields'
    return
  }

  loading.value = true
  try {
    const payload = {
      resident_id: model.resident_id,
      visitor_id:  model.visitor_id,
      check_in_at: model.check_in_at ? new Date(model.check_in_at).toISOString() : null,
      check_out_at:model.check_out_at ? new Date(model.check_out_at).toISOString() : null,
      purpose:     model.purpose,
      notes:       model.notes
    }
    if (isEdit.value) {
      await Visits.update(id, payload)
    } else {
      await Visits.create(payload)
    }
    saved.value = true
    router.push('/visits')
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Save failed'
  } finally {
    loading.value = false
  }
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/visits')
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