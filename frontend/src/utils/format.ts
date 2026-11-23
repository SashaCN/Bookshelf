const dayFormat = new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long', timeZone: 'UTC' })
const numberFormat = new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 1 })
const shortDayFormat = new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'short', timeZone: 'UTC' })

/** "2026-10-05" as "5 жовтня". The date is the reader's own calendar day, so no time zone is applied. */
export function formatDay(isoDay: string): string {
  return dayFormat.format(new Date(`${isoDay}T00:00:00Z`))
}

/** "2026-10-05" as "5 жовт.", for the axis of a chart. */
export function formatShortDay(isoDay: string): string {
  return shortDayFormat.format(new Date(`${isoDay}T00:00:00Z`))
}

/** A change with its sign: "+18", "−270" (a real minus sign, which is easy to read at a glance) or "0". */
export function signed(value: number): string {
  if (value > 0) return `+${value}`
  if (value < 0) return `−${Math.abs(value)}`
  return '0'
}

/** A number the way a Ukrainian reads it: "13,4" and "3 100" (with a non-breaking space), at most one decimal. */
export function formatNumber(value: number): string {
  return numberFormat.format(value)
}

/** An ISO timestamp (UTC) as "5 жовтня" on the calendar of the given time zone, or of the browser by default. */
export function formatMoment(iso: string, timeZone?: string): string {
  try {
    return new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long', timeZone }).format(new Date(iso))
  } catch {
    return formatDay(iso.slice(0, 10))
  }
}

const monthFormat = new Intl.DateTimeFormat('uk-UA', { month: 'long', timeZone: 'UTC' })

/** A month number (1-12) by its name in the nominative: "травень". Without a day, Intl gives the standalone form, not "травня". */
export function monthName(month: number): string {
  return monthFormat.format(new Date(Date.UTC(2000, month - 1, 1)))
}
