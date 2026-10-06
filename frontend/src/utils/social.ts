const sameYearFormat = new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long' })
const otherYearFormat = new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long', year: 'numeric' })

/** "5 жовтня" for this year, "5 жовтня 2025 р." for an older post. The reader's own time zone decides the day. */
export function formatPostDate(iso: string, now: Date = new Date()): string {
  const date = new Date(iso)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return (date.getFullYear() === now.getFullYear() ? sameYearFormat : otherYearFormat).format(date)
}

/** The first letter of a name, for the round avatar. */
export function initialOf(name: string): string {
  return name.trim().charAt(0).toUpperCase() || '?'
}
