<template>
  <table style="width:100%; border-collapse: collapse;">
    <thead>
      <tr>
        <th v-for="h in headers" :key="h.key" style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">
          {{ h.label }}
        </th>
        <!-- Only render our own Actions column if the headers DO NOT already include one -->
        <th v-if="showActionsColumn" style="border-bottom:1px solid #ddd; padding:8px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="(row, idx) in rows" :key="row[rowKey] ?? idx">
        <td v-for="h in headers" :key="h.key" style="padding:8px; border-bottom:1px solid #f1f1f1;">
          <slot :name="`cell-${h.key}`" :row="row" :value="row[h.key]">
            {{ display(row[h.key]) }}
          </slot>
        </td>
        <!-- Only render our Actions cell if we're rendering the Actions column -->
        <td v-if="showActionsColumn" style="padding:8px; border-bottom:1px solid #f1f1f1;">
          <!-- Preferred actions slot -->
          <slot name="actions" :row="row">
            <!-- Fallback to cell-actions slot for compatibility -->
            <slot name="cell-actions" :row="row">
              <button @click="$emit('edit', row)">Edit</button>
              <button @click="$emit('delete', row)">Delete</button>
            </slot>
          </slot>
        </td>
      </tr>
      <tr v-if="!rows || rows.length === 0">
        <td :colspan="headers.length + (showActionsColumn ? 1 : 0)" style="padding:10px; text-align:center; color:#666;">
          No data.
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  headers: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  // which field to use for :key on each row
  rowKey: { type: String, default: 'id' },
  // whether to render our own Actions column when headers doesn't already include it
  showActions: { type: Boolean, default: true }
})

const emit = defineEmits(['edit', 'delete'])

const showActionsColumn = computed(() => {
  const hasActionsInHeaders = props.headers.some(h => String(h.key) === 'actions')
  return props.showActions && !hasActionsInHeaders
})

function display(val) {
  if (val === null || val === undefined || val === '') return '—'
  return String(val)
}
</script>