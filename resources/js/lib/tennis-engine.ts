export type Side = 1 | 2;

// The authoritative scoring rules live server-side in
// app/Services/TennisScoringEngine.php. This module mirrors them so the score
// board can update optimistically; every request is reconciled against the
// server projection afterwards, so a drift here can only be a transient render.
//
// TODO: mirror tests/Unit/TennisScoringEngineTest.php in JS to catch drift.
// Reference cases: displayGameScore deuce/Ad boundaries, scorePoint deuce reset
// at 4-4, advantage -> game, tiebreak transitions, and the statusLine branches
// (Deuce / Advantage / Set point / Match point / Tiebreak / Match won).

export type ShotTag = 'Ace' | 'Winner' | 'Unforced error' | 'Forced error';

export type EventTag = 'Fault' | 'Double fault' | 'Let';

export type MatchStatusValue = 'pending' | 'running' | 'completed' | 'canceled';

export interface SetScore {
    a: number;
    b: number;
}

export interface MatchScore {
    server: Side | null;
    first_server: Side | null;
    set_a: number;
    set_b: number;
    game_a: number;
    game_b: number;
    tiebreak: boolean;
    tb_a: number;
    tb_b: number;
    tb_first: Side | null;
    sets: SetScore[];
    win: Side | null;
}

export interface MatchState extends MatchScore {
    id: number;
    side_a_name: string;
    side_b_name: string;
    sets_to_win: number;
    games_to_win: number;
    extend_at_tie: boolean;
    tiebreak_to: number;
    status: MatchStatusValue;
    started_at: string | null;
    ended_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    format_label: string;
    event_count: number;
    pending_shot: ShotTag | EventTag | null;
    fault: boolean;
}

export type StatusTone = 'neutral' | 'amber' | 'emerald' | 'blue';

export interface StatusLine {
    text: string;
    tone: StatusTone;
}

const PTS = ['0', '15', '30', '40'] as const;

export function gamePointLabel(p: number): string {
    return PTS[p] ?? String(p);
}

export function gameOver(a: number, b: number, inTb: boolean): Side | null {
    if (inTb) {
        if (a >= 7 && a - b >= 2) {
            return 1;
        }

        if (b >= 7 && b - a >= 2) {
            return 2;
        }

        return null;
    }

    if (a >= 4 && a - b >= 2) {
        return 1;
    }

    if (b >= 4 && b - a >= 2) {
        return 2;
    }

    return null;
}

export function setOver(
    a: number,
    b: number,
    gamesToWin: number,
    extendAtTie: boolean,
): Side | null {
    if (extendAtTie) {
        if (a >= gamesToWin + 1 && a - b >= 2) {
            return 1;
        }

        if (b >= gamesToWin + 1 && b - a >= 2) {
            return 2;
        }

        return null;
    }

    if (a >= gamesToWin && a - b >= 2) {
        return 1;
    }

    if (b >= gamesToWin && b - a >= 2) {
        return 2;
    }

    return null;
}

export function tiebreakServer(total: number, firstSrv: Side): Side {
    if (total <= 1) {
        return firstSrv;
    }

    const alt: Side = firstSrv === 1 ? 2 : 1;
    const t = total - 1;
    const grp = Math.floor((t - 1) / 2);

    return grp % 2 === 0 ? alt : firstSrv;
}

function finishGame(state: MatchState, gw: Side): MatchState {
    const nextSetA = gw === 1 ? state.set_a + 1 : state.set_a;
    const nextSetB = gw === 2 ? state.set_b + 1 : state.set_b;
    let next: MatchState = {
        ...state,
        game_a: 0,
        game_b: 0,
        set_a: nextSetA,
        set_b: nextSetB,
    };

    const so = setOver(
        nextSetA,
        nextSetB,
        state.games_to_win,
        state.extend_at_tie,
    );

    if (so !== null) {
        next = finishSet(next);
    } else if (
        !state.extend_at_tie &&
        nextSetA === state.games_to_win &&
        nextSetB === state.games_to_win
    ) {
        const tbf: Side = state.server === 1 ? 2 : 1;
        next = {
            ...next,
            tiebreak: true,
            tb_a: 0,
            tb_b: 0,
            tb_first: tbf,
            server: tbf,
        };
    } else {
        next = { ...next, server: state.server === 1 ? 2 : 1 };
    }

    return next;
}

function finishSet(state: MatchState): MatchState {
    const newSets = [...state.sets, { a: state.set_a, b: state.set_b }];
    const next: MatchState = {
        ...state,
        sets: newSets,
        set_a: 0,
        set_b: 0,
        tiebreak: false,
        tb_a: 0,
        tb_b: 0,
    };

    const wonA = countSets(newSets, 1) >= state.sets_to_win;
    const wonB = countSets(newSets, 2) >= state.sets_to_win;

    if (wonA) {
        return { ...next, win: 1 };
    }

    if (wonB) {
        return { ...next, win: 2 };
    }

    return { ...next, server: state.server === 1 ? 2 : 1 };
}

function countSets(sets: SetScore[], side: Side): number {
    return sets.filter((s) => (side === 1 ? s.a > s.b : s.b > s.a)).length;
}

function finishTiebreakPoint(state: MatchState, who: Side): MatchState {
    const nextTbA = who === 1 ? state.tb_a + 1 : state.tb_a;
    const nextTbB = who === 2 ? state.tb_b + 1 : state.tb_b;
    const total = nextTbA + nextTbB;
    const nextServer = tiebreakServer(
        total,
        state.tb_first ?? state.server ?? 1,
    );
    let next: MatchState = {
        ...state,
        tb_a: nextTbA,
        tb_b: nextTbB,
        server: nextServer,
    };
    const gw = gameOver(nextTbA, nextTbB, true);

    if (gw !== null) {
        next = {
            ...next,
            set_a: gw === 1 ? state.games_to_win + 1 : state.set_a,
            set_b: gw === 2 ? state.games_to_win + 1 : state.set_b,
        };
        next = finishSet(next);
    }

    return next;
}

export function scorePoint(state: MatchState, who: Side): MatchState {
    if (state.win !== null) {
        return state;
    }

    const cleared: MatchState = {
        ...state,
        pending_shot: null,
        fault: false,
    };

    if (cleared.tiebreak) {
        return finishTiebreakPoint(cleared, who);
    }

    const nextA = who === 1 ? state.game_a + 1 : state.game_a;
    const nextB = who === 2 ? state.game_b + 1 : state.game_b;
    const gw = gameOver(nextA, nextB, false);

    if (gw !== null) {
        return finishGame(cleared, gw);
    }

    if (nextA === 4 && nextB === 4) {
        return { ...cleared, game_a: 3, game_b: 3 };
    }

    return { ...cleared, game_a: nextA, game_b: nextB };
}

export function applyEvent(state: MatchState, event: EventTag): MatchState {
    if (state.win !== null) {
        return state;
    }

    if (event === 'Fault') {
        return { ...state, fault: !state.fault, pending_shot: null };
    }

    if (event === 'Double fault') {
        const server: Side = state.server ?? 1;
        const receiver: Side = server === 1 ? 2 : 1;

        return scorePoint(
            { ...state, fault: false, pending_shot: 'Double fault' },
            receiver,
        );
    }

    const next: MatchState =
        state.pending_shot === 'Let'
            ? { ...state, pending_shot: null }
            : { ...state, pending_shot: 'Let' };

    return next;
}

export function tagShot(state: MatchState, label: ShotTag): MatchState {
    if (state.win !== null) {
        return state;
    }

    return {
        ...state,
        pending_shot: state.pending_shot === label ? null : label,
    };
}

export function setServer(state: MatchState, side: Side): MatchState {
    if (state.first_server !== null) {
        return state;
    }

    return { ...state, server: side, first_server: side };
}

export function isComplete(state: MatchState): boolean {
    return state.win !== null;
}

export function statusLine(state: MatchState): StatusLine {
    if (state.win !== null) {
        const winner = state.win === 1 ? state.side_a_name : state.side_b_name;

        return { text: `Match won · ${winner}`, tone: 'emerald' };
    }

    if (state.tiebreak) {
        return { text: 'Tiebreak', tone: 'blue' };
    }

    if (state.game_a >= 4 && state.game_a - state.game_b === 1) {
        return { text: `Advantage · ${state.side_a_name}`, tone: 'emerald' };
    }

    if (state.game_b >= 4 && state.game_b - state.game_a === 1) {
        return { text: `Advantage · ${state.side_b_name}`, tone: 'emerald' };
    }

    const deuce =
        state.game_a >= 3 && state.game_b >= 3 && state.game_a === state.game_b;

    if (deuce) {
        return { text: 'Deuce', tone: 'blue' };
    }

    const need = state.sets_to_win;
    const matchA = state.set_a >= need - 1;
    const matchB = state.set_b >= need - 1;

    if (state.game_a >= 3) {
        if (
            state.set_a === state.games_to_win - 1 &&
            state.game_a > state.game_b
        ) {
            return {
                text: `${matchA ? 'Match' : 'Set'} point · ${state.side_a_name}`,
                tone: 'amber',
            };
        }

        if (state.set_a === state.games_to_win && state.game_a > state.game_b) {
            return {
                text: `${matchA ? 'Match' : 'Set'} point · ${state.side_a_name}`,
                tone: 'amber',
            };
        }

        if (state.game_a > state.game_b) {
            return { text: `Game point · ${state.side_a_name}`, tone: 'amber' };
        }
    }

    if (state.game_b >= 3) {
        if (
            state.set_b === state.games_to_win - 1 &&
            state.game_b > state.game_a
        ) {
            return {
                text: `${matchB ? 'Match' : 'Set'} point · ${state.side_b_name}`,
                tone: 'amber',
            };
        }

        if (state.set_b === state.games_to_win && state.game_b > state.game_a) {
            return {
                text: `${matchB ? 'Match' : 'Set'} point · ${state.side_b_name}`,
                tone: 'amber',
            };
        }

        if (state.game_b > state.game_a) {
            return { text: `Game point · ${state.side_b_name}`, tone: 'amber' };
        }
    }

    const serverName =
        state.server === 1 ? state.side_a_name : state.side_b_name;

    return { text: `${serverName} serving`, tone: 'neutral' };
}

export function displayGameScore(state: MatchState, side: Side): string {
    if (state.tiebreak) {
        return String(side === 1 ? state.tb_a : state.tb_b);
    }

    const mine = side === 1 ? state.game_a : state.game_b;
    const opp = side === 1 ? state.game_b : state.game_a;

    if (mine >= 4 && mine > opp) {
        return 'Ad';
    }

    return gamePointLabel(mine);
}
