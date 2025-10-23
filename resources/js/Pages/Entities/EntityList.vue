<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import EntityRow from './EntityRow.vue'
import Pagination from '@/Components/Pagination.vue';
defineProps({
  titles: Array,
  data: Object,
  pagination: Object
})
</script>

<template>
  <Head title="Контакти" />

  <AuthenticatedLayout>


  <div class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold mb-4">Список записів</h1>
      <button
        @click="$inertia.visit(route('contact.add'))"
        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
      >
        + Додати
      </button>
    </div>

    <div class="overflow-x-auto rounded-xl shadow border mb-6">
      <table class="min-w-full border-collapse bg-white">
        <thead class="bg-gray-100 text-gray-700 text-sm">
          <tr>
            <th
              v-for="title in titles"
              :key="title.field"
              class="py-2 px-3 text-left"
            >
              {{ title.caption }}
            </th>
          </tr>
        </thead>
        <tbody>
          <EntityRow
            v-for="row in data"
            :key="row.id"
            :row="row"
            :titles="titles"
          />
        </tbody>
      </table>
    </div>

    <!-- 🔹 Пагинация -->
    <div class="flex flex-wrap gap-2">
       <Pagination
       :pagination="pagination"
       />
    </div>
  </div>
  </AuthenticatedLayout>
</template>
