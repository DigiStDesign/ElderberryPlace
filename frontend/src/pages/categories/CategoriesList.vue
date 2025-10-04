<template>
  <section class="p-4">
    <header class="flex items-center justify-between mb-3">
      <h1 class="text-xl font-semibold">Categories</h1>
      <router-link class="btn btn-primary" to="/categories/new">New Category</router-link>
    </header>

    <!-- Filters -->
    <div class="filters">
      <div class="filter">
        <label class="lbl">Search</label>
        <input v-model.trim="q" @keyup.enter="refresh" class="input" placeholder="Name…" />
      </div>
      <button class="btn" @click="refresh">Search</button>
    </div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="rows.length === 0" style="padding:8px; color:#555;">No categories found.</div>

      <DataTable
        v-else
        :headers="headers"
        :rows="rows"
        @edit="editRow"
        @delete="deleteRow"
      />
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
    const payload = resp?.data
    rows.value = Array.isArray(payload) ? payload : (payload?.data ?? [])
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load categories'
    rows.value = []
  } finally {
    loading.value = false
  }
}

function editRow(row) {
  router.push(`/categories/${row.id}/edit`)
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
.btn {
  padding:6px 10px;
  border:1px solid #e5e7eb;
  border-radius:4px;
  background:#f9fafb;
  cursor:pointer;
}
.btn-primary {
  background:#2563eb;
  color:white;
  border-color:#1d4ed8;
}
</style>