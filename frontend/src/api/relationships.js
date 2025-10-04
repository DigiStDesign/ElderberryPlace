

// Relationships API client
// Usage:
// import Relationships from '@/api/relationships'
// Relationships.list({ search: 'John' })
// Relationships.get(1)
// Relationships.create({ resident_id: 1, visitor_id: 2, relation_type: 'Daughter' })
// Relationships.update(id, payload)
// Relationships.remove(id)
import api from './client'

export default {
  list:   (params = {})        => api.get('/relationships', { params }),
  get:    (id)                 => api.get(`/relationships/${id}`),
  create: (data)               => api.post('/relationships', data),
  update: (id, data)           => api.put(`/relationships/${id}`, data),
  remove: (id)                 => api.delete(`/relationships/${id}`)
}