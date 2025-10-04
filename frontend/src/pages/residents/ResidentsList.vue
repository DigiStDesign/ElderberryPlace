<template>
  <section>
    <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <h2>Residents</h2>
      <div>
        <button @click="refresh">Refresh</button>
        <button @click="createNew">New</button>
      </div>
    </header>

    <div class="filters">
      <div class="filter">
        <label class="lbl">Search</label>
        <input v-model.trim="q" @keyup.enter="refresh" class="input" placeholder="Name, username, room…" />
      </div>
      <button class="btn" @click="refresh">Search</button>
    </div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="rows.length === 0" style="padding:8px; color:#555;">No residents found.</div>

      <DataTable
        v-else
        :headers="headers"
        :rows="rows"
        rowKey="id"
      >
        <template #cell-actions="{ row }">
          <div class="action-buttons">
            <button class="btn btn-sm" @click.prevent="editRow(row)">Edit</button>
            <button class="btn btn-sm btn-danger" @click.prevent="deleteRow(row)">Delete</button>
          </div>
        </template>
      </DataTable>
    </template>

    <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
    <details v-if="errorDetail" style="margin-top:6px;">
      <summary>Details</summary>
      <pre style="white-space:pre-wrap; font-size:12px; background:#f8fafc; padding:8px; border:1px solid #e5e7eb; border-radius:6px;">{{ errorDetail }}</pre>
    </details>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from '../../components/DataTable.vue'
import Residents from '@/api/residents'

const router = useRouter()
const rows = ref([])
const error = ref('')
const errorDetail = ref('')
const loading = ref(false)
const q = ref('')
const headers = [
  { key: 'id', label: 'ID' },
  { key: 'username', label: 'Username' },
  { key: 'full_name', label: 'Full Name' },
  { key: 'email', label: 'Email' },
  { key: 'room_number', label: 'Room' },
  { key: 'dob', label: 'DOB' },
  { key: 'care_notes', label: 'Care notes' },
  { key: 'actions', label: 'Actions' }
]

async function refresh() {
  error.value = ''
  loading.value = true
  try {
    const resp = await Residents.list({
      search: q.value || undefined
    })
    const payload = resp?.data
    console.log('[ResidentsList] resp.data =', payload)
    // Your API shape can be: { ok, data: { items: [...] } }
    let items = []
    if (Array.isArray(payload?.data?.items)) {
      items = payload.data.items
    } else if (Array.isArray(payload?.items)) {
      items = payload.items
    } else if (Array.isArray(payload?.data)) {
      items = payload.data
    } else if (Array.isArray(payload)) {
      items = payload
    } else if (Array.isArray(payload?.residents)) {
      items = payload.residents
    } else {
      console.warn('[ResidentsList] Unexpected response shape:', payload)
      items = []
    }
    rows.value = items.map((r, i) => {
      const user    = r.user || r.users || {}
      const profile = r.profile || r.resident_profile || {}

      const id = r.id ?? r.user_id ?? r.resident_id ?? user.id ?? profile.user_id ?? i + 1

      const username   = r.username ?? user.username ?? ''
      const full_name  = r.full_name ?? user.full_name ?? [r.first_name, r.last_name].filter(Boolean).join(' ')
      const email      = r.email ?? user.email ?? ''
      const room_no    = r.room_number ?? r.room_no ?? profile.room_number ?? ''
      const dob        = r.dob ?? profile.dob ?? ''
      const care_notes = r.care_notes ?? profile.care_notes ?? ''

      return {
        // id used only for table rowKey/display; may be fallback
        id,
        // edit_id is the authoritative identifier to open edit page
        edit_id: r.user_id ?? r.id ?? user.id ?? profile.user_id ?? null,
        user_id: r.user_id ?? profile.user_id ?? id,
        username,
        full_name,
        email,
        room_number: room_no,
        dob,
        care_notes
      }
    })
  } catch (e) {
    console.error('[ResidentsList] load error:', e?.response?.data || e)
    const resp = e?.response?.data
    error.value =
      resp?.message ||
      resp?.error?.message ||
      (typeof resp === 'string' ? resp : '') ||
      e?.message ||
      'Failed to load residents'
    try {
      errorDetail.value = JSON.stringify(resp ?? {}, null, 2)
    } catch (_) {
      errorDetail.value = String(resp ?? '')
    }
    rows.value = []
  } finally {
    loading.value = false
  }
}

function createNew() {
  router.push('/residents/new')
}
function editRow(row) {
  const id = row.edit_id ?? row.user_id ?? row.id
  if (!id || String(id).trim() === '') {
    alert('Sorry, this resident record has no editable id.')
    return
  }
  // Prefer the named route if it exists
  try {
    router.push({ name: 'residents.edit', params: { id } })
  } catch (e) {
    // Fallback to path-based in case route name differs
    router.push(`/residents/${id}/edit`)
  }
}
async function deleteRow(row) {
  if (!confirm('Delete this resident?')) return
  error.value = ''
  loading.value = true
  try {
    const id = row.user_id ?? row.id
    await Residents.remove(id)
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
  .action-buttons { display: inline-flex; gap: 6px; }
  .btn-sm { padding: 4px 8px; font-size: 12px; }
  .btn-danger {
    background:#ef4444;
    color:#fff;
    border-color:#dc2626;
  }
  .btn-danger:hover { filter: brightness(0.95); }
</style>