// frontend/src/api/client.js
// Axios client for PHP API (index.php?r=v1) with CSRF attach + auto refresh.

import axios from 'axios'

const API_BASE =
  import.meta?.env?.VITE_API_BASE ||
  'http://127.0.0.1:8000/api/index.php?r=v1'

const api = axios.create({
  baseURL: API_BASE,
  headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  withCredentials: true,
})

// Attach CSRF from localStorage or window.__CSRF
api.interceptors.request.use(cfg => {
  const csrf = localStorage.getItem('csrf') || window.__CSRF
  if (csrf) cfg.headers['X-CSRF-Token'] = csrf
  return cfg
})

// Auto-refresh CSRF on 419, retry once. Optional 401 hook.
api.interceptors.response.use(
  (res) => res,
  async (err) => {
    const cfg = err?.config
    const status = err?.response?.status

    // If CSRF failed, fetch /csrf and retry once
    if (status === 419 && cfg && !cfg.__retried) {
      try {
        const { data } = await axios.get(API_BASE.replace(/\/?$/, '/csrf'), { withCredentials: true })
        const token = data?.data?.csrf
        if (token) {
          localStorage.setItem('csrf', token)
          window.__CSRF = token
          cfg.headers = cfg.headers || {}
          cfg.headers['X-CSRF-Token'] = token
          cfg.__retried = true
          return api(cfg)
        }
      } catch (_) {
        // fall through to throw original error
      }
    }

    // Optional: handle 401 (unauthenticated)
    if (status === 401) {
      // e.g., redirect to login or emit a global event
      // window.location.href = '/login'
    }

    throw err
  }
)

export default api
