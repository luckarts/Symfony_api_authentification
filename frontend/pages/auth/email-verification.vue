<script setup lang="ts">
import { useAppToast } from '~/composables/useAppToast'
import { resendVerificationEmailService } from '~/services/auth'

definePageMeta({ layout: 'auth' })

const { toast } = useAppToast()
const authStore = useAuthStore()
const resending = ref(false)

async function resendEmail() {
  resending.value = true
  try {
    await resendVerificationEmailService()
    toast({
      title: 'Email renvoyé',
      description: 'Un nouvel email de vérification vous a été envoyé.',
      variant: 'success',
    })
  } catch (err: unknown) {
    const e = err as { data?: { error?: string }; status?: number }
    const description = e.data?.error || (e.status === 409 ? 'Email déjà vérifié' : 'Erreur lors du renvoi')
    toast({
      title: 'Erreur',
      description,
      variant: 'destructive',
    })

    // If already verified, redirect to home
    if (e.status === 409) {
      await authStore.fetchProfile()
      await navigateTo('/')
    }
  } finally {
    resending.value = false
  }
}

async function goToLogin() {
  await navigateTo('/auth/login')
}
</script>

<template>
  <Card variant="shadow" class="w-full max-w-sm">
    <div class="flex flex-col items-center gap-3">
      <AppLogo size="md" />
      <Heading :level="1" size="lg">Vérifiez votre email</Heading>
    </div>

    <div class="space-y-4 text-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="1.5" aria-hidden="true">
        <rect x="2" y="2" width="20" height="14" rx="2" />
        <polyline points="4 10 10 4" />
        <polyline points="14 10 20 4" />
        <polyline points="10 4 14 10" />
        <rect x="6" y="16" width="12" height="6" rx="1" />
      </svg>

      <Text>
        Un email de vérification vous a été envoyé à <strong>{{ authStore.user?.email || 'votre adresse email' }}</strong>.
      </Text>

      <Text>
        Cliquez sur le lien dans l'email pour vérifier votre compte. Si vous ne le trouvez pas, vérifiez vos spams.
      </Text>

      <div class="flex flex-col items-center gap-3">
        <Button
          variant="primary"
          full-width
          :loading="resending"
          @click="resendEmail"
        >
          Renvoyer l'email
        </Button>

        <Text as="p" class="text-center">
          Déjà vérifié ?
          <AppLink variant="brand" @click="goToLogin">Se connecter</AppLink>
        </Text>
      </div>
    </div>
  </Card>
</template>