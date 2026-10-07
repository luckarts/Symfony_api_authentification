export interface SignupPayload {
  firstName: string
  lastName: string
  email: string
  password: string
}

export interface AuthUser {
  id: string
  email: string
  firstName: string
  lastName: string
  isVerified: boolean
}

export interface UserProfileResponse {
  id: string
  email: string
  firstName: string
  lastName: string
  roles: string[]
  isVerified: boolean
  createdAt: string
}

export interface SignupResponse {
  id: string
  email: string
  firstName: string
  lastName: string
  roles: string[]
  isVerified: boolean
  createdAt: string
  updatedAt: string
}

export interface VerifiedUsersResponse {
  member: string[]
  totalItems: number
  page: number
  itemsPerPage: number
}
