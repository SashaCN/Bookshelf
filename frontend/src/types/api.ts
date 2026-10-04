export interface User {
  id: number
  name: string
  email: string
  timezone: string
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload extends LoginPayload {
  name: string
  timezone: string
}
