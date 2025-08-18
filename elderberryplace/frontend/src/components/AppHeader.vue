<template>
  <header style="padding: 12px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
    <h1 style="margin: 0; font-size: 20px;">Elderberry Place</h1>
    <div v-if="isAuthed" style="display:flex; gap:8px; align-items:center;">
      <span>{{ auth.user?.name || 'Logged in' }}</span>
      <button @click="logout">Logout</button>
    </div>
  </header>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore()
const { isAuthed } = storeToRefs(auth)
function logout() { auth.logout(); window.location.href = '/login' }
</script>