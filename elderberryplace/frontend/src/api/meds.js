import api from './client'

export async function listMedications(q='') {
  const { data } = await api.get('/medications', { params: q ? { q } : {} })
  return data.data.items
}

export async function listRxByResident(residentId) {
  const { data } = await api.get(`/residents/${residentId}/prescriptions`)
  return data.data.items
}

export async function createRx(residentId, payload) {
  const { data } = await api.post(`/residents/${residentId}/prescriptions`, payload)
  return data.data
}

export async function listDueDoses(residentId, from, to) {
  const { data } = await api.get(`/residents/${residentId}/med-due`, { params: { from, to } })
  return data.data.items
}

export async function administer(scheduleId, body) {
  const { data } = await api.post(`/med-schedule/${scheduleId}/administer`, body)
  return data.data
}
