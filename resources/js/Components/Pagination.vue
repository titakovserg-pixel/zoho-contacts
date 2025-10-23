<script setup>
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  pagination: {
    type: Object,
    required: true,
  },
})

const totalPages = computed(() =>
  Math.ceil(props.pagination.full_count / props.pagination.per_page)
)

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return

  const url = route(props.pagination.route_name, { page })

  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <div class="flex justify-center items-center gap-2 mt-4 select-none">
    <button
      @click="goToPage(pagination.page - 1)"
      :disabled="pagination.page <= 1"
      class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50 hover:bg-gray-300"
    >
      ←
    </button>

    <span class="text-gray-700">
      Сторінка {{ pagination.page }} з {{ totalPages }}
    </span>

    <button
      @click="goToPage(pagination.page + 1)"
      :disabled="pagination.page >= totalPages"
      class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50 hover:bg-gray-300"
    >
      →
    </button>
  </div>
</template>
