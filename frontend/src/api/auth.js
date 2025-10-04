import api from './client'

// --- tiny utils -----------------------------------------------------------

// 'pick' safely extracts a nested property from an object using a dot-separated path.
// If the path doesn't exist, it returns a fallback value (default is undefined).
// This helps avoid runtime errors when accessing deep properties.
function pick(root, path, fallback = undefined) {
  try { 
    return path.split('.').reduce((o, k) => (o && k in o ? o[k] : undefined), root) ?? fallback 
  } catch { 
    return fallback 
  }
}

// 'normalizeUser' standardizes the user object returned from the backend.
// It handles different shapes of user data, ensuring we always get a consistent user object.
// Returns null if no valid user info is found.
function normalizeUser(obj) {
  if (obj && typeof obj === 'object') {
    if (obj.user && typeof obj.user === 'object') return obj.user
    if (obj.username || obj.role) return { username: obj.username, role: obj.role, ...obj }
  }
  return null
}

// 'normalizeCsrf' extracts the CSRF token string from the backend response object.
// If the object is invalid or missing, it returns an empty string.
function normalizeCsrf(obj) {
  if (!obj || typeof obj !== 'object') return ''
  return obj.csrf || ''
}

// 'persistCsrf' saves the CSRF token both in localStorage and on the global window object.
// This allows the token to be reused across requests for security.
// If saving to localStorage fails (e.g. in private mode), it silently ignores the error.
// The window.__CSRF is a fallback/global cache for quick access.
function persistCsrf(v) {
  if (!v) return
  try { 
    localStorage.setItem('csrf', v) 
  } catch {}
  // eslint-disable-next-line no-underscore-dangle
  window.__CSRF = v
}

// Saves the authenticated user to localStorage for guards and other consumers.
function persistUser(u) {
  if (!u) return
  try { localStorage.setItem('user', JSON.stringify(u)) } catch {}
}

// --- public API -----------------------------------------------------------

// Fetches a fresh CSRF token from the backend.
// Calls GET /csrf endpoint, expects response containing { data: { csrf: 'token' } } or similar.
// Extracts the token, persists it locally, and returns the token string.
export async function csrf() {
  const res = await api.get('/csrf')
  // Try to get nested data.data first, fallback to data directly
  const payload = pick(res, 'data.data', res?.data)
  const token = normalizeCsrf(payload)
  persistCsrf(token)
  return token
}

// Retrieves the current authenticated user's info from the backend.
// Calls GET /me endpoint, expects response containing { data: { user info } }.
// Returns a normalized user object or null if not authenticated.
export async function me() {
  const res = await api.get('/me')
  const payload = pick(res, 'data.data', res?.data)
  return normalizeUser(payload)
}

// Attempts to log in a user with username and password credentials.
// If no CSRF token is present locally, it fetches one first (some backends require CSRF even for login).
// Calls POST /auth/login with { username, password }.
// Expects response containing user info, csrf token, and optionally an auth token.
// Returns an object with { user, csrf, token }.
// Throws an error if no user is returned, including a sample of the payload for debugging.
export async function login({ username, password }) {
  // Some stacks require a CSRF token even for login; fetch once if we don't have it
  if (!localStorage.getItem('csrf') && !window.__CSRF) {
    try { 
      await csrf() 
    } catch { 
      /* ignore prefetch errors */ 
    }
  }

  const res = await api.post('/auth/login', { username, password })

  // Accept { ok:true, data:{ user, csrf, token? } } or flat shapes
  const payload = pick(res, 'data.data', res?.data)
  const user = normalizeUser(payload)
  const token = payload?.token || ''
  const csrfToken = normalizeCsrf(payload)

  if (csrfToken) persistCsrf(csrfToken)
  // Persist user so router guards can detect auth state
  persistUser(user)

  if (!user) {
    // Surface a compact view of what we actually received to help debugging
    const sample = JSON.stringify(payload ?? res?.data ?? {}, null, 2).slice(0, 400)
    throw new Error(`No user returned from /auth/login. Payload sample: ${sample}`)
  }

  return { user, csrf: csrfToken, token }
}

// Logs out the current user by calling POST /auth/logout.
// Clears stored CSRF token and user info from localStorage and resets the global CSRF token.
// Errors during logout or clearing storage are caught and ignored to avoid breaking app flow.
export async function logout() {
  try { 
    await api.post('/auth/logout', {}) 
  } catch (_) {}
  try { 
    localStorage.removeItem('csrf'); 
    localStorage.removeItem('user') 
  } catch {}
  // eslint-disable-next-line no-underscore-dangle
  window.__CSRF = ''
}

// Also provide a default export for compatibility with code using `import AuthAPI from '@/api/auth'`
export default { csrf, me, login, logout }