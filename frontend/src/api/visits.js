

// Visits API client
// Usage:
// import Visits from '@/api/visits'
// Visits.list({ search: 'Maria' })
// Visits.get(1)
// Visits.create({ resident_id: 1, visitor_id: 2, check_in_at: '2025-10-03T10:00:00Z' })
// Visits.update(id, payload)
// Visits.remove(id)
import api from './client'

export default {
  list:   (params = {})        => api.get('/visits', { params }),
  get:    (id)                 => api.get(`/visits/${id}`),
  create: (data)               => api.post('/visits', data),
  update: (id, data)           => api.put(`/visits/${id}`, data),
  remove: (id)                 => api.delete(`/visits/${id}`)
}