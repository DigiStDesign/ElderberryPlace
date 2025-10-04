

// Categories API client
// Usage:
// import Categories from '@/api/categories'
// Categories.list({ search: 'Allied' })
// Categories.get(1)
// Categories.create({ name: 'Physio', description: '...' })
// Categories.update(id, payload)
// Categories.remove(id)
import api from './client'

export default {
  list:   (params = {})        => api.get('/categories', { params }),
  get:    (id)                 => api.get(`/categories/${id}`),
  create: (data)               => api.post('/categories', data),
  update: (id, data)           => api.put(`/categories/${id}`, data),
  remove: (id)                 => api.delete(`/categories/${id}`)
}