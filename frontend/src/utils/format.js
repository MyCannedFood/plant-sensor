/*
 * Presentation helpers. The API sends raw numbers and ISO timestamps; this is
 * the only place they are turned into text.
 */

export function formatValue(value, decimals = 0) {
    if (value === null || value === undefined) {
        return '—';
    }

    return Number(value).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const UNITS = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['week', 604_800],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
    ['second', 1],
];

export function timeAgo(iso, now = Date.now()) {
    if (!iso) {
        return 'never';
    }

    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);

    if (Math.abs(seconds) < 10) {
        return 'just now';
    }

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size || unit === 'second') {
            return relative.format(Math.round(seconds / size), unit);
        }
    }
}

export function clockTime(iso) {
    return new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

export function dayLabel(iso) {
    const date = new Date(iso);
    const today = new Date();

    if (date.toDateString() === today.toDateString()) {
        return 'today';
    }

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

export function alertMessage(alert) {
    const above = alert.direction === 'above_max';

    return `${alert.label} at ${formatValue(alert.value, alert.decimals)} ${alert.unit} moved ${above ? 'above' : 'below'} the ${above ? 'maximum' : 'minimum'} threshold`;
}

export function band(threshold, decimals) {
    if (!threshold || threshold.min == null || threshold.max == null) {
        return 'not set';
    }

    return `${formatValue(threshold.min, decimals)}–${formatValue(threshold.max, decimals)}`;
}

export function plural(count, one, many) {
    return `${count} ${count === 1 ? one : many}`;
}
