import api from './client'

export async function fetchCsrf() {
  const res = await api.get('/csrf')
  return res.data?.data?.csrf || ''
}

export async function me() {
  const res = await api.get('/me')
  return res.data?.data?.user || null
}

export async function login({ username, password }) {
  const res = await api.post('/auth/login', { username, password })
  const user = res.data?.data?.user || null
  const csrf = res.data?.data?.csrf || ''
  return { user, csrf }
}

export async function logout() {
  try { await api.post('/auth/logout', {}) } catch (_) {}
}