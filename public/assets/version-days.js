/*
 * Groups a Version history (newest first) by calendar day for display. The
 * day is taken in `timeZone` (the User's display timezone on the server
 * build), so a Version saved late in the evening isn't filed under the next
 * day just because it's already tomorrow in UTC.
 *
 * @param {{savedAt: string}[]} versions
 * @param {string} [timeZone]  IANA name; the browser's when omitted
 * @returns {{day: string, items: object[]}[]}  day as "YYYY-MM-DD"
 */
export function groupVersionsByDay(versions, timeZone) {
  // en-CA formats dates as YYYY-MM-DD.
  const dayOf = new Intl.DateTimeFormat('en-CA', {
    timeZone: timeZone || undefined,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  });

  const days = [];
  versions.forEach(version => {
    const day = dayOf.format(new Date(version.savedAt));
    const last = days[days.length - 1];

    if (last && last.day === day) {
      last.items.push(version);
    } else {
      days.push({ day, items: [version] });
    }
  });

  return days;
}
