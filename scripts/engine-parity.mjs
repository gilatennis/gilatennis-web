import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

/*
 * Cross-language guard for the scoring engines. The PHP engine
 * (app/Services/TennisScoringEngine.php) is authoritative; the JS engine below
 * mirrors it for optimistic UI. Both replay the same shared vectors, so a drift
 * in either implementation fails `npm run test:engine`.
 *
 * The PHP side of this contract is tests/Unit/TennisScoringEngineParityTest.php.
 * Run with Node's built-in TypeScript type stripping (Node >= 23.6).
 */

import { scorePoint, setServer } from '../resources/js/lib/tennis-engine.ts';

const here = dirname(fileURLToPath(import.meta.url));
const vectors = JSON.parse(
    readFileSync(
        resolve(here, '../resources/js/lib/tennis-engine.vectors.json'),
        'utf8',
    ),
);

const SCORE_KEYS = [
    'server',
    'first_server',
    'set_a',
    'set_b',
    'game_a',
    'game_b',
    'tiebreak',
    'tb_a',
    'tb_b',
    'tb_first',
    'sets',
    'win',
];

function initialState(format, start) {
    return {
        server: 1,
        first_server: null,
        set_a: 0,
        set_b: 0,
        game_a: 0,
        game_b: 0,
        tiebreak: false,
        tb_a: 0,
        tb_b: 0,
        tb_first: null,
        sets: [],
        win: null,
        // MatchState fields the scoring math does not touch:
        id: 0,
        side_a_name: 'A',
        side_b_name: 'B',
        sets_to_win: format.sets_to_win,
        games_to_win: format.games_to_win,
        extend_at_tie: format.extend_at_tie,
        tiebreak_to: format.tiebreak_to,
        status: 'running',
        started_at: null,
        ended_at: null,
        created_at: null,
        updated_at: null,
        format_label: '',
        event_count: 0,
        pending_shot: null,
        fault: false,
        ...start,
    };
}

let failures = 0;

for (const vector of vectors) {
    let state = initialState(vector.format, vector.start ?? {});

    for (const event of vector.events) {
        state =
            event.type === 'server'
                ? setServer(state, event.side)
                : scorePoint(state, event.side);
    }

    for (const key of SCORE_KEYS) {
        try {
            assert.deepStrictEqual(state[key], vector.expected[key]);
        } catch {
            failures += 1;
            console.error(
                `✗ ${vector.name} → ${key}: expected ${JSON.stringify(vector.expected[key])}, got ${JSON.stringify(state[key])}`,
            );
        }
    }
}

if (failures > 0) {
    console.error(
        `\n${failures} mismatch(es) between the PHP and JS scoring engines.`,
    );
    process.exit(1);
}

console.log(
    `✓ ${vectors.length} scoring vectors match between the PHP and JS engines.`,
);
