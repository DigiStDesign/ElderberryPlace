<template>
  <section class="p-4">
    <h2 class="page-title">Visitors</h2>

    <!-- Toolbar: search (left) + actions (right) to match Residents/Staff -->
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
        <router-link class="btn" :to="{ name: 'visitors.new' }">New Visitor</router-link>
      </div>
    </div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="rows.length === 0" class="empty">No visitors found.</div>

      <DataTable
        v-else
        :headers="headers"
        :rows="rows"
        rowKey="id"
        @edit="editRow"
        @delete="deleteRow"
      >
        <!-- Make Full Name clickable to edit -->
        <template #cell-full_name="{ row }">
          <router-link :to="{ name: 'visitors.edit', params: { id: row.id } }" class="link">
            {{ row.full_name || '—' }}
          </router-link>
        </template>
      </DataTable>
    </template>

    <p v-if="error" class="error">{{ error }}</p>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import Visitors from '@/api/visitors'

const router = useRouter()

// Keep your existing columns; layout now matches other lists
const headers = [
  { key: 'id',         label: 'ID' },
  { key: 'full_name',  label: 'Full Name' },
  { key: 'relation',   label: 'Relation' },
  { key: 'phone',      label: 'Phone' },
  { key: 'status',     label: 'Status' },
  { key: 'actions',    label: 'Actions' }
]

const rows = ref([])
const loading = ref(false)
const error = ref('')
const q = ref('')

function asItems(payload) {
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.items)) return payload.items
  if (Array.isArray(payload)) return payload
  return []
}

async function refresh() {
  error.value = ''
  loading.value = true
  try {
    const resp = await Visitors.list(q.value ? { search: q.value } : {})
    const items = asItems(resp?.data)

    rows.value = items.map(v => {
      const id = v.id ?? v.user_id ?? v.visitor_id ?? null
      const full = v.full_name || [v.first_name, v.last_name].filter(Boolean).join(' ')
      const relation = v.relationship ?? v.relation ?? ''
      const phone = v.phone ?? v.profile?.phone ?? ''
      const status = (v.is_active ?? 1) ? 'Active' : 'Inactive'
      return { id, full_name: full, relation, phone, status }
    })
  } catch (e) {
    console.error(e)
    const resp = e?.response?.data
    error.value = resp?.message || resp?.error?.message || 'Failed to load visitors'
    rows.value = []
  } finally {
    loading.value = false
  }
}

function editRow(row) {
  router.push(`/visitors/${row.id}/edit`)
}

async function deleteRow(row) {
  if (!confirm('Delete this visitor?')) return
  error.value = ''
  loading.value = true
  try {
    await Visitors.remove(row.id)
    await refresh()
  } catch (e) {
    console.error(e)
    const resp = e?.response?.data
    error.value = resp?.message || resp?.error?.message || 'Delete failed'
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
.btn { padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 4px; background:#f9fafb; cursor:pointer; text-decoration:none; }
.link { color:#2563eb; text-decoration:none; }
.link:hover { text-decoration:underline; }
.empty { padding:8px; color:#555; }
.error { color:#b00; margin-top: 8px; }
.p-4 { padding: 16px; }
.space-x-2 > * + * { margin-left: 8px; }
</style>