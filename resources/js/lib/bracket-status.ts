import type { BracketStatus } from '@/types';

export const bracketStatusLabel: Record<BracketStatus, string> = {
    draft: 'Draft',
    active: 'In Progress',
    completed: 'Completed',
};

export const bracketStatusClass: Record<BracketStatus, string> = {
    draft: 'bg-neutral-100 text-neutral-500 border-neutral-200',
    active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    completed: 'bg-blue-50 text-blue-700 border-blue-200',
};
