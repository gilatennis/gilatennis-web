export function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '';
    }

    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function formatDateTime(date: string | null | undefined): string {
    if (!date) {
        return '';
    }

    const d = new Date(date);

    return (
        d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) +
        ' · ' +
        d.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        })
    );
}

export function formatAmount(cents: number, currency: string = 'IDR'): string {
    if (currency === 'IDR') {
        return `Rp ${(cents / 100).toLocaleString('id-ID')}`;
    }

    return `$${(cents / 100).toFixed(2)}`;
}
