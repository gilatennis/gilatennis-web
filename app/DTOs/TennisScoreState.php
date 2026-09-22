<?php

namespace App\DTOs;

/**
 * Immutable projection of a tennis match score, derived from the match's event log.
 */
final readonly class TennisScoreState
{
    /**
     * @param  list<array{a: int, b: int}>  $sets
     */
    public function __construct(
        public ?int $server,
        public ?int $firstServer,
        public int $setA,
        public int $setB,
        public int $gameA,
        public int $gameB,
        public bool $tiebreak,
        public int $tbA,
        public int $tbB,
        public ?int $tbFirst,
        public array $sets,
        public ?int $win,
    ) {}

    public static function initial(): self
    {
        return new self(
            server: 1,
            firstServer: null,
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
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromArray(array $state): self
    {
        return new self(
            server: isset($state['server']) ? (int) $state['server'] : null,
            firstServer: isset($state['first_server']) ? (int) $state['first_server'] : null,
            setA: (int) ($state['set_a'] ?? 0),
            setB: (int) ($state['set_b'] ?? 0),
            gameA: (int) ($state['game_a'] ?? 0),
            gameB: (int) ($state['game_b'] ?? 0),
            tiebreak: (bool) ($state['tiebreak'] ?? false),
            tbA: (int) ($state['tb_a'] ?? 0),
            tbB: (int) ($state['tb_b'] ?? 0),
            tbFirst: isset($state['tb_first']) ? (int) $state['tb_first'] : null,
            sets: array_values(array_map(
                fn (array $set): array => ['a' => (int) $set['a'], 'b' => (int) $set['b']],
                $state['sets'] ?? [],
            )),
            win: isset($state['win']) ? (int) $state['win'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'server' => $this->server,
            'first_server' => $this->firstServer,
            'set_a' => $this->setA,
            'set_b' => $this->setB,
            'game_a' => $this->gameA,
            'game_b' => $this->gameB,
            'tiebreak' => $this->tiebreak,
            'tb_a' => $this->tbA,
            'tb_b' => $this->tbB,
            'tb_first' => $this->tbFirst,
            'sets' => $this->sets,
            'win' => $this->win,
        ];
    }
}
