// Residents API client
// Usage:
// import Residents from '@/api/residents'
// Residents.list({ search: 'Alice' })
// Residents.get(1)
// Residents.create({
//   username: 'alice',
//   full_name: 'Alice Smith',
//   password: 'secret123', // required on create
//   email: 'alice@example.com',
//   room_number: '12A',
//   dob: '1990-01-01',
//   care_notes: 'Allergic to penicillin'
// })
// Residents.update(id, { full_name: 'Alice S.', room_number: '14B' })
// Residents.remove(id)
import api from './client'

// Fetch one resident by id with robust fallback and normalization for various backend shapes
async function detail(id) {
  const want = String(id)
  // 1) Preferred RESTful show
  try {
    const r = await api.get(`/residents/${id}`)
    const d = r?.data
    // Some backends (or SQL libs) return an array even for show; normalize
    if (Array.isArray(d)) {
      const hit = d.find(x => matchesId(x, want)) || d[0] || null
      return { data: hit }
    }
    return r
  } catch (e) {
    const status = e?.response?.status
    if (status !== 404 && status !== 405) throw e
  }

  // 2) Fallback: query collection (try both user_id and id)
  const tries = [
    { user_id: id, limit: 50 },
    { id,       limit: 50 }
  ]
  for (const params of tries) {
    try {
      const r = await api.get('/residents', { params })
      const d = r?.data
      const items = Array.isArray(d?.data?.items) ? d.data.items
        : Array.isArray(d?.items)               ? d.items
        : Array.isArray(d?.data)                ? d.data
        : Array.isArray(d?.residents)           ? d.residents
        : Array.isArray(d)                      ? d
        : []
      if (items.length) {
        const hit = items.find(x => matchesId(x, want)) || items[0]
        return { data: hit }
      }
    } catch (_) {}
  }

  // 3) Last resort: return empty
  return { data: null }
}

function matchesId(obj, want) {
  if (!obj) return false
  const user = obj.user || obj.users || {}
  const prof = obj.profile || obj.resident_profile || {}
  const candidates = [
    obj.id,
    obj.user_id,
    obj.resident_id,
    user.id,
    prof.user_id
  ].filter(v => v !== undefined && v !== null).map(v => String(v))
  return candidates.includes(want)
}

export default {
  list:   (params = {})        => api.get('/residents', { params }),
  get:    (id)                 => api.get(`/residents/${id}`),
  detail, // smart getter: tries /:id then falls back to ?user_id=
  create: (data)               => api.post('/residents', data),
  update: (id, data)           => api.put(`/residents/${id}`, data),
  remove: (id)                 => api.delete(`/residents/${id}`)
}