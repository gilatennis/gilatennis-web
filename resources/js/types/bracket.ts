export type BracketStatus = 'draft' | 'active' | 'completed';

export type BracketMatchStatus =
    | 'pending'
    | 'running'
    | 'completed'
    | 'canceled';

export type SeedingMode = 'auto' | 'manual';

export interface BracketEntrant {
    id: number;
    name: string;
    team: { id: number; name: string } | null;
    players: { id: number; name: string }[];
}

export interface BracketMatchSummary {
    id: number;
    side_a_name: string;
    side_b_name: string;
    sets_to_win: number;
    games_to_win: number;
    extend_at_tie: boolean;
    tiebreak_to: number;
    status: BracketMatchStatus;
    format_label: string;
    winner_side: number | null;
}

export type RubberPayload = {
    side_a_name: string;
    side_b_name: string;
    sets_to_win: number;
    games_to_win: number;
    extend_at_tie: boolean;
    tiebreak_to: number;
};

export type AdvanceReason = 'walkover' | 'retirement' | 'disqualification';

export interface BracketSlot {
    id: number;
    round: number;
    position: number;
    seed: number | null;
    bye: boolean;
    label: string | null;
    entrant_a: BracketEntrant | null;
    entrant_b: BracketEntrant | null;
    winner_entrant: BracketEntrant | null;
    advance_reason: AdvanceReason | null;
    is_ready: boolean;
    is_complete: boolean;
    matches: BracketMatchSummary[];
}

export interface BracketRound {
    round: number;
    label: string | null;
    slots: BracketSlot[];
}

export interface GroupMatchday {
    round: number;
    slots: BracketSlot[];
}

export interface GroupStanding {
    entrant_id: number;
    name: string;
    played: number;
    wins: number;
    losses: number;
    sets_for: number;
    sets_against: number;
    games_for: number;
    games_against: number;
    set_diff: number;
    game_diff: number;
    rank: number;
}

export interface BracketGroup {
    id: number;
    name: string;
    position: number;
    advance_count: number;
    double_round_robin: boolean;
    winner_entrant_ids: number[];
    entrants: BracketEntrant[];
    matchdays: GroupMatchday[];
    standings: GroupStanding[];
}

export interface KnockoutBracket {
    id: number;
    type: 'single_elimination';
    status: BracketStatus;
    size: number;
    manual_assignment: boolean;
    draw_locked: boolean;
    eligible_entrants: BracketEntrantOption[];
    rounds: BracketRound[];
}

export interface GroupStageBracket {
    id: number;
    type: 'group_stage';
    status: BracketStatus;
    size: number;
    groups: BracketGroup[];
}

export interface BracketEntrantOption {
    id: number;
    name: string;
}
