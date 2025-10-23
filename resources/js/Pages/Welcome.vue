<script setup>
import { Link, usePage } from '@inertiajs/vue3'

const { props } = usePage()
const user = props.auth?.user
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-100">
    <div class="text-center">
      <h1 class="text-3xl font-bold mb-6 text-gray-800">
        Ласкаво просимо до CRM-панелі
      </h1>

      <div v-if="!user" class="space-x-4">
        <Link
          href="/login"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Увійти
        </Link>
        <Link
          href="/register"
          class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
        >
          Зареєструватися
        </Link>
      </div>

      <div v-else>
        <Link
          href="/contacts"
          class="px-6 py-3 bg-indigo-600 text-white text-lg rounded-lg hover:bg-indigo-700"
        >
          Список контактів
        </Link>
      </div>
    </div>
  </div>
</template>
