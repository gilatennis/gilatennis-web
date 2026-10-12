import type { LiveDivision, LiveMatch, LiveTie } from '@/types';

/*
 * Client-side grouping for the public live board. The server returns a flat list
 * ordered newest-first within each division (divisions keep their sort order, so
 * a division's stages and buckets follow their newest match); these helpers only
 * rearrange it into the sections the board renders, preserving that order.
 */

export interface BoardBucket {
    key: string;
    label: string;
    matches: LiveMatch[];
}

export interface BoardStage {
    key: string;
    label: string;
    buckets: BoardBucket[];
}

export interface BoardDivision {
    id: number | null;
    name: string;
    stages: BoardStage[];
}

export interface TieGroup {
    key: string;
    tie: LiveTie | null;
    matches: LiveMatch[];
}

/** A stable, attribute-safe slug for section testids (`Group A` → `group-a`). */
export function slug(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function stageRank(match: LiveMatch): number {
    if (match.stage === 'group_stage') {
        return 0;
    }

    if (match.stage === 'knockout') {
        return 1;
    }

    return 2;
}

function stageLabel(match: LiveMatch): string {
    if (match.stage === 'group_stage') {
        return 'Group stage';
    }

    if (match.stage === 'knockout') {
        return 'Knockout';
    }

    return 'Matches';
}

function bucketLabel(match: LiveMatch): string {
    if (match.stage === 'group_stage') {
        return match.group?.name ?? 'Group';
    }

    if (match.stage === 'knockout') {
        return (
            match.round?.label ??
            (match.round ? `Round ${match.round.number}` : 'Knockout')
        );
    }

    return 'Matches';
}

/** Mirrors App\Services\LiveScoreService::bucketKey(). */
export function bucketKey(match: LiveMatch): string {
    const divisionId = match.division?.id ?? 0;

    if (match.stage === 'group_stage') {
        return `g:${divisionId}:${match.group?.id ?? 0}`;
    }

    if (match.stage === 'knockout') {
        return `k:${divisionId}:${match.round?.number ?? 0}`;
    }

    return `m:${divisionId}`;
}

/**
 * A one-line location for a match card: `Men's Singles · Group A · Matchday 2`
 * or `Men's Singles · Semifinal`. Null for a slot-less match with no division.
 */
export function contextLabel(match: LiveMatch): string | null {
    const parts: string[] = [];

    if (match.division?.name) {
        parts.push(match.division.name);
    }

    if (match.stage === 'group_stage') {
        if (match.group?.name) {
            parts.push(match.group.name);
        }

        if (match.round?.number) {
            parts.push(`Matchday ${match.round.number}`);
        }
    } else if (match.stage === 'knockout') {
        parts.push(bucketLabel(match));
    }

    return parts.length > 0 ? parts.join(' · ') : null;
}

/** Group a flat, ordered list of results into division → stage → bucket. */
export function groupResults(matches: LiveMatch[]): BoardDivision[] {
    const divisions = new Map<string, BoardDivision>();
    const stages = new Map<string, BoardStage>();
    const buckets = new Map<string, BoardBucket>();

    for (const match of matches) {
        const divisionId = match.division?.id ?? null;
        const divisionKey = String(divisionId ?? 'none');

        let division = divisions.get(divisionKey);

        if (!division) {
            division = {
                id: divisionId,
                name: match.division?.name ?? 'Other',
                stages: [],
            };
            divisions.set(divisionKey, division);
        }

        const stageKey = `${divisionKey}:${stageRank(match)}`;
        let stage = stages.get(stageKey);

        if (!stage) {
            stage = { key: stageKey, label: stageLabel(match), buckets: [] };
            stages.set(stageKey, stage);
            division.stages.push(stage);
        }

        const key = bucketKey(match);
        let bucket = buckets.get(key);

        if (!bucket) {
            bucket = { key, label: bucketLabel(match), matches: [] };
            buckets.set(key, bucket);
            stage.buckets.push(bucket);
        }

        bucket.matches.push(match);
    }

    return [...divisions.values()];
}

/** Group a bucket's matches by their bracket tie, keeping rubbers together. */
export function groupTies(matches: LiveMatch[]): TieGroup[] {
    const groups: TieGroup[] = [];
    const bySlot = new Map<number, TieGroup>();

    for (const match of matches) {
        const slotId = match.tie?.slot_id;

        if (slotId === undefined) {
            groups.push({
                key: `match:${match.id}`,
                tie: null,
                matches: [match],
            });
            continue;
        }

        let group = bySlot.get(slotId);

        if (!group) {
            group = { key: `tie:${slotId}`, tie: match.tie, matches: [] };
            bySlot.set(slotId, group);
            groups.push(group);
        }

        group.matches.push(match);
    }

    return groups;
}

export function divisionTabs(
    divisions: LiveDivision[],
): { id: number | 'all'; name: string }[] {
    return [
        { id: 'all', name: 'All' },
        ...divisions.map((division) => ({
            id: division.id,
            name: division.name,
        })),
    ];
}
