<script setup lang="ts">
import { resendVerificationEmailService } from '~/services/auth'

const authStore = useAuthStore()
const resending = ref(false)
const sent = ref(false)

async function resend() {
  resending.value = true
  try {
    await resendVerificationEmailService()
    sent.value = true
    setTimeout(() => {
      sent.value = false
    }, 5000)
  } catch {
    // Silently handle
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <div
    v-if="authStore.isAuthenticated && !authStore.isVerified"
    class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 flex items-center gap-3"
    role="alert"
  >
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
      <path d="M12 2L2 22 22 22z" /><circle cx="12" cy="12" r="10" /><line x1="10" y1="16" x2="14" y2="16" /><line x1="12" y1="8" x2="12" y2="12" />
    </svg>

    <span class="flex-1">
      <template v-if="sent">
        Email renvoyé ! Vérifiez votre boîte de réception.
      </template>
      <template v-else>
        Veuillez vérifier votre adresse email pour accéder à toutes les fonctionnalités.
      </template>
    </span>

    <Button
      v-if="!sent"
      variant="outline"
      size="sm"
      :loading="resending"
      @click="resend"
    >
      Renvoyer
    </Button>
  </div>
</template>

<style scoped>
.bg-yellow-50 { background-color: #fffbeb; }
.text-yellow-800 { color: #92400e; }
.border-yellow-300 { border-color: #fde68a; }

[data-theme='dark'] .bg-yellow-50 { background-color: rgba(120, 53, 15, 0.2); }
[data-theme='dark'] .text-yellow-800 { color: #fcd34d; }
[data-theme='dark'] .border-yellow-300 { border-color: #78350f; }
</style>