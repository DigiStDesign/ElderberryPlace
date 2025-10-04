// Staff API client
// Usage:
// import Staff from '@/api/staff'
// Staff.list({ search: 'Jane' })
// Staff.get(1)
// Staff.create({
//   // user fields
//   username: 'jdoe',
//   full_name: 'Jane Doe',
//   email: 'jane@example.com',
//   password: 'secret123', // required on create
//   // staff_profiles fields
//   staff_job_id: 2,
//   started_on: '2024-07-01',
//   notes: 'Senior carer'
// })
// Staff.update(id, { full_name: 'Jane D.', staff_job_id: 3 })
// Staff.remove(id)
import api from './client'

export default {
  list:   (params = {})        => api.get('/staff', { params }),
  get:    (id)                 => api.get(`/staff/${id}`),
  create: (data)               => api.post('/staff', data),
  update: (id, data)           => api.put(`/staff/${id}`, data),
  remove: (id)                 => api.delete(`/staff/${id}`),
  // Jobs catalogue for role select
  jobs:   ()                   => api.get('/staff/jobs')
}