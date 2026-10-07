/** Formats the 8 digits of a postal code (CEP) the way people write it ("01310100" -> "01310-100"). */
export function formatPostalCode(digits: string): string {
  return digits.length === 8 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits
}
