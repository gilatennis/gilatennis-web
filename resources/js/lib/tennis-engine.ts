export type Side = 1 | 2;

export type ShotTag =
    | 'Ace'
    | 'Winner'
    | 'Unforced error'
    | 'Forced error';

export type EventTag = 'Fault' | 'Double fault' | 'Let';

export interface MatchFormat {
    setsToWin: number;
    gamesToWin: number;
    extendAtTie: boolean;
    tiebreakTo: number;
}

export interface SetScore {
    a: number;
    b: number;
}

export interface MatchState {
    format: MatchFormat;
    p1: string;
    p2: string;
    server: Side;
    firstServer: Side | null;
    setA: number;
    setB: number;
    gameA: number;
    gameB: number;
    tiebreak: boolean;
    tbA: number;
    tbB: number;
    tbFirst: Side | null;
    sets: SetScore[];
    win: Side | null;
    pendingShot: ShotTag | EventTag | null;
    fault: boolean;
    history: Snapshot[];
}

export interface Snapshot {
    server: Side;
    setA: number;
    setB: number;
    gameA: number;
    gameB: number;
    sets: SetScore[];
    tiebreak: boolean;
    tbA: number;
    tbB: number;
    tbFirst: Side | null;
    pendingShot: ShotTag | EventTag | null;
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

export function setOver(a: number, b: number, format: MatchFormat): Side | null {
    const target = format.gamesToWin;

    if (a >= target && a - b >= 2) {
        return 1;
    }

    if (b >= target && b - a >= 2) {
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

export function createInitialState(
    format: MatchFormat,
    p1: string,
    p2: string,
    firstServer: Side | null,
): MatchState {
    return {
        format,
        p1,
        p2,
        server: firstServer ?? 1,
        firstServer,
        setA: 0,
        setB: 0,
        gameA: 0,
        gameB: 0,
        tiebreak: false,
        tbA: 0,
        tbB: 0,
        tbFirst: null,
        sets: [],
        win: null,
        pendingShot: null,
        fault: false,
        history: [],
    };
}

function snap(state: MatchState): Snapshot {
    return {
        server: state.server,
        setA: state.setA,
        setB: state.setB,
        gameA: state.gameA,
        gameB: state.gameB,
        sets: state.sets.map((s) => ({ a: s.a, b: s.b })),
        tiebreak: state.tiebreak,
        tbA: state.tbA,
        tbB: state.tbB,
        tbFirst: state.tbFirst,
        pendingShot: state.pendingShot,
        fault: state.fault,
    };
}

function fromSnap(s: Snapshot, base: MatchState): MatchState {
    return {
        ...base,
        server: s.server,
        setA: s.setA,
        setB: s.setB,
        gameA: s.gameA,
        gameB: s.gameB,
        sets: s.sets.map((sc) => ({ a: sc.a, b: sc.b })),
        tiebreak: s.tiebreak,
        tbA: s.tbA,
        tbB: s.tbB,
        tbFirst: s.tbFirst,
        pendingShot: s.pendingShot,
        fault: s.fault,
        win: null,
    };
}

export function undo(state: MatchState): MatchState | null {
    if (state.history.length === 0) {
        return null;
    }

    const h = state.history[state.history.length - 1];
    const rest = state.history.slice(0, -1);

    return { ...fromSnap(h, state), history: rest };
}

function finishGame(state: MatchState, gw: Side): MatchState {
    const nextSetA = gw === 1 ? state.setA + 1 : state.setA;
    const nextSetB = gw === 2 ? state.setB + 1 : state.setB;
    let next: MatchState = {
        ...state,
        gameA: 0,
        gameB: 0,
        setA: nextSetA,
        setB: nextSetB,
    };

    const so = setOver(nextSetA, nextSetB, state.format);

    if (so !== null) {
        next = finishSet(next);
    } else if (
        !state.format.extendAtTie &&
        nextSetA === state.format.gamesToWin &&
        nextSetB === state.format.gamesToWin
    ) {
        const tbf: Side = state.server === 1 ? 2 : 1;
        next = {
            ...next,
            tiebreak: true,
            tbA: 0,
            tbB: 0,
            tbFirst: tbf,
            server: tbf,
        };
    } else {
        next = { ...next, server: state.server === 1 ? 2 : 1 };
    }

    return next;
}

function finishSet(state: MatchState): MatchState {
    const newSets = [...state.sets, { a: state.setA, b: state.setB }];
    const next: MatchState = {
        ...state,
        sets: newSets,
        setA: 0,
        setB: 0,
        tiebreak: false,
        tbA: 0,
        tbB: 0,
    };

    const wonA = countSets(newSets, 1) >= state.format.setsToWin;
    const wonB = countSets(newSets, 2) >= state.format.setsToWin;

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
    const nextTbA = who === 1 ? state.tbA + 1 : state.tbA;
    const nextTbB = who === 2 ? state.tbB + 1 : state.tbB;
    const total = nextTbA + nextTbB;
    const nextServer = tiebreakServer(total, state.tbFirst ?? state.server);
    let next: MatchState = {
        ...state,
        tbA: nextTbA,
        tbB: nextTbB,
        server: nextServer,
    };
    const gw = gameOver(nextTbA, nextTbB, true);

    if (gw !== null) {
        next = {
            ...next,
            setA: gw === 1 ? state.format.gamesToWin + 1 : state.setA,
            setB: gw === 2 ? state.format.gamesToWin + 1 : state.setB,
        };
        next = finishSet(next);
    }

    return next;
}

export function scorePoint(state: MatchState, who: Side): MatchState {
    if (state.win !== null) {
        return state;
    }

    const history = [...state.history, snap(state)];
    const cleared: MatchState = {
        ...state,
        history,
        pendingShot: null,
        fault: false,
    };

    if (cleared.tiebreak) {
        return finishTiebreakPoint(cleared, who);
    }

    const nextA = who === 1 ? state.gameA + 1 : state.gameA;
    const nextB = who === 2 ? state.gameB + 1 : state.gameB;
    const gw = gameOver(nextA, nextB, false);

    if (gw !== null) {
        return finishGame(cleared, gw);
    }

    if (nextA === 4 && nextB === 4) {
        return { ...cleared, gameA: 3, gameB: 3 };
    }

    return { ...cleared, gameA: nextA, gameB: nextB };
}

export function applyEvent(state: MatchState, event: EventTag): MatchState {
    if (state.win !== null) {
        return state;
    }

    if (event === 'Fault') {
        return { ...state, fault: !state.fault, pendingShot: null };
    }

    if (event === 'Double fault') {
        const receiver: Side = state.server === 1 ? 2 : 1;

        return scorePoint(
            { ...state, fault: false, pendingShot: 'Double fault' },
            receiver,
        );
    }

    const next: MatchState = state.pendingShot === 'Let'
        ? { ...state, pendingShot: null }
        : { ...state, pendingShot: 'Let' };

    return next;
}

export function tagShot(state: MatchState, label: ShotTag): MatchState {
    if (state.win !== null) {
        return state;
    }

    return {
        ...state,
        pendingShot: state.pendingShot === label ? null : label,
    };
}

export function setServer(state: MatchState, side: Side): MatchState {
    if (state.history.length > 0 || state.gameA > 0 || state.gameB > 0) {
        return state;
    }

    return { ...state, server: side, firstServer: side };
}

export function isComplete(state: MatchState): boolean {
    return state.win !== null;
}

export function statusLine(state: MatchState): StatusLine {
    if (state.tiebreak) {
        return { text: 'Tiebreak', tone: 'blue' };
    }

    if (state.gameA >= 4 && state.gameA - state.gameB === 1) {
        return { text: `Advantage · ${state.p1}`, tone: 'emerald' };
    }

    if (state.gameB >= 4 && state.gameB - state.gameA === 1) {
        return { text: `Advantage · ${state.p2}`, tone: 'emerald' };
    }

    const deuce = state.gameA >= 3 && state.gameB >= 3 && state.gameA === state.gameB;

    if (deuce) {
        return { text: 'Deuce', tone: 'blue' };
    }

    const need = state.format.setsToWin;
    const matchA = state.setA >= need - 1;
    const matchB = state.setB >= need - 1;

    if (state.gameA >= 3) {
        if (state.setA === state.format.gamesToWin - 1 && state.gameA > state.gameB) {
            return {
                text: `${matchA ? 'Match' : 'Set'} point · ${state.p1}`,
                tone: 'amber',
            };
        }

        if (state.setA === state.format.gamesToWin && state.gameA > state.gameB) {
            return {
                text: `${matchA ? 'Match' : 'Set'} point · ${state.p1}`,
                tone: 'amber',
            };
        }

        if (state.gameA > state.gameB) {
            return { text: `Game point · ${state.p1}`, tone: 'amber' };
        }
    }

    if (state.gameB >= 3) {
        if (state.setB === state.format.gamesToWin - 1 && state.gameB > state.gameA) {
            return {
                text: `${matchB ? 'Match' : 'Set'} point · ${state.p2}`,
                tone: 'amber',
            };
        }

        if (state.setB === state.format.gamesToWin && state.gameB > state.gameA) {
            return {
                text: `${matchB ? 'Match' : 'Set'} point · ${state.p2}`,
                tone: 'amber',
            };
        }

        if (state.gameB > state.gameA) {
            return { text: `Game point · ${state.p2}`, tone: 'amber' };
        }
    }

    return {
        text: state.server === 1 ? `${state.p1} serving` : `${state.p2} serving`,
        tone: 'neutral',
    };
}

export function displayGameScore(state: MatchState, side: Side): string {
    if (state.tiebreak) {
        return String(side === 1 ? state.tbA : state.tbB);
    }

    const mine = side === 1 ? state.gameA : state.gameB;
    const opp = side === 1 ? state.gameB : state.gameA;

    if (mine >= 4 && mine > opp) {
        return 'Ad';
    }

    return gamePointLabel(mine);
}

export function canUndo(state: MatchState): boolean {
    return state.history.length > 0 || state.firstServer !== null;
}
