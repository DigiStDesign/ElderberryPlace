<!--
  AppHeader.vue renders the site header with the app name and a logout button if the user is logged in.
-->
<template>
  <!-- Main header bar styled with padding and border -->
  <header style="padding: 12px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
    <h1 style="margin: 0; font-size: 20px;">Elderberry Place</h1>
    <!-- This section only shows when a user is logged in, displaying their name and a logout button -->
    <div v-if="isAuthed" style="display:flex; gap:8px; align-items:center;">
      <span>{{ auth.user?.name || 'Logged in' }}</span>
      <button @click="logout">Logout</button>
    </div>
  </header>
</template>

<script setup>
  // Import the auth store from Pinia to access authentication state and actions
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore()
const { isAuthed } = storeToRefs(auth)

// This function calls the store logout and then redirects the user to the login page
function logout() { auth.logout(); window.location.href = '/login' }
</script>