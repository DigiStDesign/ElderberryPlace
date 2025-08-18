import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true 
})

api.interceptors.request.use((config) => {
  const m = (config.method || 'get').toUpperCase()
  if (m !== 'GET' && m !== 'HEAD' && m !== 'OPTIONS') {
    const csrf = window.__CSRF || ''
    if (csrf) config.headers['X-CSRF-Token'] = csrf
  }
  return config
})

export default api