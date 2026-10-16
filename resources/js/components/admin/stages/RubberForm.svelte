<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { Switch } from '@/components/ui/switch';
    import { show as showMatch } from '@/routes/admin/tennis-matches';
    import type {
        BracketMatchSummary,
        BracketSlot,
        RubberPayload,
    } from '@/types';

    type RubberType = 'singles' | 'doubles';

    let {
        slot,
        rubber = null,
        onSubmit,
        onCancel,
    }: {
        slot: BracketSlot;
        rubber?: BracketMatchSummary | null;
        onSubmit: (payload: RubberPayload) => void;
        onCancel: () => void;
    } = $props();

    const DEFAULTS: Record<
        RubberType,
        Omit<RubberPayload, 'side_a_name' | 'side_b_name'>
    > = {
        doubles: {
            sets_to_win: 1,
            games_to_win: 6,
            extend_at_tie: false,
            extend_tiebreak: false,
            sudden_death: true,
            tiebreak_to: 7,
        },
        singles: {
            sets_to_win: 1,
            games_to_win: 6,
            extend_at_tie: false,
            extend_tiebreak: false,
            sudden_death: true,
            tiebreak_to: 7,
        },
    };

    let rubberType = $state<RubberType>('doubles');
    let sideA1 = $state('');
    let sideA2 = $state('');
    let sideB1 = $state('');
    let sideB2 = $state('');
    let setsToWin = $state(DEFAULTS.doubles.sets_to_win);
    let gamesToWin = $state(DEFAULTS.doubles.games_to_win);
    let extendAtTie = $state(DEFAULTS.doubles.extend_at_tie);
    let extendTiebreak = $state(DEFAULTS.doubles.extend_tiebreak);
    let suddenDeath = $state(DEFAULTS.doubles.sudden_death);
    let tiebreakTo = $state(DEFAULTS.doubles.tiebreak_to);

    const playersA = $derived(slot.entrant_a?.players ?? []);
    const playersB = $derived(slot.entrant_b?.players ?? []);

    const isDoubles = $derived(rubberType === 'doubles');
    const isEditing = $derived(rubber !== null);

    function splitNames(value: string): string[] {
        return value
            .split(' / ')
            .map((part) => part.trim())
            .filter(Boolean);
    }

    function playerNames(
        players: { id: number; name: string }[],
        current: string,
        partner: string,
    ): string[] {
        const names = players
            .map((player) => player.name)
            .filter((name) => name !== partner);

        if (current && !names.includes(current)) {
            return [current, ...names];
        }

        return names;
    }

    function applyTypeDefaults(type: RubberType) {
        const defaults = DEFAULTS[type];
        setsToWin = defaults.sets_to_win;
        gamesToWin = defaults.games_to_win;
        extendAtTie = defaults.extend_at_tie;
        extendTiebreak = defaults.extend_tiebreak;
        suddenDeath = defaults.sudden_death;
        tiebreakTo = defaults.tiebreak_to;
    }

    function selectType(type: RubberType) {
        rubberType = type;

        if (!isEditing) {
            applyTypeDefaults(type);
        }
    }

    $effect(() => {
        if (rubber) {
            const a = splitNames(rubber.side_a_name);
            const b = splitNames(rubber.side_b_name);

            rubberType = a.length > 1 || b.length > 1 ? 'doubles' : 'singles';
            sideA1 = a[0] ?? '';
            sideA2 = a[1] ?? '';
            sideB1 = b[0] ?? '';
            sideB2 = b[1] ?? '';
            setsToWin = rubber.sets_to_win;
            gamesToWin = rubber.games_to_win;
            extendAtTie = rubber.extend_at_tie;
            extendTiebreak = rubber.extend_tiebreak;
            suddenDeath = rubber.sudden_death;
            tiebreakTo = rubber.tiebreak_to;

            return;
        }

        rubberType = 'doubles';
        applyTypeDefaults('doubles');

        sideA1 = playersA[0]?.name ?? slot.entrant_a?.name ?? '';
        sideA2 = playersA[1]?.name ?? '';
        sideB1 = playersB[0]?.name ?? slot.entrant_b?.name ?? '';
        sideB2 = playersB[1]?.name ?? '';
    });

    const canSubmit = $derived(
        isDoubles ? sideA1 && sideA2 && sideB1 && sideB2 : sideA1 && sideB1,
    );

    function submit() {
        if (!canSubmit) {
            return;
        }

        onSubmit({
            side_a_name: isDoubles ? `${sideA1} / ${sideA2}` : sideA1,
            side_b_name: isDoubles ? `${sideB1} / ${sideB2}` : sideB1,
            sets_to_win: setsToWin,
            games_to_win: gamesToWin,
            extend_at_tie: extendAtTie,
            extend_tiebreak: extendTiebreak,
            sudden_death: suddenDeath,
            tiebreak_to: tiebreakTo,
        });
    }

    const inputClass =
        'w-full h-9 px-3 border border-black/12 bg-white text-[13px] outline-none focus:border-black/35 transition-colors';

    const tabClass = (active: boolean) =>
        `h-8 px-4 text-[11px] font-bold uppercase tracking-[0.12em] transition-colors ${
            active
                ? 'bg-neutral-900 text-white'
                : 'text-neutral-500 hover:text-neutral-800'
        }`;
</script>

{#snippet playerField(
    value: string,
    names: string[],
    setValue: (next: string) => void,
)}
    {#if names.length}
        <select
            {value}
            onchange={(e) => setValue(e.currentTarget.value)}
            class={inputClass}
        >
            {#each names as name, index (index)}
                <option value={name}>{name}</option>
            {/each}
        </select>
    {:else}
        <input
            {value}
            oninput={(e) => setValue(e.currentTarget.value)}
            class={inputClass}
        />
    {/if}
{/snippet}

<div class="mt-4 inline-flex border border-black/12">
    <button
        type="button"
        data-testid="rubber-type-singles"
        class={tabClass(!isDoubles)}
        onclick={() => selectType('singles')}
    >
        Singles
    </button>
    <button
        type="button"
        data-testid="rubber-type-doubles"
        class={tabClass(isDoubles)}
        onclick={() => selectType('doubles')}
    >
        Doubles
    </button>
</div>

<div class="mt-4 grid grid-cols-2 gap-4">
    <div>
        <div
            class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2 truncate"
        >
            {slot.entrant_a?.name}
        </div>
        {@render playerField(
            sideA1,
            playerNames(playersA, sideA1, sideA2),
            (v) => (sideA1 = v),
        )}
        {#if isDoubles}
            <div class="mt-2">
                {@render playerField(
                    sideA2,
                    playerNames(playersA, sideA2, sideA1),
                    (v) => (sideA2 = v),
                )}
            </div>
        {/if}
    </div>
    <div>
        <div
            class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2 truncate"
        >
            {slot.entrant_b?.name}
        </div>
        {@render playerField(
            sideB1,
            playerNames(playersB, sideB1, sideB2),
            (v) => (sideB1 = v),
        )}
        {#if isDoubles}
            <div class="mt-2">
                {@render playerField(
                    sideB2,
                    playerNames(playersB, sideB2, sideB1),
                    (v) => (sideB2 = v),
                )}
            </div>
        {/if}
    </div>
</div>

<div class="mt-4 border-t border-black/6 pt-4">
    <div
        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-2"
    >
        Match format
    </div>
    <div class="grid grid-cols-3 gap-3">
        <label class="flex flex-col gap-1">
            <span class="text-[10px] text-neutral-500">Sets to win</span>
            <select
                bind:value={setsToWin}
                class={inputClass}
                data-testid="rubber-sets-to-win"
            >
                <option value={1}>1</option>
                <option value={2}>2</option>
                <option value={3}>3</option>
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-[10px] text-neutral-500">Games to win</span>
            <select
                bind:value={gamesToWin}
                class={inputClass}
                data-testid="rubber-games-to-win"
            >
                <option value={4}>4</option>
                <option value={6}>6</option>
                <option value={8}>8</option>
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-[10px] text-neutral-500">Tiebreak to</span>
            <select bind:value={tiebreakTo} class={inputClass}>
                <option value={7}>7</option>
                <option value={10}>10</option>
            </select>
        </label>
    </div>
    <div class="mt-3 space-y-3">
        <div>
            <Label
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                >Extend at tie</Label
            >
            <div class="h-9 flex items-center gap-3">
                <Switch
                    bind:checked={extendAtTie}
                    data-testid="rubber-extend-at-tie"
                />
                <span class="text-[12px] text-neutral-500">
                    {extendAtTie
                        ? 'Play one more game at tie, then tiebreak'
                        : 'Tiebreak at games per set'}
                </span>
            </div>
        </div>
        <div>
            <Label
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                >Extend at tie for tiebreak</Label
            >
            <div class="h-9 flex items-center gap-3">
                <Switch
                    bind:checked={extendTiebreak}
                    data-testid="rubber-extend-tiebreak"
                />
                <span class="text-[12px] text-neutral-500">
                    {extendTiebreak
                        ? 'Win by two points in the tiebreak'
                        : 'First to the tiebreak target wins'}
                </span>
            </div>
        </div>
        <div>
            <Label
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                >Sudden death</Label
            >
            <div class="h-9 flex items-center gap-3">
                <Switch
                    bind:checked={suddenDeath}
                    data-testid="rubber-sudden-death"
                />
                <span class="text-[12px] text-neutral-500">
                    {suddenDeath
                        ? 'No deuce — first to 4 points wins the game'
                        : 'Deuce and advantage in every game'}
                </span>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
    {#if rubber}
        <Link
            href={showMatch({ match: rubber.id }).url}
            data-testid="rubber-score-link"
            class="h-9 px-4 inline-flex items-center justify-center gap-2 border border-black/12 text-[11px] font-bold uppercase tracking-[0.16em] text-coral hover:border-coral/40 transition-colors sm:mr-auto"
        >
            Score
        </Link>
    {/if}
    <Button variant="outline" onclick={onCancel} data-testid="rubber-cancel"
        >Cancel</Button
    >
    <Button
        variant="coral"
        disabled={!canSubmit}
        onclick={submit}
        data-testid="rubber-submit"
    >
        {isEditing ? 'Save' : 'Add Rubber'}
    </Button>
</div>
