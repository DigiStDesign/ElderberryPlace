<template>
  <div class="min-h-screen">
    <AppHeader />
    <AppNav v-if="isAuthed" />
    <main class="container" style="max-width: 1000px; margin: 16px auto; padding: 0 12px;">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import AppHeader from './components/AppHeader.vue'
import AppNav from './components/AppNav.vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const { isAuthed } = storeToRefs(auth)
</script>