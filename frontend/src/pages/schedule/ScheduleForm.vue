<template>
  <section style="max-width: 760px;">
    <h2>{{ isEdit ? 'Edit Schedule Entry' : 'New Schedule Entry' }}</h2>
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
          <label>Staff</label>
          <select v-model="model.staff_id" :class="{'err': submitted && !model.staff_id}">
            <option :value="''" disabled>Select staff</option>
            <option v-for="s in staffOptions" :key="s.id" :value="s.id">
              {{ s.first_name }} {{ s.last_name }} — {{ s.role || 'Staff' }}
            </option>
          </select>
          <small v-if="submitted && !model.staff_id" class="help">Required</small>
        </div>

        <div>
          <label>Service</label>
          <select v-model="model.service_id" :class="{'err': submitted && !model.service_id}">
            <option :value="''" disabled>Select service</option>
            <option v-for="svc in serviceOptions" :key="svc.id" :value="svc.id">
              {{ svc.name }} <span v-if="svc.rate">— ${{ Number(svc.rate).toFixed(2) }}</span>
            </option>
          </select>
          <small v-if="submitted && !model.service_id" class="help">Required</small>
        </div>

        <div>
          <label>Start</label>
          <input type="datetime-local" v-model="model.start_at" :class="{'err': submitted && !model.start_at}" />
          <small v-if="submitted && !model.start_at" class="help">Required</small>
        </div>

        <div>
          <label>End</label>
          <input type="datetime-local" v-model="model.end_at" :class="{'err': submitted && !endOk}" />
          <small v-if="submitted && !endOk" class="help">End must be after start</small>
        </div>

        <div>
          <label>Status</label>
          <select v-model="model.status">
            <option value="scheduled">Scheduled</option>
            <option value="complete">Complete</option>
            <option value="cancelled">Cancelled</option>
          </select>
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
import Schedule from '@/api/schedule'
import Residents from '@/api/residents'
import Staff from '@/api/staff'
import Services from '@/api/services'

const route = useRoute()
const router = useRouter()

const id = route.params.id
const isEdit = computed(() => !!id)

const model = reactive({
  resident_id: '',
  staff_id: '',
  service_id: '',
  start_at: '',
  end_at: '',
  status: 'scheduled',
  notes: ''
})

const residentOptions = ref([])
const staffOptions = ref([])
const serviceOptions = ref([])

const error = ref('')
const saved = ref(false)
const loading = ref(false)
const submitted = ref(false)

const endOk = computed(() => {
  if (!model.start_at || !model.end_at) return true
  try {
    return new Date(model.end_at) > new Date(model.start_at)
  } catch { return false }
})

async function loadOptions() {
  try {
    const [rRes, sRes, svcRes] = await Promise.all([
      Residents.list({ page: 1, per_page: 100 }),
      Staff.list({ page: 1, per_page: 100 }),
      Services.list({ page: 1, per_page: 100 })
    ])
    const rPayload = rRes?.data
    const sPayload = sRes?.data
    const svcPayload = svcRes?.data
    residentOptions.value = Array.isArray(rPayload) ? rPayload : (rPayload?.data ?? [])
    staffOptions.value = Array.isArray(sPayload) ? sPayload : (sPayload?.data ?? [])
    serviceOptions.value = Array.isArray(svcPayload) ? svcPayload : (svcPayload?.data ?? [])
  } catch (e) {
    console.error(e)
    // Non-fatal
  }
}

async function loadEntity() {
  if (!isEdit.value) return
  try {
    const resp = await Schedule.get(id)
    const payload = resp?.data
    const entity = (payload && payload.data) ? payload.data : payload
    if (entity && typeof entity === 'object') {
      Object.assign(model, {
        resident_id: entity.resident_id ?? '',
        staff_id:    entity.staff_id ?? '',
        service_id:  entity.service_id ?? '',
        start_at:    (entity.start_at || entity.scheduled_for || '').slice(0,16),
        end_at:      (entity.end_at || '').slice(0,16),
        status:      entity.status ?? 'scheduled',
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
    error.value = 'Failed to load schedule entry'
  } finally {
    loading.value = false
  }
}

async function save() {
  submitted.value = true
  error.value = ''
  saved.value = false

  if (!model.resident_id || !model.staff_id || !model.service_id || !model.start_at || !endOk.value) {
    error.value = 'Please fix the highlighted fields'
    return
  }

  loading.value = true
  try {
    const payload = {
      resident_id: model.resident_id,
      staff_id:    model.staff_id,
      service_id:  model.service_id,
      start_at:    model.start_at ? new Date(model.start_at).toISOString() : null,
      end_at:      model.end_at ? new Date(model.end_at).toISOString() : null,
      status:      model.status,
      notes:       model.notes
    }
    if (isEdit.value) {
      await Schedule.update(id, payload)
    } else {
      await Schedule.create(payload)
    }
    saved.value = true
    router.push('/schedule')
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Save failed'
  } finally {
    loading.value = false
  }
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/schedule')
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
