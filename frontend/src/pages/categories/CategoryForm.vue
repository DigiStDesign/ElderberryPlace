

<template>
  <section style="max-width: 640px;">
    <h2>{{ isEdit ? 'Edit Category' : 'New Category' }}</h2>
    <p v-if="loading">Saving/Loading…</p>

    <form @submit.prevent="save">
      <div style="display:grid; grid-template-columns: 1fr; gap:12px;">
        <div>
          <label>Name</label>
          <input
            v-model.trim="model.name"
            :class="{'err': submitted && !model.name}"
            placeholder="e.g., Allied Health"
          />
          <small v-if="submitted && !model.name" class="help">Required</small>
        </div>

        <div>
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
import Categories from '@/api/categories'

const route = useRoute()
const router = useRouter()

const id = route.params.id
const isEdit = computed(() => !!id)

const model = reactive({
  name: '',
  description: ''
})

const error = ref('')
const saved = ref(false)
const loading = ref(false)
const submitted = ref(false)

async function load() {
  if (!isEdit.value) return
  error.value = ''
  loading.value = true
  try {
    const resp = await Categories.get(id)
    const payload = resp?.data
    const entity = (payload && payload.data) ? payload.data : payload
    if (entity && typeof entity === 'object') {
      Object.assign(model, {
        name:        entity.name ?? '',
        description: entity.description ?? ''
      })
    }
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load category'
  } finally {
    loading.value = false
  }
}

async function save() {
  submitted.value = true
  error.value = ''
  saved.value = false

  if (!model.name) {
    error.value = 'Please enter a name'
    return
  }

  loading.value = true
  try {
    const payload = {
      name: model.name,
      description: model.description
    }
    if (isEdit.value) {
      await Categories.update(id, payload)
    } else {
      await Categories.create(payload)
    }
    saved.value = true
    router.push('/categories')
  } catch (e) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Save failed'
  } finally {
    loading.value = false
  }
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/categories')
}

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