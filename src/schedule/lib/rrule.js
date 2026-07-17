/**
 * Converts schedule form state to/from RFC 5545 recurrence rule strings.
 */

const FREQ = { daily: 'DAILY', weekly: 'WEEKLY', monthly: 'MONTHLY' }

/**
 * Builds an RFC 5545 RRULE string from schedule form state.
 *
 * @param {object}   config           - Schedule config.
 * @param {string}   config.type      - Frequency type (daily | weekly | monthly).
 * @param {number}   [config.interval=1]  - Recurrence interval.
 * @param {string[]} [config.weekdays=[]] - Selected weekday codes for weekly mode.
 * @param {Array}    [config.monthDays=[1]] - Month-day entries for monthly mode.
 * @param {number|null} [config.nthWeek=null] - Nth-week prefix for weekly BYDAY.
 * @returns {string}
 */
export function buildRRule({ type, interval = 1, weekdays = [], monthDays = [1], nthWeek = null }) {
  const parts = [`FREQ=${FREQ[type] ?? 'DAILY'}`]

  if (interval !== 1) parts.push(`INTERVAL=${interval}`)

  if (type === 'weekly' && weekdays.length > 0) {
    const prefix = nthWeek ? String(nthWeek) : ''
    const prefixedDay = d => `${prefix}${d}`
    parts.push(`BYDAY=${weekdays.map(prefixedDay).join(',')}`)
  }

  if (type === 'monthly' && monthDays.length > 0) {
    const dayNums = monthDays.filter(e => e.type === 'day').map(e => e.value)
    const nthDays = monthDays.filter(e => e.type === 'nth').map(e => `${e.nth}${e.weekday}`)
    if (dayNums.length > 0) parts.push(`BYMONTHDAY=${dayNums.join(',')}`)
    if (nthDays.length > 0) parts.push(`BYDAY=${nthDays.join(',')}`)
  }

  return parts.join(';')
}
