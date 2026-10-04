import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'

const { t } = i18n.global

describe('Ukrainian plural forms', () => {
  it.each([
    [0, '0 днів'],
    [1, '1 день'],
    [2, '2 дні'],
    [4, '4 дні'],
    [5, '5 днів'],
    [11, '11 днів'],
    [12, '12 днів'],
    [14, '14 днів'],
    [21, '21 день'],
    [22, '22 дні'],
    [25, '25 днів'],
    [101, '101 день'],
    [111, '111 днів'],
  ])('writes %i as "%s"', (n, expected) => {
    expect(t('stats.days', { n }, n)).toBe(expected)
  })
})
