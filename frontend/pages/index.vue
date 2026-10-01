<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({
  middleware: 'auth',
  layout: 'default',
})

const authStore = useAuthStore()

// Fetch user profile to get isVerified status
onMounted(() => {
  authStore.fetchProfile()
})

useHead({
  title: 'Accueil',
})
</script>

<template>
    <div class="container-page">
        <EmailVerificationBanner />
        <section class="text-center py-16">
            <h1 class="text-3xl font-bold mb-4" v-if="authStore.user">
                - Bonjour {{ authStore.user.firstName }} -
            </h1>
        </section>
    </div>
</template>
