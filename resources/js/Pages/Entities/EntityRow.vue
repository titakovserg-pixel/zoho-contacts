<script setup>
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  row: Object,
  titles: Array
})

function removeEntity(e) {
  e.stopPropagation() 
  if (confirm('Удалить запись?')) {    
    router.delete(route('contact.remove', props.row.id), {
      preserveScroll: true,
      onSuccess: () => alert('Удалено!')
    })
  }
}
</script>

<template>
  <tr
    class="hover:bg-gray-50 cursor-pointer transition"
    @click="$inertia.visit(route('contact.edit', row.id))"
  >
    <td
      v-for="title in titles"
      :key="title.field"
      class="py-2 px-3 border-t border-gray-200 text-sm"
    >
      {{ row[title.field] }}
    </td>

    <td class="py-2 px-3 border-t border-gray-200 text-right">
      <button
        @click="removeEntity"
        class="text-red-600 hover:text-red-800 text-sm font-medium"
      >
        Удалить
      </button>
    </td>
  </tr>
</template>
