<template>
  <section style="max-width: 420px; margin: 60px auto;">
    <h2>Login</h2>
    <form @submit.prevent="submit">
      <div style="margin-bottom:12px;">
        <label>Email</label><br />
        <input v-model.trim="email" type="email" required style="width:100%; padding:8px;" />
      </div>
      <div style="margin-bottom:12px;">
        <label>Password</label><br />
        <input v-model="password" type="password" required style="width:100%; padding:8px;" />
      </div>
      <button :disabled="loading">{{ loading ? 'Signing in…' : 'Login' }}</button>
      <p v-if="error" style="color:#b00; margin-top:8px;">{{ error }}</p>
    </form>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login({ email: email.value, password: password.value })
    router.push('/')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>