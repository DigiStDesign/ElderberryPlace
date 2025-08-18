<template>
  <nav style="padding: 8px 12px; border-bottom: 1px solid #eee;">
    <a v-for="link in links" :key="link.to" :href="link.to" style="margin-right: 12px;" @click.prevent="go(link.to)">
      {{ link.label }}
    </a>
  </nav>
</template>

<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
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
function go(to) { router.push(to) }
</script>