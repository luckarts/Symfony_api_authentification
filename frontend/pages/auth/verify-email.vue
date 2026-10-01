<script setup lang="ts">
import { useAppToast } from '~/composables/useAppToast'

definePageMeta({ layout: 'auth' })

const { toast } = useAppToast()
const authStore = useAuthStore()
const route = useRoute()

const userId = route.query.userId as string
const userEmail = route.query.userEmail as string

// The verification is done entirely by the backend via GET /api/email/verify
// The Nuxt proxy forwards /api/** to the backend.
// This page receives the callback with query params from the email link.
// The actual verification happens server-side through the proxy.
// We just show the result to the user.

const status = ref<'loading' | 'success' | 'error'>('loading')
const errorMessage = ref<string | null>(null)

onMounted(async () => {
  if (!userId || !userEmail) {
    status.value = 'error'
    errorMessage.value = 'Lien de vérification invalide.'
    return
  }

  try {
    // The backend handles verification via GET /api/email/verify
    // The proxy in nuxt.config.ts forwards /api/** -> backend
    await $fetch('/api/email/verify', {
      params: { userId, userEmail },
    })
    status.value = 'success'

    // Refresh the user profile to get updated isVerified status
    if (authStore.user) {
      await authStore.fetchProfile()
    }

    toast({
      title: 'Email vérifié !',
      description: 'Votre adresse email a été vérifiée avec succès.',
      variant: 'success',
    })
  } catch (err: unknown) {
    status.value = 'error'
    const e = err as { data?: { error?: string }; message?: string }
    errorMessage.value = e.data?.error || e.message || 'La vérification a échoué. Le lien est peut-être expiré.'
  }
})

async function goToDashboard() {
  await navigateTo('/')
}
</script>

<template>
  <Card variant="shadow" class="w-full max-w-sm">
    <div class="flex flex-col items-center gap-3">
      <AppLogo size="md" />
      <Heading :level="1" size="lg">Vérification email</Heading>
    </div>

    <div class="space-y-4 text-center">
      <!-- Loading -->
      <div v-if="status === 'loading'" class="flex flex-col items-center gap-3">
        <span class="inline-block animate-spin w-8 h-8 rounded-full border-2 border-blue-500 border-t-transparent" />
        <Text>Vérification de votre adresse email...</Text>
      </div>

      <!-- Success -->
      <div v-else-if="status === 'success'" class="flex flex-col items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" aria-hidden="true">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 16 10 12 14" fill="#22c55e" />
        </svg>
        <Heading :level="2" size="md">Email vérifié !</Heading>
        <Text>Votre adresse email a été vérifiée avec succès.</Text>
        <Button variant="primary" @click="goToDashboard">
          Accéder au tableau de bord
        </Button>
      </div>

      <!-- Error -->
      <div v-else class="flex flex-col items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" aria-hidden="true">
          <circle cx="12" cy="12" r="10" />
          <line x1="15" y1="9" x2="9" y2="15" />
          <line x1="9" y1="9" x2="15" y2="15" />
        </svg>
        <Heading :level="2" size="md">Échec de vérification</Heading>
        <Text>{{ errorMessage || 'Le lien de vérification est invalide ou a expiré.' }}</Text>
        <Button variant="primary" @click="goToDashboard">
          Retour à l'accueil
        </Button>
      </div>
    </div>
  </Card>
</template>