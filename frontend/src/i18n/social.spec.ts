import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'
import social, { apiErrors } from './social.uk'

// Every file of the community, read as text, to find the messages it asks for.
const sources = import.meta.glob(
  ['../views/*.vue', '../components/*.vue', '../router/social.ts', '../composables/*.ts'],
  { query: '?raw', import: 'default', eager: true },
) as Record<string, string>

const KEY = /(?:\$t|\bt)\(\s*['"`](social\.[A-Za-z0-9_.]+)['"`]/g

function keysUsedIn(source: string): string[] {
  return [...source.matchAll(KEY)].map((match) => match[1]!)
}

function flatten(tree: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(tree).flatMap(([key, value]) =>
    typeof value === 'object' && value !== null ? flatten(value as Record<string, unknown>, `${prefix}${key}.`) : [`${prefix}${key}`],
  )
}

describe('the messages of the community', () => {
  const used = Object.entries(sources).flatMap(([file, source]) => keysUsedIn(source).map((key) => ({ file, key })))

  it('are all there for what the screens ask for', () => {
    expect(used.length).toBeGreaterThan(50)

    const missing = used.filter(({ key }) => !i18n.global.te(key)).map(({ file, key }) => `${key} (${file})`)

    expect(missing).toEqual([])
  })

  it('are all in Ukrainian: none is left empty or in English', () => {
    for (const key of flatten(social as Record<string, unknown>)) {
      const message = i18n.global.t(`social.${key}`)

      expect(message, key).not.toBe('')
      expect(message, key).toMatch(/[А-Яа-яІіЇїЄєҐґ0-9{]/)
    }
  })

  it('explain every error code the server can answer with', () => {
    const codes = [
      'review_exists',
      'cannot_follow_self',
      'user_blocked',
      'cannot_block_self',
      'already_reported',
      'cannot_report_own',
      'handle_taken',
      'handle_invalid',
    ]

    expect(Object.keys(apiErrors).sort()).toEqual([...codes].sort())
    for (const code of codes) {
      expect(i18n.global.te(`apiErrors.social.${code}`)).toBe(true)
    }
  })

  it('count in good Ukrainian', () => {
    const t = i18n.global.t
    const comments = (n: number) => t('social.post.comments', { n }, n)

    expect([1, 2, 5, 11, 21, 22, 25].map(comments)).toEqual([
      '1 коментар',
      '2 коментарі',
      '5 коментарів',
      '11 коментарів',
      '21 коментар',
      '22 коментарі',
      '25 коментарів',
    ])
  })
})
