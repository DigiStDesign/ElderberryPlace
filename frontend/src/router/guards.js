// Vue Router navigation guards for authentication and role-based redirects.

import { useAuthStore } from '../stores/auth'

// Looks across multiple possible user object keys to find a role or falls back to username/email
function extractRawRole(user) {
  if (!user || typeof user !== 'object') return ''
  // Try several common keys that backends use
  const keys = ['role', 'user_role', 'type', 'account_type', 'userType', 'user_type']
  for (const k of keys) {
    if (user[k]) return user[k]
  }
  // Fallback: infer from username (useful for demo accounts)
  if (user.username) return String(user.username)
  if (user.email) return String(user.email).split('@')[0]
  return ''
}

// Standardizes role values and maps variants/numeric codes to 'admin', 'staff', 'resident'
function normalizeRole(raw) {
  if (!raw) return ''
  const r = String(raw).toLowerCase().replace(/[^a-z0-9]/g, '')
  // Common admin variants
  if (['admin','administrator','superadmin','root','1'].includes(r)) return 'admin'
  // Common staff variants
  if (['staff','employee','careworker','carer','team','2','staffmember','worker'].includes(r)) return 'staff'
  // Common resident variants
  if (['resident','patient','client','member','3','res'].includes(r)) return 'resident'
  // Otherwise return normalized string as-is
  return r
}

// Decides the default landing path based on the user role
function landingFor(auth) {
  const raw = extractRawRole(auth.user)
  const role = normalizeRole(raw)
  console.log('[guards] raw role:', raw, '→ normalized:', role)
  // Map normalized roles to their landing paths
  const map = {
    admin: '/home',
    staff: '/staff',
    resident: '/residents'
  }
  // Default fallback is /home if no role-specific mapping found
  return map[role] || '/home'
}

// Ensures a user is logged in before allowing route access, and redirects root path to their landing
export function requireAuth(to, from, next) {
  const auth = useAuthStore()
  if (!auth.isAuthed) return next('/login')
  // If user is authenticated and hits the root, steer them to their landing
  if (to.path === '/' || to.name === 'root') return next(landingFor(auth))
  return next()
}

// Prevents logged-in users from seeing login/register pages by redirecting to their landing
export function redirectIfAuthed(to, from, next) {
  const auth = useAuthStore()
  if (auth.isAuthed) return next(landingFor(auth))
  return next()
}

// Enforces specific roles on routes, redirecting to the correct landing if the role doesn’t match
export function requireRole(roles = []) {
  const set = new Set(roles.map(r => normalizeRole(r)))
  return (to, from, next) => {
    const auth = useAuthStore()
    if (!auth.isAuthed) return next('/login')
    const role = normalizeRole(extractRawRole(auth.user))
    // Allow if no roles specified or if user's role is in allowed set
    if (set.size === 0 || set.has(role)) return next()
    // Redirect to landing if role doesn't match
    return next(landingFor(auth))
  }
}