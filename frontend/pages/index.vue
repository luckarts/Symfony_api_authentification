<script setup lang="ts">
import { useAuthStore } from '~/stores/auth'

definePageMeta({
  middleware: 'auth',
  layout: 'default',
})

const authStore = useAuthStore()
const { t } = useI18n()

// Fetch user profile to get isVerified status
onMounted(() => {
  authStore.fetchProfile()
})

useHead({
  title: 'Tableau de bord',
})
</script>

<template>
  <div class="flex min-h-full">
    <VerifiedUsersSidebar />

    <div class="container-page flex-1">
      <EmailVerificationBanner />
      <section class="py-16 text-center">
        <Heading v-if="authStore.user" :level="1" size="2xl">
          {{ t('dashboard.greeting', { name: authStore.user.firstName }) }}
        </Heading>
      </section>
    </div>
  </div>
</template>
