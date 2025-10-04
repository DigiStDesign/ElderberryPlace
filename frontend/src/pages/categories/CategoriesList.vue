<template>
  <section class="p-4">
    <header class="page-header">
      <h1 class="page-title">Categories</h1>
      <div class="header-actions">
        <button type="button" class="btn btn-sm" @click="refresh">Refresh</button>
        <router-link class="btn btn-sm btn-primary" to="/categories/new">New</router-link>
      </div>
    </header>

    <!-- Filters -->
    <div class="filters">
      <div class="filter">
        <label class="lbl">Search</label>
        <input v-model.trim="q" @keyup.enter="refresh" class="input" placeholder="Name…" />
      </div>
      <button class="btn btn-sm" @click="refresh">Search</button>
    </div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="rows.length === 0" style="padding:8px; color:#555;">No categories found.</div>

      <DataTable
        v-else
        :headers="headers"
        :rows="rows"
        rowKey="id"
      >
        <template #cell-actions="{ row }">
          <div class="action-buttons">
            <button
              type="button"
              class="btn btn-sm btn-primary"
              :disabled="!row.id"
              :title="row.id ? 'Edit category' : 'Missing id'"
              @click.prevent="editRow(row)"
            >
              Edit
            </button>
            <button
              type="button"
              class="btn btn-sm btn-danger"
              :disabled="!row.id"
              :title="row.id ? 'Delete category' : 'Missing id'"
              @click.prevent="deleteRow(row)"
            >
              Delete
            </button>
          </div>
        </template>
      </DataTable>
    </template>

    <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
  </section>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import Categories from '@/api/categories'

const router = useRouter()

const headers = [
  { key: 'name', label: 'Name' },
  { key: 'description', label: 'Description' },
  { key: 'actions', label: 'Actions' }
]

const rows = ref([])
const loading = ref(false)
const error = ref('')
const q = ref('')

async function refresh() {
  error.value = ''
  loading.value = true
  try {
    const resp = await Categories.list({ search: q.value || undefined })
    const d = resp?.data
    const items = Array.isArray(d) ? d
      : Array.isArray(d?.data) ? d.data
      : Array.isArray(d?.items) ? d.items
      : Array.isArray(d?.records) ? d.records
      : []
    rows.value = items.map((c) => {
      const id = c.id ?? c.category_id ?? c.ID ?? null
      return {
        id: id,
        name: c.name ?? c.category_name ?? '',
        description: c.description ?? c.details ?? ''
      }
    })
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load categories'
    rows.value = []
  } finally {
    loading.value = false
  }
}

function editRow(row) {
  if (!row?.id) {
    console.warn('[CategoriesList] Missing id for row:', row)
    alert('Cannot edit: missing category id from API.')
    return
  }
  const id = String(row.id)

  // 1) Prefer named routes if they exist
  try {
    if (router.hasRoute && router.hasRoute('CategoryEdit')) {
      router.push({ name: 'CategoryEdit', params: { id } })
      return
    }
    if (router.hasRoute && router.hasRoute('CategoriesEdit')) {
      router.push({ name: 'CategoriesEdit', params: { id } })
      return
    }
  } catch (_) { /* ignore and try fallbacks */ }

  // 2) Try common path patterns used in this project
  const candidates = [
    `/categories/${id}/edit`,
    `/category/${id}/edit`,
    { path: '/categories/edit', query: { id } },
    { path: '/category/edit',   query: { id } },
  ]

  for (const target of candidates) {
    try {
      router.push(target)
      return
    } catch (e) {
      console.warn('[CategoriesList] navigation attempt failed for', target, e)
    }
  }

  alert('Could not navigate to the edit page. Check your router for a CategoryEdit route or one of: /categories/:id/edit, /categories/edit?id=, /category/:id/edit, /category/edit?id=')
}

async function deleteRow(row) {
  if (!confirm('Delete this category?')) return
  error.value = ''
  loading.value = true
  try {
    await Categories.remove(row.id)
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
.page-header {
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:12px;
}
.page-title {
  font-size:20px;
  font-weight:600;
}
.header-actions { display:flex; gap:8px; }

.filters {
  display:flex;
  align-items:flex-end;
  gap:8px;
  margin-bottom:12px;
}
.filter { display:flex; flex-direction:column; gap:4px; }
.lbl { font-size:12px; color:#555; }
.input {
  padding:6px 8px;
  border:1px solid #e5e7eb;
  border-radius:4px;
  min-width:220px;
}

/* Buttons — mirror Residents page look */
.btn {
  padding:6px 10px;
  border:1px solid #e5e7eb;
  border-radius:4px;
  background:#f9fafb;
  cursor:pointer;
  line-height:1.1;
  user-select:none;
}
.btn:hover { background:#f3f4f6; }
.btn-sm {
  padding:4px 8px;
  font-size:12px;
  border-radius:4px;
}
.btn-primary {
  background:#2563eb;
  color:#fff;
  border-color:#1d4ed8;
}
.btn-primary:hover { filter:brightness(0.95); }
.btn-danger { background:#ef4444; color:#fff; border-color:#dc2626; }
.btn-danger:hover { filter:brightness(0.95); }

.action-buttons { display:inline-flex; gap:6px; }

/* Ensure slot buttons in DataTable aren't overridden by table styles */
:deep(.data-table) .btn,
:deep(.data-table) .btn-sm,
:deep(.data-table) .btn-primary,
:deep(.data-table) .btn-danger {
  all: unset;
  display:inline-block;
  padding:4px 8px;
  font-size:12px;
  border-radius:4px;
  border:1px solid #e5e7eb;
  background:#f9fafb;
  color:#111827;
  line-height:1.1;
  cursor:pointer;
}
:deep(.data-table) .btn-primary { background:#2563eb; color:#fff; border-color:#1d4ed8; }
:deep(.data-table) .btn-danger { background:#ef4444; color:#fff; border-color:#dc2626; }
</style>

.btn[disabled], .btn:disabled { opacity: 0.5; cursor: not-allowed; }
