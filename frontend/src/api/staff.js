// Staff API client
// Usage:
// import Staff from '@/api/staff'
// Staff.list({ search: 'Jane' })
// Staff.detail(1) // smart getter: tries /staff/:id then falls back to ?user_id=/id=
// Staff.create({...})
// Staff.update(id, {...})
// Staff.remove(id)
import api from './client'

// Smart detail fetcher that works with both /staff/:id and collection filters
async function detail(id) {
  const want = String(id)

  // 1) Preferred: RESTful show
  try {
    const r = await api.get(`/staff/${id}`)
    const d = r?.data
    // Some backends may (incorrectly) return an array for show; normalize
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
      const r = await api.get('/staff', { params })
      const d = r?.data
      const items = Array.isArray(d?.data?.items) ? d.data.items
        : Array.isArray(d?.items)               ? d.items
        : Array.isArray(d?.data)                ? d.data
        : Array.isArray(d?.staff)               ? d.staff
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
  const prof = obj.profile || obj.staff_profile || {}
  const candidates = [
    obj.id,
    obj.user_id,
    obj.staff_id,
    user.id,
    prof.user_id
  ].filter(v => v !== undefined && v !== null).map(v => String(v))
  return candidates.includes(want)
}

export default {
  list:   (params = {})        => api.get('/staff', { params }),
  get:    (id)                 => api.get(`/staff/${id}`),
  detail, // NEW smart getter
  create: (data)               => api.post('/staff', data),
  update: (id, data)           => api.put(`/staff/${id}`, data),
  remove: (id)                 => api.delete(`/staff/${id}`),
  // Jobs catalogue for role select
  jobs:   ()                   => api.get('/staff/jobs')
}