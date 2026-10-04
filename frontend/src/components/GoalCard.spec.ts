import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createTestEnvironment } from '@/test/utils'
import type { GoalProgress } from '@/types/api'
import GoalCard from './GoalCard.vue'

function makeGoal(overrides: Partial<GoalProgress> = {}): GoalProgress {
  return {
    year: 2026,
    target_books: 12,
    finished_books: 6,
    expected_books: 6,
    due_books: 6,
    remaining_books: 6,
    on_track: true,
    ...overrides,
  }
}

function mountCard(goal: GoalProgress, props: { busy?: boolean; error?: string } = {}) {
  const { plugins } = createTestEnvironment()
  return mount(GoalCard, { props: { goal, ...props }, global: { plugins } })
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('GoalCard', () => {
  it('shows the progress against the goal', () => {
    const wrapper = mountCard(makeGoal({ finished_books: 3 }))

    expect(wrapper.text()).toContain('Ціль на 2026 рік')
    expect(wrapper.text()).toContain('3')
    expect(wrapper.text()).toContain('прочитано · ціль 12')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('25')
  })

  it('says the reader is on track and how many books are left', () => {
    const wrapper = mountCard(makeGoal({ remaining_books: 6, on_track: true }))

    expect(wrapper.text()).toContain('Ви в графіку. Ще потрібно: 6 книг')
  })

  it.each([
    [1, 'Ще потрібно: 1 книга'],
    [2, 'Ще потрібно: 2 книги'],
    [5, 'Ще потрібно: 5 книг'],
    [21, 'Ще потрібно: 21 книга'],
  ])('writes %i remaining books in correct Ukrainian', (remaining, expected) => {
    const wrapper = mountCard(makeGoal({ target_books: 40, remaining_books: remaining, on_track: true }))

    expect(wrapper.text()).toContain(expected)
  })

  it('says how many books should be done when the reader is behind', () => {
    const wrapper = mountCard(makeGoal({ finished_books: 3, due_books: 6, on_track: false, remaining_books: 9 }))

    expect(wrapper.text()).toContain('Відстаєте від графіка: за планом уже мало б бути прочитано 6 книг')
  })

  it('celebrates a finished goal and caps the bar at 100%', () => {
    const wrapper = mountCard(makeGoal({ finished_books: 14, remaining_books: 0, on_track: true }))

    expect(wrapper.text()).toContain('Ціль виконано!')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('100')
  })

  it('asks for a goal when there is none', async () => {
    const wrapper = mountCard(makeGoal({ target_books: null, expected_books: null, due_books: null, remaining_books: null, on_track: null }))

    expect(wrapper.text()).toContain('Поставте ціль')
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)

    await wrapper.get('#goal-target').setValue('24')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')).toEqual([[24]])
  })

  it('will not save a goal that is not a whole number from 1 to 1000', async () => {
    const wrapper = mountCard(makeGoal({ target_books: null, expected_books: null, due_books: null, remaining_books: null, on_track: null }))

    for (const bad of ['0', '1001', '2.5', '-3']) {
      await wrapper.get('#goal-target').setValue(bad)
      expect(wrapper.text()).toContain('Введіть ціле число від 1 до 1000.')
      expect(buttonWithText(wrapper, 'Зберегти').attributes('disabled')).toBeDefined()
    }

    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')).toBeUndefined()
  })

  it('changes the goal through the same form, with the current value in it', async () => {
    const wrapper = mountCard(makeGoal())

    await buttonWithText(wrapper, 'Змінити ціль').trigger('click')
    expect((wrapper.get('#goal-target').element as HTMLInputElement).value).toBe('12')

    await wrapper.get('#goal-target').setValue('20')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')).toEqual([[20]])
  })

  it('keeps the form open until the new goal arrives, so a failed save loses nothing', async () => {
    const wrapper = mountCard(makeGoal())

    await buttonWithText(wrapper, 'Змінити ціль').trigger('click')
    await wrapper.get('#goal-target').setValue('20')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.find('form').exists()).toBe(true)

    await wrapper.setProps({ goal: makeGoal({ target_books: 20 }) })

    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.text()).toContain('ціль 20')
  })

  it('closes the form without saving when nothing was changed or when cancelled', async () => {
    const wrapper = mountCard(makeGoal())

    await buttonWithText(wrapper, 'Змінити ціль').trigger('click')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.find('form').exists()).toBe(false)

    await buttonWithText(wrapper, 'Змінити ціль').trigger('click')
    await buttonWithText(wrapper, 'Скасувати').trigger('click')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('removes the goal', async () => {
    const wrapper = mountCard(makeGoal())

    await buttonWithText(wrapper, 'Прибрати').trigger('click')

    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('shows an error and blocks the buttons while busy', () => {
    const wrapper = mountCard(makeGoal(), { busy: true, error: 'Не вдалося' })

    expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося')
    expect(buttonWithText(wrapper, 'Змінити ціль').attributes('disabled')).toBeDefined()
    expect(buttonWithText(wrapper, 'Прибрати').attributes('disabled')).toBeDefined()
  })
})
