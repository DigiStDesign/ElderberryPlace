<script setup>
import { ref, onMounted } from 'vue'
import dayjs from 'dayjs'
import { medAdherence, medMissedByShift } from '../../api/reports'

const from = ref(dayjs().startOf('month').format('YYYY-MM-DD'))
const to   = ref(dayjs().format('YYYY-MM-DD'))
const summary = ref(null)
const rows = ref([])

async function load() {
  summary.value = await medAdherence({ from: from.value, to: to.value })
  rows.value = await medMissedByShift({ from: from.value, to: to.value })
}
function toCSV() {
  const headers = ['day','shift','missed','scheduled']
  const lines = [headers.join(',')]
  rows.value.forEach(r => lines.push([r.day, r.shift, r.missed, r.scheduled].join(',')))
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = `med-missed-by-shift_${from.value}_to_${to.value}.csv`
  a.click(); URL.revokeObjectURL(url)
}
onMounted(load)
</script>

<template>
  <div>
    <h2>Medication Reporting</h2>
    <div style="display:flex;gap:.5rem;align-items:center;margin:.5rem 0;">
      <label>From</label><input type="date" v-model="from" />
      <label>To</label><input type="date" v-model="to" />
      <button @click="load">Run</button>
      <button @click="toCSV" :disabled="!rows.length">Export CSV</button>
    </div>

    <div v-if="summary" class="cards" style="display:flex;gap:1rem;flex-wrap:wrap;margin:.5rem 0;">
      <div class="card"><b>Scheduled</b><div>{{ summary.scheduled }}</div></div>
      <div class="card"><b>Given</b><div>{{ summary.given }}</div></div>
      <div class="card"><b>Missed</b><div>{{ summary.missed }}</div></div>
      <div class="card"><b>Refused</b><div>{{ summary.refused }}</div></div>
      <div class="card"><b>Withheld</b><div>{{ summary.withheld }}</div></div>
      <div class="card"><b>Adherence</b><div>{{ (summary.adherence*100).toFixed(1) }}%</div></div>
    </div>

    <table class="table">
      <thead><tr><th>Day</th><th>Shift</th><th>Missed</th><th>Scheduled</th></tr></thead>
      <tbody>
        <tr v-for="r in rows" :key="r.day + r.shift">
          <td>{{ r.day }}</td>
          <td>{{ r.shift }}</td>
          <td>{{ r.missed }}</td>
          <td>{{ r.scheduled }}</td>
        </tr>
        <tr v-if="!rows.length"><td colspan="4" style="text-align:center;color:#666">No data</td></tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table { width:100%; border-collapse:collapse }
th, td { border:1px solid #ddd; padding:.5rem }
th { background:#f7f7f7 }
.card { padding:.75rem 1rem; border:1px solid #eee; border-radius:.5rem; min-width:120px; text-align:center; background:#fafafa }
</style>
