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

      // If email verification is required and user is not verified yet,
      // redirect directly to email-verification page (skip login).
      if (res.isVerified === false) {
        toast({
          title: 'Bienvenue !',
          description: 'Un email de vérification vous a été envoyé.',
          variant: 'success',
        })
        await navigateTo('/auth/email-verification')
        return
      }

      // User is already verified (e.g. admin-created account) — login normally
      const loginRes = await loginService(payload.email, payload.password)
      authStore.setToken(loginRes.token)

      try {
        const profile = await fetchProfileService()
        authStore.setProfile(profile)
      } catch {
        // Silently handle profile fetch failure
      }

      toast({
        title: 'Bienvenue !',
        description: 'Votre compte a été créé avec succès.',
        variant: 'success',
      })
      await navigateTo('/')
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
