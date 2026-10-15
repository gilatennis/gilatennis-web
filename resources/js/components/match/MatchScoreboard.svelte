<script lang="ts">
    import { Link, useHttp } from '@inertiajs/svelte';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Trophy from 'lucide-svelte/icons/trophy';
    import MatchStatusBadge from '@/components/admin/MatchStatusBadge.svelte';
    import {
        applyEvent,
        displayGameScore,
        isComplete,
        scorePoint,
        setServer,
        statusLine,
        tagShot,
    } from '@/lib/tennis-engine';
    import type {
        EventTag,
        MatchState,
        ShotTag,
        Side,
    } from '@/lib/tennis-engine';

    let {
        match,
        endpoints,
        bracketHref = null,
        listHref = null,
    }: {
        match: MatchState;
        endpoints: {
            point: string;
            server: string;
            undo: string;
            status: string;
        };
        bracketHref?: string | null;
        listHref?: string | null;
    } = $props();

    let score = $state<MatchState>({
        pending_shot: null,
        fault: false,
    } as MatchState);
    let selectedSide: Side | null = $state(null);
    let lastSavedAt: Date | null = $state(null);
    let saving = $state(false);
    let saveError = $state<string | null>(null);

    $effect.pre(() => {
        score = {
            ...match,
            pending_shot: null,
            fault: false,
        };
        selectedSide = match.first_server !== null ? match.server : null;
    });

    const http = useHttp<
        { side: number | null; shot: string | null },
        { match: MatchState }
    >({
        side: null,
        shot: null,
    });

    const statusHttp = useHttp<
        { status: 'running' | 'completed' },
        { match: MatchState }
    >({
        status: 'running',
    });

    let decided = $derived(isComplete(score));
    let isCompleted = $derived(score.status === 'completed');
    let undoable = $derived(score.event_count > 0);
    let statusBanner = $derived(statusLine(score));
    let showShotBar = $state(true);
    let showEventBar = $state(true);

    const shots: ShotTag[] = [
        'Ace',
        'Winner',
        'Unforced error',
        'Forced error',
    ];
    const events: EventTag[] = ['Fault', 'Double fault']; // 'Let' hidden for now

    function isShotTag(value: MatchState['pending_shot']): value is ShotTag {
        return value !== null && (shots as string[]).includes(value);
    }

    function reconcile(next: MatchState) {
        score = {
            ...score,
            ...next,
            pending_shot: null,
            fault: false,
        };
        selectedSide = score.first_server !== null ? score.server : null;
        lastSavedAt = new Date();
    }

    function firstError(errors: Record<string, unknown> | null): string | null {
        for (const value of Object.values(errors ?? {})) {
            const message = Array.isArray(value) ? value[0] : value;

            if (typeof message === 'string' && message.length > 0) {
                return message;
            }
        }

        return null;
    }

    async function persist(
        url: string,
        payload: { side?: number | null; shot?: string | null },
        previous: MatchState,
    ) {
        saving = true;
        saveError = null;
        http.setStore({
            side: payload.side ?? null,
            shot: payload.shot ?? null,
        });

        try {
            const res = await http.post(url);

            if (res?.match) {
                reconcile(res.match);
            }
        } catch {
            score = previous;
            selectedSide = score.first_server !== null ? score.server : null;
            saveError =
                firstError(http.errors) ?? 'Could not save the score. Retry.';
        } finally {
            saving = false;
        }
    }

    function pickServer(side: Side) {
        selectedSide = side;
    }

    function startMatch() {
        if (selectedSide === null || saving) {
            return;
        }

        const previous = score;

        score = { ...setServer(score, selectedSide), status: 'running' };
        persist(endpoints.server, { side: selectedSide }, previous);
    }

    function awardPoint(side: Side) {
        if (decided || saving) {
            return;
        }

        const previous = score;

        const shot = isShotTag(score.pending_shot) ? score.pending_shot : null;
        const next = scorePoint(score, side);

        score = {
            ...next,
            status: 'running',
            pending_shot: null,
            fault: false,
        };

        persist(endpoints.point, { side, shot }, previous);
    }

    function rollback() {
        if (!undoable || saving) {
            return;
        }

        persist(endpoints.undo, {}, score);
    }

    async function setStatus(status: 'running' | 'completed') {
        if (saving) {
            return;
        }

        const previous = score;

        saving = true;
        saveError = null;
        score = { ...score, status };

        try {
            statusHttp.setStore({ status });
            const res = await statusHttp.post(endpoints.status);

            if (res?.match) {
                reconcile(res.match);
            }
        } catch {
            score = previous;
            selectedSide = score.first_server !== null ? score.server : null;
            saveError =
                firstError(statusHttp.errors) ??
                'Could not update the match status. Retry.';
        } finally {
            saving = false;
        }
    }

    function tag(label: ShotTag) {
        score = tagShot(score, label);
    }

    function fireEvent(label: EventTag) {
        if (label === 'Double fault') {
            if (!score.fault || decided || saving) {
                return;
            }

            const previous = score;
            const receiver: Side = (score.server ?? 1) === 1 ? 2 : 1;
            const next = applyEvent({ ...score, fault: false }, 'Double fault');

            score = {
                ...next,
                status: 'running',
                pending_shot: null,
                fault: false,
            };

            persist(
                endpoints.point,
                {
                    side: receiver,
                    shot: 'Double fault',
                },
                previous,
            );

            return;
        }

        if (label === 'Fault') {
            if (!score.fault) {
                score = { ...score, fault: true, pending_shot: null };
            } else {
                fireEvent('Double fault');
            }

            return;
        }

        score = applyEvent(score, label);
    }

    function playerColor(side: Side) {
        return side === 1
            ? {
                  dot: 'bg-emerald-600',
                  soft: 'bg-emerald-50',
                  border: 'border-emerald-200',
                  text: 'text-emerald-700',
              }
            : {
                  dot: 'bg-sky-600',
                  soft: 'bg-sky-50',
                  border: 'border-sky-200',
                  text: 'text-sky-700',
              };
    }

    function formatDate(iso: string | null): string {
        if (!iso) {
            return '—';
        }

        const d = new Date(iso);

        return d.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    function formatTime(d: Date): string {
        const pad = (n: number) => String(n).padStart(2, '0');

        return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    function splitSideNames(name: string): string[] {
        return name
            .split(/\s*\/\s*/)
            .map((part) => part.trim())
            .filter(Boolean);
    }

    const statusToneClass: Record<string, string> = {
        neutral: 'text-neutral-500',
        blue: 'text-sky-600',
        emerald: 'text-emerald-700',
        amber: 'text-amber-600',
    };
    let statusTone = $derived(
        statusToneClass[statusBanner.tone] ?? 'text-neutral-500',
    );
</script>

{#snippet sideName(name: string)}
    {@const parts = splitSideNames(name)}
    {#if parts.length > 1}
        <span class="block">{parts[0]}</span>
        <span class="block">/ {parts[1]}</span>
    {:else}
        {name}
    {/if}
{/snippet}

{#snippet winnerPanel()}
    <div
        class="bg-white border border-black/10 flex flex-col items-center text-center px-6 py-12 gap-4"
    >
        <Trophy
            class="size-12 {score.win === 1
                ? 'text-emerald-600'
                : score.win === 2
                  ? 'text-sky-600'
                  : 'text-neutral-400'}"
        />
        {#if score.win === null}
            <div data-testid="match-completed">
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400"
                >
                    Status
                </div>
                <div
                    class="mt-2 font-display font-black text-[16px] sm:text-[28px] md:text-[28px] tracking-[-0.03em] text-neutral-700"
                >
                    Match completed
                </div>
                <p class="mt-2 text-[12px] text-neutral-500">
                    No winner was recorded for this match.
                </p>
            </div>
        {:else}
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400"
                >
                    Match Winner
                </div>
                <div
                    data-testid="match-winner"
                    class="mt-2 font-display font-black text-[16px] sm:text-[28px] md:text-[28px] tracking-[-0.03em] {score.win ===
                    1
                        ? 'text-emerald-700'
                        : 'text-sky-700'}"
                >
                    {@render sideName(
                        score.win === 1 ? match.side_a_name : match.side_b_name,
                    )}
                </div>
            </div>
        {/if}

        <div class="w-full max-w-md border border-black/10 mt-4">
            {#each score.sets as set, i (i)}
                {@const aWin = set.a > set.b}
                {@const bWin = set.b > set.a}
                <div
                    class="flex items-center justify-between px-4 py-3 bg-neutral-50 border-b border-black/10 last:border-b-0"
                >
                    <span class="text-[12px] font-medium flex-1 text-left">
                        {match.side_a_name}
                    </span>
                    <span
                        class="font-mono text-[14px] font-bold tabular-nums min-w-[24px] text-center {aWin
                            ? 'text-emerald-700'
                            : ''}"
                        data-testid={`final-set-${i}-a`}
                    >
                        {set.a}
                    </span>
                    <span class="text-neutral-400 min-w-[8px] text-center"
                        >–</span
                    >
                    <span
                        class="font-mono text-[14px] font-bold tabular-nums min-w-[24px] text-center {bWin
                            ? 'text-sky-700'
                            : ''}"
                        data-testid={`final-set-${i}-b`}
                    >
                        {set.b}
                    </span>
                    <span class="text-[12px] font-medium flex-1 text-right">
                        {match.side_b_name}
                    </span>
                </div>
            {/each}
        </div>
    </div>
{/snippet}

{#snippet bracketBackButton()}
    <Link
        href={bracketHref ?? '#'}
        data-testid="back-to-brackets"
        class="px-5 py-2 border border-black/10 bg-white text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-700 hover:bg-neutral-50 transition-colors"
    >
        Back to brackets
    </Link>
{/snippet}

{#snippet matchesBackButton()}
    <Link
        href={listHref ?? '#'}
        class="px-5 py-2 border border-black/10 bg-white text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-700 hover:bg-neutral-50 transition-colors"
    >
        Back to matches
    </Link>
{/snippet}

{#snippet winnerActions()}
    <div class="flex flex-wrap gap-2 justify-center mt-2">
        {#if isCompleted}
            <button
                type="button"
                data-testid="reopen-match"
                onclick={() => setStatus('running')}
                class="px-5 py-2 border border-amber-300 bg-amber-50 text-[11px] font-bold uppercase tracking-[0.12em] text-amber-800 hover:bg-amber-100 transition-colors"
            >
                Reopen match
            </button>
        {:else}
            <button
                type="button"
                data-testid="mark-completed"
                onclick={() => setStatus('completed')}
                class="px-5 py-2 bg-emerald-600 text-white text-[11px] font-bold uppercase tracking-[0.12em] hover:bg-emerald-700 transition-colors"
            >
                Mark as completed
            </button>
        {/if}
        {#if bracketHref}
            {@render bracketBackButton()}
        {/if}
        {#if listHref}
            {@render matchesBackButton()}
        {/if}
    </div>
{/snippet}

<div class="space-y-2 mb-4 w-full">
    <h1
        data-testid="match-heading"
        class="font-display font-black text-[16px] sm:text-[28px] md:text-[28px] tracking-[-0.025em] leading-tight w-full"
    >
        <div class="flex space-x-8">
            <div class="">{@render sideName(match.side_a_name)}</div>
            <span class="text-neutral-300">vs</span>
            <div class="">{@render sideName(match.side_b_name)}</div>
        </div>
    </h1>
    <div>
        <div class="flex items-center space-x-2">
            <span class=""><MatchStatusBadge status={score.status} /></span>
            <p class="pt-1 text-[13px] text-neutral-500">
                {#if score.started_at}
                    Started {formatDate(score.started_at)}
                {:else}
                    Not started yet
                {/if}
            </p>
        </div>
        <div class="h-2">
            <p class="pt-1 text-[11px] text-neutral-400">
                {#if saveError}
                    <span data-testid="save-error" class="text-red-600"
                        >{saveError}</span
                    >
                {:else if saving}
                    Saving…
                {:else if lastSavedAt}
                    Autosaved at {formatTime(lastSavedAt)}
                {/if}
            </p>
        </div>
    </div>
</div>

{#if bracketHref && !decided && !isCompleted}
    <div class="flex mt-2">
        {@render bracketBackButton()}
    </div>
{/if}

{#if !isCompleted}
    <p class="mt-2 mb-2 text-[13px] text-neutral-500">{match.format_label}</p>
    {#if decided}
        <div class="mb-4">
            {@render winnerPanel()}
            {@render winnerActions()}
            <p class="mt-3 text-[12px] text-neutral-500 text-center">
                Undo the last point to correct the score, or mark the match
                completed.
            </p>
        </div>
    {/if}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 flex flex-col gap-4">
            <div class="bg-white border border-black/10">
                {#each [{ side: 1 as Side, name: match.side_a_name }, { side: 2 as Side, name: match.side_b_name }] as row (row.side)}
                    {@const colors = playerColor(row.side)}
                    {@const serving =
                        score.first_server !== null &&
                        score.server === row.side}
                    <div
                        class="flex items-center gap-4 px-3 py-3 border-b border-black/10 last:border-b-0 {serving
                            ? colors.soft
                            : 'bg-white'}"
                    >
                        <span
                            class="font-display font-bold text-[16px] tracking-[-0.01em] flex-1 min-w-0 flex items-center gap-2"
                        >
                            <span class="truncate">{row.name}</span>
                            {#if serving}
                                <span
                                    class="size-2.5 rounded-full bg-yellow-400 ring-1 ring-yellow-600/20 shrink-0"
                                    data-testid="serving-dot"
                                    data-side={row.side}
                                    aria-label="Serving"
                                ></span>
                            {/if}
                        </span>
                        <div class="flex items-center gap-1.5">
                            {#each score.sets as set, i (i)}
                                <span
                                    class="font-mono text-[12px] text-neutral-500 tabular-nums min-w-[18px] text-center"
                                >
                                    {row.side === 1 ? set.a : set.b}
                                </span>
                            {/each}
                            <span
                                class="font-mono text-[12px] text-neutral-400 tabular-nums min-w-[18px] text-center"
                            >
                                {score.tiebreak
                                    ? ''
                                    : row.side === 1
                                      ? score.set_a
                                      : score.set_b}
                            </span>
                            <span
                                class="font-mono text-[20px] font-bold tabular-nums min-w-[28px] text-center {colors.text}"
                            >
                                {displayGameScore(score, row.side)}
                            </span>
                        </div>
                    </div>
                {/each}
            </div>

            {#if score.first_server === null}
                <div
                    class="bg-white border border-black/10 px-5 py-4 flex flex-col gap-2"
                >
                    <span
                        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400"
                    >
                        Who serves first?
                    </span>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            data-testid="server-pick-a"
                            onclick={() => pickServer(1)}
                            class="flex-1 px-3 py-2 border text-[12px] font-bold uppercase tracking-[0.12em] transition-colors {selectedSide ===
                            1
                                ? 'border-emerald-600 bg-emerald-50 text-emerald-700'
                                : 'border-black/10 hover:border-neutral-400'}"
                        >
                            {match.side_a_name}
                        </button>
                        <button
                            type="button"
                            data-testid="server-pick-b"
                            onclick={() => pickServer(2)}
                            class="flex-1 px-3 py-2 border text-[12px] font-bold uppercase tracking-[0.12em] transition-colors {selectedSide ===
                            2
                                ? 'border-sky-600 bg-sky-50 text-sky-700'
                                : 'border-black/10 hover:border-neutral-400'}"
                        >
                            {match.side_b_name}
                        </button>
                    </div>
                    {#if selectedSide !== null}
                        <button
                            type="button"
                            data-testid="start-match"
                            onclick={startMatch}
                            class="w-full mt-1 px-3 py-2.5 bg-amber-600 text-white text-[12px] font-bold uppercase tracking-[0.12em] hover:bg-neutral-800 transition-colors"
                        >
                            Start Match
                        </button>
                    {/if}
                </div>
            {/if}

            {#if score.first_server !== null}
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold uppercase tracking-[0.22em] {statusTone}"
                    >
                        {statusBanner.text}
                    </span>
                    <button
                        type="button"
                        data-testid="undo"
                        onclick={rollback}
                        disabled={!undoable}
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-black/10 bg-white text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-500 transition-colors hover:bg-neutral-50 disabled:opacity-40 disabled:cursor-default"
                    >
                        <RotateCcw class="size-3" />
                        Undo
                    </button>
                </div>
            {/if}
        </div>

        {#if score.first_server !== null}
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-2">
                    <span
                        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-500 px-1"
                    >
                        Point won by
                    </span>
                    <button
                        type="button"
                        data-testid="point-a"
                        onclick={() => awardPoint(1)}
                        disabled={decided || saving}
                        class="w-full px-2 py-3 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-display font-bold text-[12px] uppercase tracking-[0.12em] flex items-center justify-center gap-2 transition-colors disabled:opacity-40 disabled:cursor-default"
                    >
                        <span class="size-2 rounded-full bg-emerald-600"></span>
                        {match.side_a_name}
                    </button>
                    <button
                        type="button"
                        data-testid="point-b"
                        onclick={() => awardPoint(2)}
                        disabled={decided || saving}
                        class="w-full px-2 py-3 border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sky-700 font-display font-bold text-[12px] uppercase tracking-[0.12em] flex items-center justify-center gap-2 transition-colors disabled:opacity-40 disabled:cursor-default"
                    >
                        <span class="size-2 rounded-full bg-sky-600"></span>
                        {match.side_b_name}
                    </button>
                </div>

                <div class="bg-white border border-black/10">
                    <button
                        type="button"
                        onclick={() => (showEventBar = !showEventBar)}
                        class="w-full px-4 py-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-500 hover:text-neutral-700"
                    >
                        Event Recording
                        <span class="text-neutral-400"
                            >{showEventBar ? '−' : '+'}</span
                        >
                    </button>
                    {#if showEventBar}
                        <div
                            class="px-3 pb-3 flex flex-wrap gap-1.5 border-t border-black/10 pt-3"
                        >
                            {#each events as ev (ev)}
                                {@const active =
                                    !score.fault &&
                                    ev !== 'Fault' &&
                                    !ev.includes('Double') &&
                                    score.pending_shot === ev}
                                {@const dfActive = ev === 'Double fault'}
                                {@const faultActive =
                                    ev === 'Fault' && score.fault}
                                {@const dfDisabled =
                                    dfActive && (!score.fault || decided)}
                                <button
                                    type="button"
                                    data-testid="event-{ev
                                        .toLowerCase()
                                        .replace(' ', '-')}"
                                    onclick={() => fireEvent(ev)}
                                    disabled={dfDisabled}
                                    class="px-2.5 py-1.5 border text-[10px] font-bold uppercase tracking-[0.12em] transition-colors {faultActive
                                        ? 'border-sky-600 bg-sky-600 text-white'
                                        : active
                                          ? 'border-emerald-600 bg-emerald-600 text-white'
                                          : 'border-black/10 hover:bg-neutral-50 text-neutral-700'} disabled:opacity-40 disabled:cursor-default"
                                >
                                    {ev}
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>

                <div class="bg-white border border-black/10">
                    <button
                        type="button"
                        onclick={() => (showShotBar = !showShotBar)}
                        class="w-full px-4 py-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-500 hover:text-neutral-700"
                    >
                        Shot Recording
                        <span class="text-neutral-400"
                            >{showShotBar ? '−' : '+'}</span
                        >
                    </button>
                    {#if showShotBar}
                        <div
                            class="px-3 pb-3 flex flex-col gap-2 border-t border-black/10 pt-3"
                        >
                            <div class="flex flex-wrap gap-1.5">
                                {#each shots as shot (shot)}
                                    {@const active =
                                        score.pending_shot === shot}
                                    {@const isAce = shot === 'Ace'}
                                    {@const isWinner = shot === 'Winner'}
                                    <button
                                        type="button"
                                        data-testid="shot-{shot
                                            .toLowerCase()
                                            .replace(' ', '-')}"
                                        onclick={() => tag(shot)}
                                        class="px-2.5 py-1.5 border text-[10px] font-bold uppercase tracking-[0.12em] transition-colors {active
                                            ? 'border-emerald-600 bg-emerald-600 text-white'
                                            : isAce
                                              ? 'border-black/10 text-emerald-600 hover:bg-neutral-50'
                                              : isWinner
                                                ? 'border-black/10 text-sky-600 hover:bg-neutral-50'
                                                : 'border-black/10 text-neutral-700 hover:bg-neutral-50'}"
                                    >
                                        {shot}
                                    </button>
                                {/each}
                            </div>
                            {#if score.pending_shot}
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-neutral-700 text-center pt-1"
                                >
                                    Shot: {score.pending_shot} — now tap a player
                                </div>
                            {:else}
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-neutral-400 text-center pt-1"
                                >
                                    Tap a shot type before awarding a point
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            </div>
        {:else}
            <div
                class="bg-amber-50 border border-amber-200 px-6 py-12 text-center flex flex-col gap-2"
            >
                <span
                    class="text-[10px] font-bold uppercase tracking-[0.22em] text-amber-700"
                >
                    Pick who serves first
                </span>
                <p class="text-[12px] text-amber-700/80">
                    Score updates unlock once a server is chosen.
                </p>
            </div>
        {/if}
    </div>
{:else}
    {@render winnerPanel()}
    {@render winnerActions()}
{/if}
