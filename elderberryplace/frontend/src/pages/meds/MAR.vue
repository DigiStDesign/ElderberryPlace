<script setup>
import { ref, onMounted, watch } from 'vue'
import dayjs from 'dayjs'
import { listDueDoses, administer } from '../../api/meds'
import { useRoute } from 'vue-router'

const route = useRoute()
const residentId = Number(route.params.id)

const date = ref(dayjs().format('YYYY-MM-DD'))
const items = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const from = dayjs(date.value + ' 00:00').format('YYYY-MM-DD HH:mm')
    const to   = dayjs(date.value + ' 23:59').format('YYYY-MM-DD HH:mm')
    items.value = await listDueDoses(residentId, from, to)
  } finally {
    loading.value = false
  }
}

async function record(it, outcome) {
  try {
    await administer(it.schedule_id, {
      outcome,
      dose_given: it.dose,
      // TODO: replace with real logged-in staff id
      staff_user_id: 2
    })
    await load()
  } catch (e) {
    console.error(e)
    alert('Failed to record administration')
  }
}

function fmtTime(ts) {
  return dayjs(ts).format('HH:mm')
}

onMounted(load)
watch(date, load)
</script>

<template>
  <div class="mar">
    <h2>Medication Administration Record</h2>

    <div style="display:flex;gap:.75rem;align-items:center;margin:.5rem 0">
      <label for="day">Date</label>
      <input id="day" type="date" v-model="date" />
      <button @click="load">Refresh</button>
      <span v-if="loading">Loading…</span>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Medication</th>
          <th>Dose (Route)</th>
          <th>Status</th>
          <th style="min-width:260px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="it in items" :key="it.schedule_id">
          <td>{{ fmtTime(it.due_at) }}</td>
          <td>{{ it.generic_name }} {{ it.strength }}</td>
          <td>{{ it.dose }} ({{ it.route || 'PO' }})</td>
          <td>{{ it.outcome ? it.outcome : 'Due' }}</td>
          <td>
            <button :disabled="!!it.outcome" @click="record(it,'given')">Given</button>
            <button :disabled="!!it.outcome" @click="record(it,'refused')">Refused</button>
            <button :disabled="!!it.outcome" @click="record(it,'missed')">Missed</button>
            <button :disabled="!!it.outcome" @click="record(it,'withheld')">Withheld</button>
          </td>
        </tr>
        <tr v-if="!items.length && !loading">
          <td colspan="5" style="text-align:center;color:#666">No doses scheduled for this date.</td>
        </tr>
      </tbody>
    </table>

    <div style="margin-top:1rem">
      <router-link :to="{ name:'rx-new', params:{ id: residentId }}">+ New prescription</router-link>
    </div>
  </div>
</template>

<style scoped>
.table { width:100%; border-collapse:collapse }
th, td { border:1px solid #ddd; padding:.5rem; text-align:left }
th { background:#f7f7f7 }
button { margin-right:.25rem; padding:.35rem .6rem; cursor:pointer }
</style>
