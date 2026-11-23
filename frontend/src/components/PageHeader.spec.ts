import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createTestEnvironment } from '@/test/utils'
import { useAuthStore } from '@/stores/auth'
import PageHeader from './PageHeader.vue'

function mountHeader(newFollowers: number) {
  const { plugins } = createTestEnvironment()
  useAuthStore().user = {
    id: 1, name: 'Olena', email: 'o@example.com', timezone: 'Europe/Kyiv', username: 'olena', is_public: true, new_followers_count: newFollowers,
  }
  return mount(PageHeader, { props: { title: 'Title' }, global: { plugins } })
}

describe('PageHeader new followers badge', () => {
  it('has no badge when nobody new followed', () => {
    expect(mountHeader(0).find('[data-test="new-followers-badge"]').exists()).toBe(false)
  })

  it.each([
    [1, '1 новий підписник'],
    [3, '3 нові підписники'],
    [7, '7 нових підписників'],
  ])('shows %i with a plural label', (count, label) => {
    const badge = mountHeader(count).get('[data-test="new-followers-badge"]')

    expect(badge.text()).toBe(String(count))
    expect(badge.attributes('aria-label')).toBe(label)
  })

  it('marks the capped count as "at least"', () => {
    expect(mountHeader(99).get('[data-test="new-followers-badge"]').text()).toBe('99+')
  })
})
