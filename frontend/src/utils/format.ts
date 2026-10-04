const dayFormat = new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long', timeZone: 'UTC' })

/** "2026-10-05" as "5 жовтня". The date is the reader's own calendar day, so no time zone is applied. */
export function formatDay(isoDay: string): string {
  return dayFormat.format(new Date(`${isoDay}T00:00:00Z`))
}

/** A change with its sign: "+18", "−270" (a real minus sign, which is easy to read at a glance) or "0". */
export function signed(value: number): string {
  if (value > 0) return `+${value}`
  if (value < 0) return `−${Math.abs(value)}`
  return '0'
}
