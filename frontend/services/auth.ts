import type {
  SignupPayload,
  SignupResponse,
  UserProfileResponse,
  VerifiedUsersCountResponse,
} from '~/types/auth'

const API_ENDPOINTS = {
  SIGNUP: '/api/users',
  PROFILE: '/api/users/me',
  VERIFIED_USERS_COUNT: '/api/v1/users/verified/count',
  DELETE_ACCOUNT: '/api/v1/users/me',
  RESEND_VERIFICATION: '/api/email/resend-verification',
} as const

function authHeaders(): Record<string, string> {
  const store = useAuthStore()
  const headers: Record<string, string> = {}
  if (store.token) {
    headers.Authorization = `Bearer ${store.token}`
  }
  return headers
}

export interface LoginResponse {
  token: string
}

interface OAuth2TokenResponse {
  access_token: string
  refresh_token: string
  token_type: string
  expires_in: number
}

export const loginService = async (email: string, password: string): Promise<LoginResponse> => {
  const config = useRuntimeConfig()

  const body = new URLSearchParams({
    grant_type: 'password',
    client_id: config.public.oauthClientId,
    client_secret: config.public.oauthClientSecret,
    username: email,
    password,
  })

  const res = await $fetch<OAuth2TokenResponse>('/oauth2/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  })

  return { token: res.access_token }
}

export const signupService = (payload: SignupPayload): Promise<SignupResponse> => {
  return $fetch<SignupResponse>(API_ENDPOINTS.SIGNUP, {
    method: 'POST',
    headers: { 'Content-Type': 'application/ld+json' },
    body: { ...payload },
  })
}

export const fetchProfileService = (): Promise<UserProfileResponse> => {
  return $fetch<UserProfileResponse>(API_ENDPOINTS.PROFILE, {
    headers: { Accept: 'application/ld+json', ...authHeaders() },
  })
}

export const resendVerificationEmailService = (email: string): Promise<void> => {
  return $fetch<void>(API_ENDPOINTS.RESEND_VERIFICATION, {
    method: 'POST',
    headers: { 'Content-Type': 'application/ld+json' },
    body: { email },
  })
}

export const fetchVerifiedUsersCountService = (): Promise<VerifiedUsersCountResponse> => {
  return $fetch<VerifiedUsersCountResponse>(API_ENDPOINTS.VERIFIED_USERS_COUNT, {
    headers: { ...authHeaders() },
  })
}

export const deleteAccountService = (): Promise<void> => {
  return $fetch<void>(API_ENDPOINTS.DELETE_ACCOUNT, {
    method: 'DELETE',
    headers: { ...authHeaders() },
  })
}
