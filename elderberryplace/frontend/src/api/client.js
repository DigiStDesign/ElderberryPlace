// This file configures Axios to communicate with the PHP backend API.
// It sets up the base URL, headers, and request interceptors to handle CSRF tokens.

import axios from 'axios'

const api = axios.create({
  // The baseURL must point to the PHP API entrypoint (index.php with r=v1).
  baseURL: 'http://127.0.0.1:8000/api/index.php?r=v1',
  // Content-Type is set to 'application/json' to indicate the request body format.
  // withCredentials is enabled to allow sending cookies and HTTP authentication information.
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true,
})

// This interceptor automatically attaches the CSRF token from localStorage or window.__CSRF to every request.
api.interceptors.request.use(cfg => {
  const csrf = localStorage.getItem('csrf') || window.__CSRF
  if (csrf) cfg.headers['X-CSRF-Token'] = csrf
  return cfg
})

// Exporting the configured api allows other modules to reuse this client instead of repeating setup.
export default api