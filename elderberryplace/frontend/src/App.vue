// This is the root component of the Vue app. It sets up the overall layout with header, navigation, and router view.
<template>
  <!-- Full-page container with min-height styling -->
  <div class="min-h-screen">
    <!-- Shows the app header across all pages -->
    <AppHeader />
    <!-- Nav bar only appears when a user is authenticated -->
    <AppNav v-if="isAuthed" />
    <!-- Holds the routed content with max-width and padding -->
    <main class="container" style="max-width: 1000px; margin: 16px auto; padding: 0 12px;">
      <router-view />
    </main>
  </div>
</template>

<script setup>
// Importing storeToRefs to reactively extract store state, AppHeader and AppNav components, and the auth store from Pinia
import { storeToRefs } from 'pinia'
import AppHeader from './components/AppHeader.vue'
import AppNav from './components/AppNav.vue'
import { useAuthStore } from './stores/auth'

// Connecting to Pinia auth store and extracting isAuthed to conditionally show navigation
const auth = useAuthStore()
const { isAuthed } = storeToRefs(auth)
</script>