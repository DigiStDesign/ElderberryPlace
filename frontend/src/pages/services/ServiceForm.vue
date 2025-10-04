<template>
  <section style="max-width: 720px;">
    <h2>{{ isEdit ? 'Edit Service' : 'New Service' }}</h2>
    <p v-if="loading">Saving/Loading…</p>

    <form @submit.prevent="save">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div style="grid-column: 1 / -1;">
          <label>Service name</label>
          <input
            v-model.trim="model.name"
            :class="{'err': submitted && !model.name}"
            placeholder="e.g., Physiotherapy session"
          />
          <small v-if="submitted && !model.name" class="help">Required</small>
        </div>

        <div>
          <label>Category</label>
          <select v-model="model.category_id" :class="{'err': submitted && !model.category_id}">
            <option disabled :value="''">Select category</option>
            <option v-for="c in categoryOptions" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
          <small v-if="submitted && !model.category_id" class="help">Required</small>
        </div>

        <div>
          <label>Cost (AUD)</label>
          <input
            v-model.trim="model.cost"
            type="number"
            step="0.01"
            min="0"
            :class="{'err': submitted && !costOk}"
            placeholder="e.g., 79.95"
          />
          <small v-if="submitted && !costOk" class="help">Enter a valid non‑negative number</small>
        </div>

        <div>
          <label>Frequency</label>
          <select v-model="model.frequency" :class="{'err': submitted && !model.frequency}">
            <option disabled value="">Select frequency</option>
            <option value="Daily">Daily</option>
            <option value="Weekly">Weekly</option>
            <option value="Monthly">Monthly</option>
            <option value="Ad-hoc">Ad-hoc</option>
          </select>
          <small v-if="submitted && !model.frequency" class="help">Required</small>
        </div>

        <div>
          <label>Status</label>
          <select v-model="model.status" :class="{'err': submitted && !model.status}">
            <option disabled value="">Select status</option>
            <option value="Active">Active</option>
            <option value="Scheduled">Scheduled</option>
            <option value="Inactive">Inactive</option>
          </select>
          <small v-if="submitted && !model.status" class="help">Required</small>
        </div>

        <div style="grid-column: 1 / -1;">
          <label>Description</label>
          <textarea v-model.trim="model.description" rows="4" placeholder="Optional description…"></textarea>
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
import Services from '@/api/services'
import Categories from '@/api/categories'

const route = useRoute()
const router = useRouter()

const id = route.params.id
const isEdit = computed(() => !!id)

const model = reactive({
  name: '',
  category_id: '',
  cost: '',
  description: '',
  frequency: '',
  status: ''
})

const categoryOptions = ref([])

const error = ref('')
const saved = ref(false)
const loading = ref(false)
const submitted = ref(false)

const costOk = computed(() => {
  if (model.cost === '' || model.cost === null || model.cost === undefined) return false
  const n = Number(model.cost)
  return !isNaN(n) && n >= 0
})

async function loadCategories() {
  try {
    const resp = await Categories.list({ page: 1, per_page: 100 })
    const payload = resp?.data
    categoryOptions.value = Array.isArray(payload) ? payload : (payload?.data ?? [])
  } catch (e) {
    console.error(e)
    // Keep empty options if fetch fails
  }
}

async function loadEntity() {
  if (!isEdit.value) return
  try {
    const resp = await Services.get(id)
    const payload = resp?.data
    const entity = (payload && payload.data) ? payload.data : payload
    if (entity && typeof entity === 'object') {
      Object.assign(model, {
        name:        entity.name ?? '',
        category_id: entity.category_id ?? '',
        cost:        entity.cost ?? entity.rate ?? '',
        description: entity.description ?? '',
        frequency:   entity.frequency ?? '',
        status:      entity.status ?? ''
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
    await Promise.all([loadCategories(), loadEntity()])
  } catch (e) {
    error.value = 'Failed to load service'
  } finally {
    loading.value = false
  }
}

async function save() {
  submitted.value = true
  error.value = ''
  saved.value = false

  if (!model.name || !model.category_id || !costOk.value || !model.frequency || !model.status) {
    error.value = 'Please fix the highlighted fields'
    return
  }

  loading.value = true
  try {
    const payload = {
      name: model.name,
      category_id: model.category_id,
      cost: Number(model.cost),
      description: model.description,
      frequency: model.frequency,
      status: model.status
    }
    if (isEdit.value) {
      await Services.update(id, payload)
    } else {
      await Services.create(payload)
    }
    saved.value = true
    router.push('/services')
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Save failed'
  } finally {
    loading.value = false
  }
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/services')
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