import { loginService, signupService, fetchProfileService } from '~/services/auth'
import type { SignupPayload } from '~/types/auth'
import { useAppToast } from './useAppToast'

export function useSignup() {
  const { toast } = useAppToast()
  const authStore = useAuthStore()
  const loading = ref(false)

  async function signup(payload: SignupPayload) {
    loading.value = true
    try {
      const res = await signupService(payload)
      const loginRes = await loginService(payload.email, payload.password)
      authStore.setToken(loginRes.token)

      // Fetch profile to store user info (including isVerified)
      try {
        const profile = await fetchProfileService(res.id)
        authStore.setProfile(profile)
      } catch {
        // Silently handle profile fetch failure
      }

      toast({
        title: 'Bienvenue !',
        description: 'Un email de vérification vous a été envoyé.',
        variant: 'success',
      })
      await navigateTo('/auth/email-verification')
    } catch (err: unknown) {
      const e = err as {
        status?: number
        data?: { message?: string; violations?: unknown[] }
        message?: string
      }
      let description = 'Une erreur est survenue, veuillez réessayer'
      if (e.status === 422) {
        const hasViolations = Array.isArray(e.data?.violations) && e.data.violations.length > 0
        description = hasViolations
          ? 'Données invalides. Vérifiez vos informations.'
          : 'Email déjà utilisé'
      } else if (e.status === 500) {
        description = 'Erreur serveur. Veuillez réessayer plus tard'
      } else {
        description = e.data?.message || e.message || description
      }
      toast({
        title: 'Erreur',
        description,
        variant: 'destructive',
      })
    } finally {
      loading.value = false
    }
  }

  return {
    signup,
    loading: readonly(loading),
  }
}
