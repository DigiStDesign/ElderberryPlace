<template>
  <section class="p-4">
    <header class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">
        {{ fullName || 'Staff Member' }}
      </h1>
      <div class="space-x-2">
        <button class="btn" @click="goBack">Back</button>
        <button class="btn btn-primary" @click="goEdit" v-if="item">Edit</button>
      </div>
    </header>

    <div v-if="loading">Loading…</div>
    <p v-else-if="error" class="err">{{ error }}</p>

    <div v-else-if="!item">
      <p>No staff member found.</p>
    </div>

    <div v-else class="grid gap-4">
      <!-- Basic details -->
      <section class="card">
        <h2 class="font-semibold mb-2">Basic details</h2>
        <ul class="detail-list">
          <li><strong>First name:</strong> {{ item.first_name || '—' }}</li>
          <li><strong>Last name:</strong> {{ item.last_name || '—' }}</li>
          <li><strong>Email:</strong> {{ item.email || '—' }}</li>
          <li><strong>Phone:</strong> {{ item.phone || '—' }}</li>
          <li><strong>Role:</strong> {{ item.role || '—' }}</li>
          <li><strong>Status:</strong> {{ item.status || '—' }}</li>
        </ul>
      </section>

      <!-- Notes -->
      <section class="card" v-if="item.notes">
        <h2 class="font-semibold mb-2">Notes</h2>
        <p>{{ item.notes }}</p>
      </section>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Staff from '@/api/staff'

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

async function load() {
  error.value = ''
  loading.value = true
  try {
    const resp = await Staff.get(id)
    const payload = resp?.data
    item.value = (payload && payload.data) ? payload.data : payload
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load staff'
    item.value = null
  } finally {
    loading.value = false
  }
}

function goEdit() {
  if (!item.value) return
  router.push(`/staff/${id}/edit`)
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/staff')
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
.p-4 { padding: 16px; }
.text-xl { font-size: 20px; }
.font-semibold { font-weight: 600; }
.mb-4 { margin-bottom: 16px; }
.space-x-2 > * + * { margin-left: 8px; }
.grid { display: grid; }
.gap-4 { gap: 16px; }
.err { color:#b00; }
</style>
