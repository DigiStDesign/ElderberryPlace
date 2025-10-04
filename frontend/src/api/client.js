// Centralized Axios client for API
import axios from 'axios'

const api = axios.create({
  // Vite proxy: /api → http://127.0.0.1:8000
  baseURL: import.meta.env.VITE_API_BASE || '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  timeout: 20000
})

// Attach Authorization + CSRF on every request
api.interceptors.request.use((config) => {
  // Bearer token (if you use JWT)
  try {
    const token = localStorage.getItem('token')
    if (token) config.headers.Authorization = `Bearer ${token}`
  } catch {}

  // CSRF token from localStorage or window
  try {
    const csrf = (typeof window !== 'undefined' && (window.__CSRF || localStorage.getItem('csrf'))) || null
    if (csrf) {
      // Cover common header spellings used by PHP stacks
      config.headers['X-CSRF'] = csrf
      config.headers['X-CSRF-TOKEN'] = csrf
      config.headers['X-CSRF-Token'] = csrf
      config.headers['X-XSRF-TOKEN'] = csrf
    }
  } catch {}

  return config
})

// Optional: friendlier network error messages
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err?.message === 'Network Error' || err?.code === 'ERR_NETWORK') {
      err.message = 'Network error: check API server and CORS/proxy settings.'
    }
    return Promise.reject(err)
  }
)

export default api