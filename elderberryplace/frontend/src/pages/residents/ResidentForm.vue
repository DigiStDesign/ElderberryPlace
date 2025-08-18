<template>
  <section style="max-width: 720px;">
    <h2>{{ isEdit ? 'Edit Resident' : 'New Resident' }}</h2>

    <form @submit.prevent="save">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div>
          <label>First Name</label>
          <input v-model.trim="model.first_name" required />
        </div>
        <div>
          <label>Last Name</label>
          <input v-model.trim="model.last_name" required />
        </div>
        <div>
          <label>Room</label>
          <input v-model.trim="model.room" />
        </div>
        <div>
          <label>Date of Birth</label>
          <input v-model="model.dob" type="date" />
        </div>
        <div style="grid-column: 1 / -1;">
          <label>Notes</label>
          <textarea v-model="model.notes" rows="4" style="width:100%;"></textarea>
        </div>
      </div>

      <div style="margin-top:12px; display:flex; gap:8px;">
        <button type="submit">{{ isEdit ? 'Update' : 'Create' }}</button>
        <button type="button" @click="goBack">Cancel</button>
      </div>

      <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
      <p v-if="saved" style="color:#070; margin-top:8px;">Saved.</p>
    </form>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as Residents from '../../api/residents'

const route = useRoute()
const router = useRouter()
const id = route.params.id
const isEdit = computed(() => !!id)

const model = reactive({
  first_name: '',
  last_name: '',
  room: '',
  dob: '',
  notes: ''
})

const error = ref('')
const saved = ref(false)

async function load() {
  if (!isEdit.value) return
  error.value = ''
  try {
    const data = await Residents.get(id)
    Object.assign(model, data)
  } catch (e) {
    error.value = 'Failed to load resident'
  }
}

async function save() {
  error.value = ''
  saved.value = false
  try {
    if (isEdit.value) await Residents.update(id, model)
    else await Residents.create(model)
    saved.value = true
    router.push('/residents')
  } catch (e) {
    error.value = 'Save failed'
  }
}

function goBack() { router.push('/residents') }

onMounted(load)
</script>