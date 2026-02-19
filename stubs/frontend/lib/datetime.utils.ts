import { DateTime } from 'luxon';

/**
 * Returns the browser's current IANA timezone (e.g., "America/Los_Angeles").
 * Used as the default zone when interpreting local timestamps.
 */
export function getBrowserZone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/**
 * Get the current time formatted for an HTML `datetime-local` input.
 *
 * - Output is in the browser's local timezone
 * - No seconds/milliseconds and no timezone suffix
 * - Example: "2025-09-02T13:45"
 */
export function nowLocal(): string {
    return DateTime.local().toFormat("yyyy-LL-dd'T'HH:mm");
}

/**
 * Convert a `datetime-local` string (yyyy-LL-dd'T'HH:mm), interpreted in a specific
 * timezone (defaults to the browser's), into a canonical UTC ISO string with `Z`.
 *
 * Example:
 *   localToUtc('2025-09-02T13:00') → '2025-09-02T20:00:00.000Z' (for America/Los_Angeles, UTC-07)
 */
export function localToUtc(value: string, zone?: string): string {
    const z = zone ?? getBrowserZone();
    const dt = DateTime.fromFormat(value, "yyyy-LL-dd'T'HH:mm", { zone: z });
    return dt.isValid ? (dt.toUTC().toISO() ?? '') : '';
}

/**
 * Render a UTC ISO timestamp (with `Z`) in the viewer's local timezone using
 * a friendly format. Defaults to "MMM d, yyyy h:mm a".
 *
 * Example:
 *   utcToLocal('2025-09-02T20:00:00.000Z') → 'Sep 2, 2025 1:00 PM' (America/Los_Angeles)
 */
export function utcToLocal(isoUtc: string, format = 'MMM d, yyyy h:mm a'): string {
    if (!isoUtc) return '';
    const dt = DateTime.fromISO(isoUtc, { zone: 'utc' }).setZone(getBrowserZone());
    return dt.isValid ? dt.toFormat(format) : '';
}

/**
 * Format a date string (YYYY-MM-DD) or ISO timestamp to a localized date format.
 * Defaults to "MMM d, yyyy" (e.g., "Dec 15, 2024").
 *
 * Example:
 *   formatDate('2024-12-15') → 'Dec 15, 2024'
 *   formatDate('2024-12-15', 'MM/dd/yyyy') → '12/15/2024'
 */
export function formatDate(dateString: string, format = 'MMM d, yyyy'): string {
    if (!dateString) return '';
    const dt = DateTime.fromISO(dateString);
    return dt.isValid ? dt.toFormat(format) : '';
}

/**
 * Format a time string (HH:mm or HH:mm:ss) to 12-hour format with AM/PM.
 * Defaults to "h:mm a" (e.g., "1:30 PM").
 *
 * Example:
 *   formatTime('13:30') → '1:30 PM'
 *   formatTime('09:15') → '9:15 AM'
 *   formatTime('13:30', 'h:mm:ss a') → '1:30:00 PM'
 */
export function formatTime(timeString: string, format = 'h:mm a'): string {
    if (!timeString) return '';
    // Parse time string as today's date with that time
    const dt = DateTime.fromFormat(timeString, 'HH:mm');
    return dt.isValid ? dt.toFormat(format) : '';
}

export function getSlotDurationMinutes(startTime: string, endTime: string): number {
    const start = DateTime.fromFormat(startTime, 'HH:mm');
    const end = DateTime.fromFormat(endTime, 'HH:mm');
    return end.diff(start).as('minutes');
}
