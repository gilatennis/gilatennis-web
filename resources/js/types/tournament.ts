export type TournamentStatus =
    | 'draft'
    | 'open'
    | 'closed'
    | 'completed'
    | 'canceled';

export const TOURNAMENT_STATUSES: readonly TournamentStatus[] = [
    'draft',
    'open',
    'closed',
    'completed',
    'canceled',
] as const;

export const TOURNAMENT_STATUS_LABELS: Record<TournamentStatus, string> = {
    draft: 'Draft',
    open: 'Open',
    closed: 'Closed',
    completed: 'Completed',
    canceled: 'Canceled',
};
