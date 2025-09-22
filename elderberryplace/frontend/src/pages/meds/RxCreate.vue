<script setup>
import { ref, computed } from 'vue'
import dayjs from 'dayjs'
import { listMedications, createRx } from '../../api/meds'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const residentId = Number(route.params.id)

const meds = ref([])
const q = ref('')
const loading = ref(false)
const form = ref({
  medication_id: null,
  dose: '',
  route: 'PO',
  prn: false,
  frequency: 'BID',
  start_date: dayjs().format('YYYY-MM-DD'),
  end_date: '',
  times: ['08:00','20:00'],
  max_daily_dose: '',
  instructions: '',
  prescriber: ''
})

async function searchMeds() {
  meds.value = await listMedications(q.value.trim())
}

function addTime() { form.value.times.push('12:00') }
function removeTime(i) { form.value.times.splice(i,1) }

const canSubmit = computed(() =>
  form.value.medication_id && form.value.dose && form.value.frequency && form.value.start_date
)

async function submit() {
  try {
    loading.value = true
    const payload = { ...form.value }
    if (payload.prn) payload.times = []         // PRN = no fixed times
    await createRx(residentId, payload)
    alert('Prescription created.')
    router.push({ name: 'mar', params: { id: residentId } })
  } catch (e) {
    console.error(e)
    alert('Failed to create prescription')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="rx-create">
    <h2>Create Prescription</h2>

    <label>Search medication</label>
    <div style="display:flex; gap:.5rem; align-items:center">
      <input v-model="q" @input="searchMeds" placeholder="Type generic/brand..." />
      <select v-model.number="form.medication_id">
        <option disabled value="">Select...</option>
        <option v-for="m in meds" :key="m.id" :value="m.id">
          {{ m.generic_name }} {{ m.strength }} ({{ m.form }}{{ m.brand_name ? ', '+m.brand_name : '' }})
        </option>
      </select>
    </div>

    <div class="grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:1rem">
      <div>
        <label>Dose</label>
        <input v-model="form.dose" placeholder="e.g., 1 tablet" />
      </div>
      <div>
        <label>Route</label>
        <select v-model="form.route">
          <option>PO</option><option>SL</option><option>IM</option><option>SC</option><option>IV</option>
        </select>
      </div>

      <div>
        <label>Frequency</label>
        <select v-model="form.frequency" :disabled="form.prn">
          <option>OD</option><option>BID</option><option>TID</option><option>QID</option><option>Custom</option>
        </select>
      </div>
      <div>
        <label><input type="checkbox" v-model="form.prn" /> PRN (as needed)</label>
      </div>

      <div>
        <label>Start date</label>
        <input type="date" v-model="form.start_date" />
      </div>
      <div>
        <label>End date</label>
        <input type="date" v-model="form.end_date" />
      </div>

      <div v-if="!form.prn" style="grid-column:1/-1">
        <label>Times (24h)</label>
        <div v-for="(t,i) in form.times" :key="i" style="display:flex;gap:.5rem;align-items:center;margin:.25rem 0">
          <input type="time" v-model="form.times[i]" />
          <button type="button" @click="removeTime(i)">Remove</button>
        </div>
        <button type="button" @click="addTime">+ Add time</button>
      </div>

      <div style="grid-column:1/-1">
        <label>Max daily dose (PRN)</label>
        <input v-model="form.max_daily_dose" placeholder="e.g., Max 4 tablets/day" />
      </div>

      <div style="grid-column:1/-1">
        <label>Instructions</label>
        <textarea v-model="form.instructions" rows="2" placeholder="e.g., with food"></textarea>
      </div>

      <div>
        <label>Prescriber</label>
        <input v-model="form.prescriber" placeholder="Dr Smith" />
      </div>
    </div>

    <div style="margin-top:1rem;display:flex;gap:.5rem">
      <button :disabled="!canSubmit || loading" @click="submit">Save prescription</button>
      <router-link :to="{ name:'mar', params:{ id: residentId }}">Cancel</router-link>
    </div>
  </div>
</template>

<style scoped>
.rx-create input, .rx-create select, .rx-create textarea { width:100%; padding:.5rem }
.rx-create label { display:block; font-weight:600; margin:.25rem 0 }
button { padding:.4rem .7rem; cursor:pointer }
</style>
