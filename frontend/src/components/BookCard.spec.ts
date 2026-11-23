import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import BookCard from './BookCard.vue'

function card(timesRead: number) {
  const { plugins } = createTestEnvironment()
  return mount(BookCard, { props: { userBook: makeUserBook({ status: 'finished', times_read: timesRead }) }, global: { plugins } })
}

describe('BookCard', () => {
  it('shows how many times a book was read from the second time on', () => {
    expect(card(2).text()).toContain('×2')
    expect(card(5).text()).toContain('×5')
  })

  it('has no badge for a book read once or never', () => {
    expect(card(1).text()).not.toContain('×')
    expect(card(0).text()).not.toContain('×')
  })
})
