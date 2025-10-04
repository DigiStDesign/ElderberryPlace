// Visitors API client (hardened)
// Usage is unchanged
import api from './client'

function toTinyInt(v) {
  if (typeof v === 'boolean') return v ? 1 : 0
  if (v === '1' || v === 1) return 1
  if (v === '0' || v === 0) return 0
  return v ? 1 : 0
}

function toIsActive(v) {
  if (v === 'active') return 1
  if (v === 'inactive') return 0
  return toTinyInt(v)
}

function normalizePayload(input = {}) {
  const data = { ...input }

  // Derive full_name if not provided but first/last exist
  if (!data.full_name) {
    const fn = (data.first_name || '').trim()
    const ln = (data.last_name || '').trim()
    const full = [fn, ln].filter(Boolean).join(' ').trim()
    if (full) data.full_name = full
  }

  // Coerce booleans/strings to tinyint where backend expects it
  if (typeof data.verified !== 'undefined') {
    data.verified = toTinyInt(data.verified)
  }
  if (typeof data.is_active !== 'undefined') {
    data.is_active = toIsActive(data.is_active)
  }
  if (typeof data.status !== 'undefined' && typeof data.is_active === 'undefined') {
    // allow `status: 'active'|'inactive'` coming from forms
    data.is_active = toIsActive(data.status)
  }

  // Drop empty/undefined fields the backend may reject
  const cleaned = {}
  for (const [k, v] of Object.entries(data)) {
    if (v === undefined) continue
    if (v === '') continue
    // don't send blank password
    if (k === 'password' && !v) continue
    cleaned[k] = v
  }
  return cleaned
}

const base = '/visitors'

function list(params = {}) {
  return api.get(base, { params })
}

function get(id) {
  return api.get(`${base}/${id}`)
}

function create(payload) {
  return api.post(base, normalizePayload(payload))
}

function update(id, payload) {
  return api.put(`${base}/${id}`, normalizePayload(payload))
}

function remove(id) {
  return api.delete(`${base}/${id}`)
}

export default { list, get, create, update, remove }