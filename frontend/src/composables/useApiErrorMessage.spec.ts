import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { defineComponent } from 'vue'
import { ApiError, NetworkError } from '@/api/http'
import { i18n } from '@/i18n'
import { useApiErrorMessage } from './useApiErrorMessage'

function messageFor(error: unknown): string {
  let message = ''

  mount(
    defineComponent({
      setup() {
        message = useApiErrorMessage()(error)
        return () => null
      },
    }),
    { global: { plugins: [i18n] } },
  )

  return message
}

describe('useApiErrorMessage', () => {
  it('blames the connection when there was no answer at all', () => {
    expect(messageFor(new NetworkError())).toBe('Немає зв’язку із сервером. Перевірте інтернет і спробуйте ще раз.')
  })

  it('translates the business rules of the API', () => {
    expect(messageFor(new ApiError(422, { errors: { status: ['library.invalid_transition'] } }))).toBe(
      'З поточного статусу не можна перейти до цього.',
    )
  })

  it('asks to slow down on 429', () => {
    expect(messageFor(new ApiError(429, null))).toBe('Забагато запитів. Спробуйте за хвилину.')
  })

  it('has a general message for everything else', () => {
    expect(messageFor(new ApiError(500, null))).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
    expect(messageFor(new Error('boom'))).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
  })
})
