<script setup>
import { ref, onMounted } from 'vue'
import dayjs from 'dayjs'
import { listAlerts, resolveAlert } from '../../api/alerts'

const items = ref([]); const loading = ref(false); const state = ref('open')

async function load() {
  loading.value = true
  try { items.value = await listAlerts({ state: state.value }) }
  finally { loading.value = false }
}
async function resolveRow(row) {
  const me = JSON.parse(localStorage.getItem('me') || '{}')
  await resolveAlert(row.id, me.id)
  await load()
}
onMounted(load)
</script>

<template>
  <div>
    <h2>Medication Alerts</h2>
    <div style="margin:.5rem 0;">
      <select v-model="state" @change="load">
        <option value="open">Open</option>
        <option value="resolved">Resolved</option>
      </select>
      <button @click="load">Refresh</button>
    </div>

    <table class="table">
      <thead><tr><th>Created</th><th>Resident</th><th>Due</th><th>Type</th><th>Message</th><th>Action</th></tr></thead>
      <tbody>
        <tr v-for="r in items" :key="r.id">
          <td>{{ dayjs(r.created_at).format('YYYY-MM-DD HH:mm') }}</td>
          <td>{{ r.resident_name || r.resident_user_id }}</td>
          <td>{{ r.due_at ? dayjs(r.due_at).format('YYYY-MM-DD HH:mm') : '-' }}</td>
          <td>{{ r.type }}</td>
          <td>{{ r.message }}</td>
          <td>
            <button v-if="r.state==='open'" @click="resolveRow(r)">Resolve</button>
            <span v-else>—</span>
          </td>
        </tr>
        <tr v-if="!items.length && !loading">
          <td colspan="6" style="text-align:center;color:#666">No alerts</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table { width:100%; border-collapse: collapse }
th, td { border:1px solid #ddd; padding:.5rem }
th { background:#f7f7f7 }
</style>
