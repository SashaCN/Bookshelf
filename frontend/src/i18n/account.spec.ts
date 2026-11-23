import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'

// The files of the account screens, read as text, to find the messages they ask for.
const sources = import.meta.glob(
  ['../views/ForgotPasswordView.vue', '../views/ResetPasswordView.vue', '../views/LoginView.vue', '../views/ProfileView.vue', '../components/DeleteAccountDialog.vue'],
  { query: '?raw', import: 'default', eager: true },
) as Record<string, string>

const KEY = /(?:\$t|\bt)\(\s*['"`](account\.[A-Za-z0-9_.]+)['"`]/g

describe('the messages of the account screens', () => {
  it('are all there for what the screens ask for', () => {
    const used = Object.entries(sources).flatMap(([file, source]) => [...source.matchAll(KEY)].map((match) => ({ file, key: match[1]! })))

    expect(used.length).toBeGreaterThan(20)
    expect(used.filter(({ key }) => !i18n.global.te(key)).map(({ file, key }) => `${key} (${file})`)).toEqual([])
  })

  it('has the items of the deletion dialog', () => {
    for (const item of ['library', 'quotes', 'community', 'books', 'follows']) {
      expect(i18n.global.te(`account.danger.items.${item}`)).toBe(true)
    }
  })
})
