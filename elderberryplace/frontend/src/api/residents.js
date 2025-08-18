import api from './client'

export async function list(params = {}) {
  const { data } = await api.get('/residents', { params })
  return Array.isArray(data) ? data : (data.items || [])
}

export async function get(id) {
  const { data } = await api.get(`/residents/${id}`)
  return data
}

export async function create(payload) {
  const { data } = await api.post('/residents', payload)
  return data
}

export async function update(id, payload) {
  const { data } = await api.put(`/residents/${id}`, payload)
  return data
}

export async function remove(id) {
  const { data } = await api.delete(`/residents/${id}`)
  return data
}