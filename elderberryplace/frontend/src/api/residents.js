// This module wraps API calls to the backend /residents endpoints using the configured Axios client.

import api from './client'

// Fetches all residents from the backend.
// Accepts optional query parameters to filter or paginate results.
// Normalizes the response to always return an array of residents.
export async function list(params = {}) {
  const { data } = await api.get('/residents', { params })
  return Array.isArray(data) ? data : (data.items || [])
}

// Fetches a single resident by their unique ID.
export async function get(id) {
  const { data } = await api.get(`/residents/${id}`)
  return data
}

// Sends a POST request to create a new resident with the provided data payload.
export async function create(payload) {
  const { data } = await api.post('/residents', payload)
  return data
}

// Sends a PUT request to update an existing resident identified by ID with the new data payload.
export async function update(id, payload) {
  const { data } = await api.put(`/residents/${id}`, payload)
  return data
}

// Sends a DELETE request to remove a resident by their unique ID.
export async function remove(id) {
  const { data } = await api.delete(`/residents/${id}`)
  return data
}