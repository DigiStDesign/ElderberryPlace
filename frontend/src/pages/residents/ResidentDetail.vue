

<template>
  <section class="p-4">
    <header class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">
        {{ fullName || 'Resident' }}
      </h1>
      <div class="space-x-2">
        <button class="btn" @click="goBack">Back</button>
        <button class="btn btn-primary" @click="goEdit" v-if="item">Edit</button>
      </div>
    </header>

    <div v-if="loading">Loading…</div>
    <p v-else-if="error" style="color:#b00;">{{ error }}</p>

    <div v-else-if="!item">
      <p>No resident found.</p>
    </div>

    <div v-else class="grid gap-4">
      <!-- Basic info -->
      <section class="card">
        <h2 class="font-semibold mb-2">Basic details</h2>
        <ul class="detail-list">
          <li><strong>First name:</strong> {{ item.first_name || '—' }}</li>
          <li><strong>Last name:</strong> {{ item.last_name || '—' }}</li>
          <li><strong>Date of birth:</strong> {{ item.dob || '—' }}</li>
          <li><strong>Gender:</strong> {{ item.gender || '—' }}</li>
          <li><strong>Status:</strong> {{ item.status || '—' }}</li>
          <li><strong>Room:</strong> {{ item.room_no || '—' }}</li>
          <li><strong>Admission date:</strong> {{ item.admission_date || '—' }}</li>
        </ul>
      </section>

      <!-- Contact -->
      <section class="card">
        <h2 class="font-semibold mb-2">Primary contact</h2>
        <ul class="detail-list">
          <li><strong>Name:</strong> {{ item.primary_contact_name || '—' }}</li>
          <li><strong>Phone:</strong> {{ item.primary_contact_phone || '—' }}</li>
        </ul>
      </section>

      <!-- Health summary (optional fields) -->
      <section class="card" v-if="hasHealth">
        <h2 class="font-semibold mb-2">Health summary</h2>
        <ul class="detail-list">
          <li v-if="Array.isArray(item.allergies)"><strong>Allergies:</strong> {{ item.allergies.length ? item.allergies.join(', ') : '—' }}</li>
          <li v-if="Array.isArray(item.conditions)"><strong>Conditions:</strong> {{ item.conditions.length ? item.conditions.join(', ') : '—' }}</li>
          <li v-if="item.notes"><strong>Notes:</strong> {{ item.notes }}</li>
        </ul>
      </section>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Residents from '@/api/residents'

const route = useRoute()
const router = useRouter()

const item = ref(null)
const loading = ref(false)
const error = ref('')

const id = route.params.id

const fullName = computed(() => {
  if (!item.value) return ''
  const f = item.value.first_name || ''
  const l = item.value.last_name || ''
  return [f, l].filter(Boolean).join(' ')
})

const hasHealth = computed(() => {
  const it = item.value || {}
  return (Array.isArray(it.allergies) && it.allergies.length) ||
         (Array.isArray(it.conditions) && it.conditions.length) ||
         !!it.notes
})

async function load() {
  error.value = ''
  loading.value = true
  try {
    const resp = await Residents.get(id)
    const payload = resp?.data
    // Support both { data: {...} } and plain object
    item.value = (payload && payload.data) ? payload.data : payload
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load resident'
    item.value = null
  } finally {
    loading.value = false
  }
}

function goEdit() {
  if (!item.value) return
  router.push(`/residents/${id}/edit`)
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/residents')
}

onMounted(load)
</script>

<style scoped>
.card {
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
}
.detail-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 6px 16px;
  list-style: none;
  padding: 0;
  margin: 0;
}
.btn {
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  background: #f9fafb;
  cursor: pointer;
}
.btn-primary {
  background: #2563eb;
  color: white;
  border-color: #1d4ed8;
}
</style>