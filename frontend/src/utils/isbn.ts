/** True for a 13-digit ISBN (978/979 prefix) with a valid check digit. */
export function isValidIsbn13(value: string): boolean {
  if (!/^97[89]\d{10}$/.test(value)) return false

  let sum = 0
  for (let i = 0; i < 12; i++) sum += Number(value[i]) * (i % 2 === 0 ? 1 : 3)

  return (10 - (sum % 10)) % 10 === Number(value[12])
}
