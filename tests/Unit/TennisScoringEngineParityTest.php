<?php

use App\DTOs\TennisScoreState;
use App\Enums\TennisMatchEventType;
use App\Services\TennisScoringEngine;
use PHPUnit\Framework\Assert;

/*
 * Guards the two scoring engines against drift. The PHP engine
 * (App\Services\TennisScoringEngine) is authoritative; resources/js/lib/tennis-engine.ts
 * mirrors it for the optimistic admin UI. Both are driven from the shared
 * resources/js/lib/tennis-engine.vectors.json — scripts/engine-parity.mjs runs
 * the JS side against the same file (`npm run test:engine`).
 */

/**
 * @return list<array<string, mixed>>
 */
function scoringVectors(): array
{
    $path = dirname(__DIR__, 2).'/resources/js/lib/tennis-engine.vectors.json';

    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

it('replays the shared scoring vectors like the JS engine', function (array $vector) {
    $engine = new TennisScoringEngine;

    $state = array_merge(TennisScoreState::initial()->toArray(), $vector['start']);

    foreach ($vector['events'] as $event) {
        $state = $engine->applyEvent(
            $state,
            $vector['format'],
            TennisMatchEventType::from($event['type']),
            $event['side'],
        );
    }

    $actual = TennisScoreState::fromArray($state)->toArray();

    foreach ($vector['expected'] as $key => $value) {
        Assert::assertSame($value, $actual[$key], "vector '{$vector['name']}' field '{$key}'");
    }
})->with(array_map(fn (array $vector): array => [$vector], scoringVectors()));
