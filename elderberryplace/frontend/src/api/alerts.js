import api from './client'

export async function listAlerts(params = {}) {
  const { data } = await api.get('/med-alerts', { params })
  return data.data.items
}
export async function resolveAlert(id, resolved_by) {
  const { data } = await api.patch(`/med-alerts/${id}`, { state: 'resolved', resolved_by })
  return data.data
}
