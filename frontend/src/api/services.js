
// Services API client (hardened)
// Usage stays the same:
// import Services from '@/api/services'
// Services.list({ search: 'Physio' })
// Services.get(1)
// Services.create({ name: 'Physio', category_id: 1, rate: 79.95, is_active: 1 })
// Services.update(id, payload)
// Services.remove(id)
import api from './client'

function toTinyInt(v) {
  if (typeof v === 'boolean') return v ? 1 : 0
  if (v === '1' || v === 1) return 1
  if (v === '0' || v === 0) return 0
  return v ? 1 : 0
}

function toNumber(v) {
  if (v === '' || v == null) return undefined
  const n = Number(v)
  return Number.isFinite(n) ? n : undefined
}

function normalizePayload(input = {}) {
  const out = {}
  for (const [k, raw] of Object.entries(input)) {
    let v = raw
    // Trim strings
    if (typeof v === 'string') v = v.trim()

    // Coerce known fields
    if (k === 'is_active') v = toTinyInt(v)
    if (k === 'rate' || k === 'price' || k === 'cost' || k === 'fee') v = toNumber(v)
    if (k === 'category_id') v = toNumber(v)

    // Skip empties/undefined
    if (v === '' || typeof v === 'undefined') continue

    out[k] = v
  }
  return out
}

const base = '/services'

function list(params = {}) {
  return api.get(base, { params })
}

function get(id) {
  return api.get(`${base}/${id}`)
}

function create(data) {
  return api.post(base, normalizePayload(data))
}

function update(id, data) {
  return api.put(`${base}/${id}`, normalizePayload(data))
}

function remove(id) {
  return api.delete(`${base}/${id}`)
}

export default { list, get, create, update, remove }