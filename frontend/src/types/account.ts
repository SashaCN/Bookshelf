/** Types of the account security screens: changing and resetting the password. */

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}
