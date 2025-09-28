<script setup>
import { ref, onMounted } from 'vue'
import dayjs from 'dayjs'
import { staffWorkload } from '../../api/reports'
const from = ref(dayjs().startOf('month').format('YYYY-MM-DD'))
const to   = ref(dayjs().format('YYYY-MM-DD'))
const rows = ref([])
async function load(){ const r = await staffWorkload({from:from.value,to:to.value}); rows.value = r.rows }
onMounted(load)
</script>

<template>
  <div>
    <h2>Staff Workload</h2>
    <div style="display:flex;gap:.5rem;align-items:center">
      <label>From</label><input type="date" v-model="from" />
      <label>To</label><input type="date" v-model="to" />
      <button @click="load">Run</button>
    </div>
    <table class="table">
      <thead><tr><th>Staff</th><th>Administrations</th></tr></thead>
      <tbody>
        <tr v-for="r in rows" :key="r.staff_user_id"><td>{{ r.staff_name }}</td><td>{{ r.administrations }}</td></tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:.5rem}th{background:#f7f7f7}
</style>
