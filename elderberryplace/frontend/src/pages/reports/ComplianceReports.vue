<script setup>
import { ref, computed, onMounted } from 'vue'
import { complianceSummary } from '../../api/reports'

// Defaults: this month to today (YYYY-MM-DD)
const today = new Date()
const y = today.getFullYear(), m = today.getMonth()
const defaultFrom = new Date(y, m, 1).toISOString().slice(0, 10)
const defaultTo   = today.toISOString().slice(0, 10)

const from = ref(defaultFrom)
const to   = ref(defaultTo)
const residentId = ref('') // optional filter if you enable it in API later

const rows = ref([])   // [{type, severity, cnt}]
const loading = ref(false)
const err = ref('')

async function load() {
  loading.value = true; err.value = ''
  try {
    const params = { from: from.value, to: to.value }
    // If you want resident filter, also pass resident_id when not empty:
    if (residentId.value) params.resident_id = residentId.value
    const res = await complianceSummary(params)
    rows.value = res.rows || []
  } catch (e) {
    err.value = 'Failed to load report'
  } finally {
    loading.value = false
  }
}
onMounted(load)

// Pivot rows -> type x severity table
const pivot = computed(() => {
  const table = {} // type -> { low, moderate, high, critical, total }
  for (const r of rows.value) {
    const t = r.type
    if (!table[t]) table[t] = { low:0, moderate:0, high:0, critical:0, total:0 }
    table[t][r.severity] = (table[t][r.severity] || 0) + Number(r.cnt || 0)
  }
  for (const t in table) {
    const o = table[t]
    o.total = (o.low||0)+(o.moderate||0)+(o.high||0)+(o.critical||0)
  }
  return table
})

const grand = computed(() => {
  const g = { low:0, moderate:0, high:0, critical:0, total:0 }
  for (const t in pivot.value) {
    const o = pivot.value[t]
    g.low += o.low; g.moderate += o.moderate; g.high += o.high; g.critical += o.critical; g.total += o.total
  }
  return g
})

// Optional CSV export (client-side)
function exportCsv() {
  const headers = ['Type','Low','Moderate','High','Critical','Total']
  const lines = [headers.join(',')]
  for (const t in pivot.value) {
    const o = pivot.value[t]
    lines.push([t, o.low, o.moderate, o.high, o.critical, o.total].join(','))
  }
  lines.push(['TOTAL', grand.value.low, grand.value.moderate, grand.value.high, grand.value.critical, grand.value.total].join(','))
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = `compliance-summary_${from.value}_to_${to.value}.csv`
  a.click(); URL.revokeObjectURL(url)
}

function sevClass(n) {
  return n > 0 ? 'badge on' : 'badge'
}
</script>

<template>
  <div class="page">
    <h2>Compliance Summary</h2>

    <div class="filters">
      <label>From</label><input type="date" v-model="from" />
      <label>To</label><input type="date" v-model="to" />
      <!-- Uncomment if you want resident filter UI:
      <label>Resident ID</label><input type="number" v-model="residentId" min="1" />
      -->
      <button @click="load" :disabled="loading">{{ loading ? 'Loading…' : 'Run' }}</button>
      <button @click="exportCsv" :disabled="loading || !rows.length">Export CSV</button>
    </div>

    <p v-if="err" class="err">{{ err }}</p>

    <table v-if="Object.keys(pivot).length" class="table">
      <thead>
        <tr>
          <th>Type</th>
          <th>Low</th>
          <th>Moderate</th>
          <th>High</th>
          <th>Critical</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(o, type) in pivot" :key="type">
          <td>{{ type }}</td>
          <td><span :class="sevClass(o.low)">{{ o.low }}</span></td>
          <td><span :class="sevClass(o.moderate)">{{ o.moderate }}</span></td>
          <td><span :class="sevClass(o.high)">{{ o.high }}</span></td>
          <td><span :class="sevClass(o.critical)">{{ o.critical }}</span></td>
          <td><b>{{ o.total }}</b></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th>Totals</th>
          <th>{{ grand.low }}</th>
          <th>{{ grand.moderate }}</th>
          <th>{{ grand.high }}</th>
          <th>{{ grand.critical }}</th>
          <th>{{ grand.total }}</th>
        </tr>
      </tfoot>
    </table>

    <p v-else class="muted">No incidents for the selected range.</p>
  </div>
</template>

<style scoped>
.page { max-width: 900px; margin: 0 auto; }
.filters { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin: .5rem 0 1rem; }
.table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #e5e5e5; padding: .5rem; text-align: center; }
th { background: #f7f7f7; }
.err { color: #b00020; margin:.5rem 0; }
.muted { color: #666; margin-top: .5rem; }
.badge { display:inline-block; min-width:2.25rem; padding:.15rem .4rem; border-radius: 12px; background:#f1f1f1; }
.badge.on { background:#e8f5e9; }
</style>
