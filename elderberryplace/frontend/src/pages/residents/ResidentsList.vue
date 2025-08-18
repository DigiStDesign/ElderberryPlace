<template>
  <section>
    <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <h2>Residents</h2>
      <div>
        <button @click="refresh">Refresh</button>
        <button @click="createNew">New</button>
      </div>
    </header>

    <DataTable
      :headers="headers"
      :rows="rows"
      @edit="editRow"
      @delete="deleteRow"
    />

    <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from '../../components/DataTable.vue'
import * as Residents from '../../api/residents'

const router = useRouter()
const rows = ref([])
const error = ref('')
const headers = [
  { key: 'id', label: 'ID' },
  { key: 'first_name', label: 'First Name' },
  { key: 'last_name', label: 'Last Name' },
  { key: 'room', label: 'Room' },
]

async function refresh() {
  error.value = ''
  try {
    rows.value = await Residents.list()
  } catch (e) {
    error.value = 'Failed to load residents'
  }
}

function createNew() {
  router.push('/residents/new')
}
function editRow(row) {
  router.push(`/residents/${row.id}`)
}
async function deleteRow(row) {
  if (!confirm('Delete this resident?')) return
  error.value = ''
  try {
    await Residents.remove(row.id)
    await refresh()
  } catch (e) {
    error.value = 'Delete failed'
  }
}

onMounted(refresh)
</script>