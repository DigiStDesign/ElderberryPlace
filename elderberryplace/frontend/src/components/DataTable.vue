<template>
  <table style="width:100%; border-collapse: collapse;">
    <thead>
      <tr>
        <th v-for="h in headers" :key="h.key" style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">
          {{ h.label }}
        </th>
        <th style="border-bottom:1px solid #ddd; padding:8px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="row in rows" :key="row.id">
        <td v-for="h in headers" :key="h.key" style="padding:8px; border-bottom:1px solid #f1f1f1;">
          {{ row[h.key] }}
        </td>
        <td style="padding:8px; border-bottom:1px solid #f1f1f1;">
          <slot name="actions" :row="row">
            <button @click="$emit('edit', row)">Edit</button>
            <button @click="$emit('delete', row)">Delete</button>
          </slot>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
defineProps({
  headers: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] }
})
defineEmits(['edit', 'delete'])
</script>