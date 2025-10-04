<template>
  <section class="p-4">
    <h2 class="page-title">Staff</h2>

    <!-- Toolbar: search + actions (match Residents page) -->
    <div class="toolbar">
      <div class="search">
        <label class="lbl">Search</label>
        <input
          v-model.trim="q"
          @keyup.enter="refresh"
          class="input"
          placeholder="Name, email, phone…"
        />
      </div>
      <div class="actions space-x-2">
        <button class="btn" @click="refresh">Refresh</button>
        <router-link class="btn" to="/staff/new">New</router-link>
      </div>
    </div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="rows.length === 0" style="padding:8px; color:#555;">No staff found.</div>

      <DataTable
        v-else
        :headers="headers"
        :rows="rows"
        rowKey="id"
        @edit="editRow"
        @delete="deleteRow"
      />
    </template>

    <p v-if="error" class="error">{{ error }}</p>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Staff from '@/api/staff'
import DataTable from '@/components/DataTable.vue'

const router = useRouter()

const headers = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: 'Username' },
  { key: 'full_name', label: 'Full Name' },
  { key: 'email', label: 'Email' },
  { key: 'started_on', label: 'Started On' },
  { key: 'notes', label: 'Notes' },
  { key: 'role', label: 'Role' },
  { key: 'actions', label: 'Actions' }
]

const q = ref('')
const rows = ref([])
const loading = ref(false)
const error = ref('')

async function refresh() {
  error.value = ''
  loading.value = true
  try {
    const resp = await Staff.list({ search: q.value || undefined })
    const payload = resp?.data

    // Parse common shapes
    let items = []
    if (Array.isArray(payload?.data)) {
      items = payload.data
    } else if (Array.isArray(payload?.data?.items)) {
      items = payload.data.items
    } else if (Array.isArray(payload?.items)) {
      items = payload.items
    } else if (Array.isArray(payload)) {
      items = payload
    } else {
      console.warn('[StaffList] Unexpected response shape:', payload)
      items = []
    }

    rows.value = items.map(r => {
      const id = r.id ?? r.user_id ?? null
      return {
        id,
        username:   r.username ?? '',
        full_name:  r.full_name ?? '',
        email:      r.email ?? '',
        started_on: r.started_on ?? r.start_date ?? '',
        notes:      r.notes ?? '',
        role:       r.job_name ?? r.role ?? r.staff_job_id ?? '',
        is_active:  r.is_active ?? null
      }
    })
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load staff'
    rows.value = []
  } finally {
    loading.value = false
  }
}

function editRow(row) {
  router.push(`/staff/${row.id}/edit`)
}

async function deleteRow(row) {
  if (!confirm('Delete this staff member?')) return
  error.value = ''
  loading.value = true
  try {
    await Staff.remove(row.id)
    await refresh()
  } catch (e) {
    console.error(e)
    error.value = 'Delete failed'
  } finally {
    loading.value = false
  }
}

onMounted(refresh)
</script>

<style scoped>
.page-title { font-size: 20px; font-weight: 600; margin: 16px 0; }
.toolbar { display:flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px; }
.search { display:flex; flex-direction: column; }
.lbl { display:block; font-size: 12px; color:#555; margin-bottom: 4px; }
.input { padding: 6px 8px; border: 1px solid #e5e7eb; border-radius: 4px; min-width: 260px; }
.btn { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 4px; background:#f9fafb; cursor:pointer; }
.error { color:#b00; margin-top:8px; }
.p-4 { padding: 16px; }
.space-x-2 > * + * { margin-left: 8px; }
</style>