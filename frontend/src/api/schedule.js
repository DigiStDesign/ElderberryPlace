

// Schedule API client
// Usage:
// import Schedule from '@/api/schedule'
// Schedule.list({ search: 'Alice' })
// Schedule.get(1)
// Schedule.create({ resident_id: 1, staff_id: 2, service_id: 3, start_at: '2025-10-03T10:00:00Z' })
// Schedule.update(id, payload)
// Schedule.remove(id)
import api from './client'

export default {
  list:   (params = {})        => api.get('/schedule', { params }),
  get:    (id)                 => api.get(`/schedule/${id}`),
  create: (data)               => api.post('/schedule', data),
  update: (id, data)           => api.put(`/schedule/${id}`, data),
  remove: (id)                 => api.delete(`/schedule/${id}`)
}