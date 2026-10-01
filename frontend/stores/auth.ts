import { defineStore } from 'pinia'
import type { AuthUser, UserProfileResponse } from '~/types/auth'
import { fetchProfileService } from '~/services/auth'

const TOKEN_KEY = 'auth_token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(null)
  const user = ref<AuthUser | null>(null)

  const isAuthenticated = computed(() => !!token.value)
  const isVerified = computed(() => user.value?.isVerified ?? false)

  function setToken(value: string) {
    token.value = value
    if (import.meta.client) {
      localStorage.setItem(TOKEN_KEY, value)
    }
  }

  function setProfile(profile: UserProfileResponse) {
    user.value = {
      id: profile.id,
      email: profile.email,
      firstName: profile.firstName,
      lastName: profile.lastName,
      isVerified: profile.isVerified,
    }
  }

  function setUser(value: AuthUser) {
    user.value = value
  }

  async function fetchProfile() {
    if (!token.value || !user.value) return
    try {
      const profile = await fetchProfileService(user.value.id)
      setProfile(profile)
    } catch {
      // Silent fail — le token peut être invalide
    }
  }

  function hydrate() {
    if (import.meta.client && !token.value) {
      const stored = localStorage.getItem(TOKEN_KEY)
      if (stored) token.value = stored
    }
  }

  function logout() {
    token.value = null
    user.value = null
    if (import.meta.client) {
      localStorage.removeItem(TOKEN_KEY)
    }
  }

  return { token, user, isAuthenticated, isVerified, setToken, setProfile, setUser, fetchProfile, hydrate, logout }
})
