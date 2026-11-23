/** The page a visitor wanted before the login form interrupted them; anything that is not an in-app path is ignored. */
export function safeRedirect(value: unknown): string | null {
  const target = Array.isArray(value) ? value[0] : value

  if (typeof target !== 'string' || !target.startsWith('/') || target.startsWith('//') || target.startsWith('/\\')) {
    return null
  }

  // Sending a signed-in reader back to the guest-only forms would just bounce them to the home page.
  if (/^\/(login|register)(?:[/?#]|$)/.test(target)) {
    return null
  }

  return target
}
