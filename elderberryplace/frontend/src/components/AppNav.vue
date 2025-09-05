<template>
  <!-- AppNav.vue: Renders the main navigation bar for the app -->
  <nav style="padding: 8px 12px; border-bottom: 1px solid #eee;">
    <!-- For each entry in `links`, render a clickable item. We prevent the default
         anchor navigation and use router-based navigation to stay in the SPA. -->
    <a
      v-for="link in links"
      :key="link.to"
      :href="link.to"
      style="margin-right: 12px;"
      @click.prevent="go(link.to)"
    >
      {{ link.label }}
    </a>
  </nav>
</template>

<script setup>
// AppNav uses vue-router to navigate between pages without a full reload.
import { useRouter } from 'vue-router'

// Get the router instance; we'll use this for programmatic navigation.
const router = useRouter()

// Top-level navigation items displayed in the header.
// Each item has a `to` (route path) and a human-friendly `label`.
const links = [
  { to: '/', label: 'Home' },
  { to: '/residents', label: 'Residents' },
  { to: '/staff', label: 'Staff' },
  { to: '/visitors', label: 'Visitors' },
  { to: '/services', label: 'Services' },
  { to: '/visits', label: 'Visits' },
  { to: '/schedule', label: 'Schedule' },
  { to: '/categories', label: 'Categories' },
  { to: '/relationships', label: 'Relationships' }
]

// go(): navigate to the given route path programmatically (keeps SPA behavior).
function go(to) {
  router.push(to)
}
</script>