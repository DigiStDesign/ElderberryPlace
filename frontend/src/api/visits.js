// Visits API client
// Usage:
// import Visits from '@/api/visits'
// Visits.list({ search: 'Maria' })
// Visits.get(1)
// Visits.create({ resident_user_id: 1, visitor_user_id: 2, requested_start: '2025-10-03 10:00:00', requested_end: '2025-10-03 11:00:00' })
// Visits.update(id, payload)
// Visits.remove(id)
import api from './client'

const base = '/visit-requests'

export default {
  list:   (params = {})        => api.get(base, { params, withCredentials: true }),
  get:    (id)                 => api.get(`${base}/${id}`, { withCredentials: true }),
  create: (data)               => api.post(base, data, { withCredentials: true }),
  update: (id, data)           => api.put(`${base}/${id}`, data, { withCredentials: true }),
  remove: (id)                 => api.delete(`${base}/${id}`, { withCredentials: true })
}