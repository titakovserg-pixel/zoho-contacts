<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3'

// ✅ сохраняем результат defineProps в переменную
const props = defineProps({
  entity: { type: Object, required: true },
  titles: { type: [Object, Array], required: true },
  save_route: { type: String, required: true }
})

// ✅ используем props.entity
const form = useForm({ ...props.entity })
function save() {
  if (form.id) {
    form.put(props.save_route, { onSuccess: () => alert('Запис оновлений!') })
  } else {
    form.post(props.save_route, { onSuccess: () => alert('Запис створений!') })
  }
}
</script>


<template>
  <Head title="Контакт. Редагування" />

  <AuthenticatedLayout>
  <div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">Редагування</h1>
    <div v-if="form.errors.zoho" class="text-red-600 mb-4">
      {{ form.errors.zoho }}
    </div>

    <form @submit.prevent="save" class="space-y-4">
      <div
        v-for="title in titles"
        :key="title.field"
        class="flex flex-col"
      >
        <label class="text-sm font-medium text-gray-600 mb-1">
          {{ title.caption }}
        </label>
        <input
          v-model="form[title.field]"
          class="border rounded-lg p-2 focus:ring focus:ring-blue-100"
        />
      </div>

      <div class="flex justify-end mt-4">
        <button
          type="submit"
          class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
        >
          Зберегти
        </button>
      </div>
    </form>
  </div>
  </AuthenticatedLayout>
</template>
