export interface CalendarEvent {
    title: string;
    start: string;
    end: string;
    location?: string;
    description?: string;
}

function toGoogleDate(dateStr: string): string {
    return dateStr.replace(/-/g, '');
}

function addDay(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');
    date.setDate(date.getDate() + 1);
    return date.toISOString().split('T')[0];
}

export function googleCalendarUrl(event: CalendarEvent): string {
    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: event.title,
        dates: `${toGoogleDate(event.start)}/${toGoogleDate(addDay(event.end))}`,
    });
    if (event.description) params.set('details', event.description);
    if (event.location) params.set('location', event.location);
    return `https://calendar.google.com/calendar/render?${params.toString()}`;
}

export function outlookCalendarUrl(event: CalendarEvent): string {
    const params = new URLSearchParams({
        subject: event.title,
        startdt: event.start + 'T00:00:00',
        enddt: addDay(event.end) + 'T00:00:00',
        allday: 'true',
    });
    if (event.description) params.set('body', event.description);
    if (event.location) params.set('location', event.location);
    return `https://outlook.live.com/calendar/0/deeplink/compose?${params.toString()}`;
}

function esc(s: string): string {
    return s.replace(/[\\;,]/g, '\\$&').replace(/\n/g, '\\n');
}

export function downloadIcs(event: CalendarEvent): void {
    const startDate = toGoogleDate(event.start);
    const endDate = toGoogleDate(addDay(event.end));

    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Gilatennis//Tournament Registration//EN',
        'BEGIN:VEVENT',
        `DTSTART;VALUE=DATE:${startDate}`,
        `DTEND;VALUE=DATE:${endDate}`,
        `SUMMARY:${esc(event.title)}`,
    ];
    if (event.description) lines.push(`DESCRIPTION:${esc(event.description)}`);
    if (event.location) lines.push(`LOCATION:${esc(event.location)}`);
    lines.push('END:VEVENT', 'END:VCALENDAR');

    const blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${event.title.replace(/[^a-zA-Z0-9]/g, '_')}.ics`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
