<template>
  <section class="p-4">
    <h2 class="page-title">Services</h2>

    <!-- Toolbar: search (left) + actions (right) -->
    <div class="toolbar">
      <div class="search">
        <label class="lbl">Search</label>
        <input
          v-model.trim="q"
          @keyup.enter="refresh"
          class="input"
          placeholder="Name or category…"
        />
      </div>
      <div class="actions space-x-2">
        <button class="btn" @click="refresh">Refresh</button>
        <router-link class="btn" :to="{ name: 'services.new' }">New Service</router-link>
      </div>
    </div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="!error && rows.length === 0" class="empty">No services found.</div>

      <DataTable
        v-else-if="rows.length > 0"
        :headers="headers"
        :rows="rows"
        rowKey="id"
        :show-actions="true"
        @edit="editRow"
        @delete="deleteRow"
      >
        <!-- Common slot API: cell for a specific key -->
        <template #cell-actions="{ row }">
          <div class="action-buttons">
            <router-link
              :to="{ name: 'services.edit', params: { id: row.id } }"
              class="btn btn-sm"
              aria-label="Edit Service"
            >Edit</router-link>
            <button
              type="button"
              class="btn btn-sm btn-danger"
              @click.prevent="deleteRow(row)"
              aria-label="Delete Service"
            >Delete</button>
          </div>
        </template>

        <!-- Alternate slot API some tables use -->
        <template #actions="{ row }">
          <div class="action-buttons">
            <router-link
              :to="{ name: 'services.edit', params: { id: row.id } }"
              class="btn btn-sm"
              aria-label="Edit Service"
            >Edit</router-link>
            <button
              type="button"
              class="btn btn-sm btn-danger"
              @click.prevent="deleteRow(row)"
              aria-label="Delete Service"
            >Delete</button>
          </div>
        </template>
      </DataTable>
    </template>

    <p v-if="error" class="error">{{ error }}</p>

    <details v-if="error" class="mt-2">
      <summary>Details</summary>
      <pre style="white-space:pre-wrap">{{ error }}</pre>
    </details>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import Services from '@/api/services'

const router = useRouter()

const headers = [
  { key: 'name',           label: 'Service' },
  { key: 'cost',           label: 'Cost' },
  { key: 'status',         label: 'Status' },
  { key: 'frequency',      label: 'Frequency' },
  { key: 'actions',        label: 'Actions' }
]

const rows = ref([])
const loading = ref(false)
const error = ref('')
const q = ref('')

function toItems(payload) {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.items)) return payload.items
  if (Array.isArray(payload?.services)) return payload.services
  if (Array.isArray(payload?.result)) return payload.result
  if (Array.isArray(payload?.records)) return payload.records
  return []
}

function pickCategoryName(s) {
  if (s.category_name) return s.category_name
  if (typeof s.category === 'string') return s.category
  if (s.category && typeof s.category === 'object') {
    return s.category.name || s.category.title || ''
  }
  return ''
}

function formatMoney(v) {
  if (v == null || v === '') return ''
  const n = Number(v)
  return Number.isFinite(n) ? n.toFixed(2) : String(v)
}

async function refresh() {
  error.value = ''
  loading.value = true
  try {
    const resp = await Services.list({ search: q.value || undefined })
    console.log('[ServicesList] resp.data =', resp?.data)
    const items = toItems(resp?.data)

    rows.value = items.map(s => {
      const id = s.id ?? s.service_id ?? s.uuid ?? undefined
      const name = s.name ?? s.service_name ?? s.title ?? ''
      const category_name = pickCategoryName(s) // kept if backend supplies it; otherwise empty
      const cost = formatMoney(s.cost ?? s.rate ?? s.price ?? s.fee)
      const status = s.status ?? ((s.is_active ?? 1) ? 'Active' : 'Inactive')
      const frequency = s.frequency ?? ''
      return { id, name, category_name, cost, status, frequency }
    })
    // Ensure every row has an id for rowKey
    rows.value = rows.value.map((r, i) => ({ ...r, id: (r.id ?? i + 1) }))
  } catch (e) {
    console.error(e)
    const resp = e?.response?.data
    error.value = resp?.message || resp?.error?.message || 'Failed to load services'
    rows.value = []
  } finally {
    loading.value = false
  }
}

function editRow(row) {
  router.push({ name: 'services.edit', params: { id: row.id } })
}

async function deleteRow(row) {
  if (!confirm('Delete this service?')) return
  error.value = ''
  loading.value = true
  try {
    await Services.remove(row.id)
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
.btn-primary { background:#2563eb; color:#fff; border-color:#1d4ed8; }
.empty { padding:8px; color:#555; }
.error { color:#b00; margin-top:8px; }
.p-4 { padding: 16px; }
.space-x-2 > * + * { margin-left: 8px; }
</style>
<style scoped>
.btn-sm { padding: 4px 8px; font-size: 12px; }
.action-buttons { display: inline-flex; gap: 6px; }
.btn-danger {
  background: #ef4444;
  color: #fff;
  border-color: #dc2626;
}
.btn-danger:hover { filter: brightness(0.95); }
</style>