<!--
This component renders the login form and handles authentication.
Upon successful login, it redirects users based on their role.
-->
<template>
  <!-- Centered login form container -->
  <section style="max-width: 420px; margin: 60px auto;">
    <h2>Login</h2>
    <form @submit.prevent="submit">
      <!-- Username input field -->
      <div style="margin-bottom:12px;">
        <label>Username</label><br />
        <input v-model.trim="username" type="text" required style="width:100%; padding:8px;" />
      </div>
      <!-- Password input field -->
      <div style="margin-bottom:12px;">
        <label>Password</label><br />
        <input v-model="password" type="password" required style="width:100%; padding:8px;" />
      </div>
      <!-- Submit button that toggles label and disables while loading -->
      <button type="submit" :disabled="loading">{{ loading ? 'Signing in…' : 'Login' }}</button>
      <!-- Error message shown only if login fails -->
      <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
    </form>
  </section>
</template>

<script setup>
// Import dependencies for reactive state and routing
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

// State refs for user input, loading status, and error messages
const router = useRouter()
const auth = useAuthStore()

const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

// Functions to interpret backend user object and normalize role strings
function extractRawRole(user) {
  if (!user || typeof user !== 'object') return ''
  const keys = ['role', 'user_role', 'type', 'account_type', 'userType', 'user_type']
  for (const k of keys) if (user[k]) return user[k]
  if (user.username) return String(user.username)
  if (user.email) return String(user.email).split('@')[0]
  return ''
}
function normalizeRole(raw) {
  if (!raw) return ''
  const r = String(raw).toLowerCase().replace(/[^a-z0-9]/g, '')
  if (['admin','administrator','superadmin','root','1'].includes(r)) return 'admin'
  if (['staff','employee','careworker','carer','team','2','staffmember','worker'].includes(r)) return 'staff'
  if (['resident','patient','client','member','3','res'].includes(r)) return 'resident'
  return r
}

// Handles login process: calls API, manages errors, and redirects based on role
async function submit() {
  // Clear previous error and set loading state
  error.value = ''
  loading.value = true
  try {
    // Attempt login with provided credentials
    await auth.login({ username: username.value, password: password.value })

    // Verify that user data was returned
    if (!auth.user) {
      error.value = 'Login failed: no user returned from API'
      console.warn('[login] no user returned, check /auth/login response shape')
      return
    }

    // Extract and normalize user role to determine landing page
    const raw = extractRawRole(auth.user)
    const role = normalizeRole(raw)
    const landingMap = { admin: '/home', staff: '/staff', resident: '/residents' }
    const landing = landingMap[role] || '/home'

    // Log details for debugging
    console.log('[login] user:', auth.user)
    console.log('[login] raw role:', raw, '→ normalized:', role)
    console.log('[login] redirecting to', landing)

    // Redirect user to appropriate landing page
    await router.replace(landing)
  } catch (e) {
    console.error('[login] error', e)
    error.value = e?.response?.data?.error?.message || e?.response?.data?.message || e?.message || 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>