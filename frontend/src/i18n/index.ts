import { createI18n } from 'vue-i18n'
import uk from './uk'

/**
 * Ukrainian has three forms of a counted noun: 1 день, 2 дні, 5 днів (and 21 день, 11 днів). A message lists them
 * separated by "|": "{n} день | {n} дні | {n} днів". A message with two forms falls back to one and many.
 */
export function ukrainianPlural(choice: number, choicesLength: number): number {
  if (choicesLength < 3) {
    return choice === 1 ? 0 : 1
  }

  const lastDigit = choice % 10
  const lastTwoDigits = choice % 100

  if (lastDigit === 1 && lastTwoDigits !== 11) return 0
  if (lastDigit >= 2 && lastDigit <= 4 && (lastTwoDigits < 12 || lastTwoDigits > 14)) return 1
  return 2
}

export const i18n = createI18n({
  legacy: false,
  locale: 'uk',
  fallbackLocale: 'uk',
  messages: { uk },
  pluralRules: { uk: ukrainianPlural },
})
