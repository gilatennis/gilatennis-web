import type { MatchStatusValue, SetScore, Side } from '@/lib/tennis-engine';

export interface LiveDivision {
    id: number;
    name: string;
}

export interface LiveGroup {
    id: number;
    name: string;
    position: number;
}

export interface LiveRound {
    number: number;
    label: string | null;
}

export type LiveStage = 'group_stage' | 'knockout';

export interface LiveTie {
    slot_id: number;
    round: number;
    position: number;
    label: string | null;
    team_a_name: string | null;
    team_b_name: string | null;
}

/**
 * Public, spectator-safe view of a match (see App\Services\PublicMatchPresenter).
 * Carries the score projection but none of the admin-only fields.
 */
export interface LiveMatch {
    id: number;
    side_a_name: string;
    side_b_name: string;
    status: MatchStatusValue;
    format_label: string;
    sets_to_win: number;
    games_to_win: number;
    extend_at_tie: boolean;
    tiebreak_to: number;
    started_at: string | null;
    ended_at: string | null;
    updated_at: string | null;
    server: Side | null;
    set_a: number;
    set_b: number;
    game_a: number;
    game_b: number;
    tiebreak: boolean;
    tb_a: number;
    tb_b: number;
    sets: SetScore[];
    win: Side | null;
    division: LiveDivision | null;
    stage: LiveStage | null;
    group: LiveGroup | null;
    round: LiveRound | null;
    tie: LiveTie | null;
}
