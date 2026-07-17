<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Trophy from 'lucide-svelte/icons/trophy';
    import { onMount } from 'svelte';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import MatchStatusBadge from '@/components/admin/MatchStatusBadge.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import {
        applyEvent,
        canUndo,
        createInitialState,
        displayGameScore,
        isComplete,
        scorePoint,
        setServer,
        tagShot,
        undo
    } from '@/lib/tennis-engine';
    import type {EventTag, MatchState, ShotTag, Side} from '@/lib/tennis-engine';
    import { index as indexRoute, update as updateRoute } from '@/routes/admin/tennis-matches';

    type MatchStatus = 'pending' | 'running' | 'completed' | 'canceled';

    interface MatchProps {
        id: number;
        side_a_name: string;
        side_b_name: string;
        sets_to_win: number;
        games_to_win: number;
        extend_at_tie: boolean;
        tiebreak_to: number;
        status: MatchStatus;
        started_at: string | null;
        ended_at: string | null;
        created_at: string | null;
        format_label: string;
    }

    let { match }: { match: MatchProps } = $props();

    let liveStatus: MatchStatus = $state(match.status);
    let selectedSide: Side | null = $state(null);
    let syncing = $state(false);
    let syncError: string | null = $state(null);
    let hasSyncedRunning = $state(match.status !== 'pending');
    let hasSyncedCompleted = $state(
        match.status === 'completed' || match.status === 'canceled',
    );

    let score: MatchState = $state(
        createInitialState(
            {
                setsToWin: match.sets_to_win,
                gamesToWin: match.games_to_win,
                extendAtTie: match.extend_at_tie,
                tiebreakTo: match.tiebreak_to,
            },
            match.side_a_name,
            match.side_b_name,
            null,
        ),
    );

    let complete = $derived(isComplete(score));
    let undoable = $derived(canUndo(score));
    let showShotBar = $state(true);
    let showEventBar = $state(true);

    const shots: ShotTag[] = ['Ace', 'Winner', 'Unforced error', 'Forced error'];
    const events: EventTag[] = ['Fault', 'Double fault', 'Let'];

    function syncStatus(next: 'running' | 'completed') {
        if (syncing) {
            return;
        }

        if (next === 'running' && hasSyncedRunning) {
            return;
        }

        if (next === 'completed' && hasSyncedCompleted) {
            return;
        }

        syncing = true;
        syncError = null;
        const url = updateRoute({ match: match.id }).url;
        router.put(url, { status: next }, {
            preserveScroll: true,
            onSuccess: () => {
                liveStatus = next;

                if (next === 'running') {
                    hasSyncedRunning = true;
                }

                if (next === 'completed') {
                    hasSyncedCompleted = true;
                }
            },
            onError: (errors) => {
                const msg = (errors as Record<string, string>).status ?? 'Sync failed.';
                syncError = msg;
            },
            onFinish: () => {
                syncing = false;
            },
        });
    }

    function pickServer(side: Side) {
        selectedSide = side;
    }

    function startMatch() {
        if (selectedSide === null) {
            return;
        }

        score = setServer(score, selectedSide);
    }

    function awardPoint(side: Side) {
        if (complete) {
            return;
        }

        const wasHistoryEmpty = score.history.length === 0;
        score = scorePoint(score, side);

        if (wasHistoryEmpty) {
            syncStatus('running');
        }

        if (isComplete(score) && !hasSyncedCompleted) {
            syncStatus('completed');
        }
    }

    function rollback() {
        const next = undo(score);

        if (next) {
            score = next;

            return;
        }

        if (score.firstServer !== null) {
            score = createInitialState(score.format, score.p1, score.p2, null);
            selectedSide = null;
        }
    }

    function tag(label: ShotTag) {
        score = tagShot(score, label);
    }

    function fireEvent(label: EventTag) {
        if (label === 'Double fault' && !score.fault) {
            return;
        }

        const wasHistoryEmpty = score.history.length === 0;
        score = applyEvent(score, label);

        if (wasHistoryEmpty && score.history.length > 0) {
            syncStatus('running');
        }

        if (isComplete(score) && !hasSyncedCompleted) {
            syncStatus('completed');
        }
    }

    function backToList() {
        router.visit(indexRoute().url);
    }

    function playerColor(side: Side) {
        return side === 1
            ? { dot: 'bg-emerald-600', soft: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-700' }
            : { dot: 'bg-sky-600', soft: 'bg-sky-50', border: 'border-sky-200', text: 'text-sky-700' };
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

    onMount(() => {
        if (match.status === 'running' || match.status === 'completed') {
            hasSyncedRunning = true;
        }
    });
</script>

<AppHead
    title={`Match #${match.id} · ${match.side_a_name} vs ${match.side_b_name}`}
/>

<AdminPage>
    {#snippet breadcrumb()}
        <button
            type="button"
            onclick={backToList}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" />
            Tennis Matches
        </button>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >Match #{match.id}</span
        >
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[32px] tracking-[-0.025em] leading-none"
        >
            {match.side_a_name}
            <span class="text-neutral-300 mx-3">vs</span>
            {match.side_b_name}
        </h1>
        <div class="flex items-center space-x-2">
            <span class=""><MatchStatusBadge status={liveStatus} /></span>
            <p class="pt-1 text-[13px] text-neutral-500">
                {#if match.started_at}
                    Started {formatDate(match.started_at)}
                {:else}
                    Not started yet
                {/if}
            </p>
        </div>
    {/snippet}

    {#if syncError}
        <div
            class="mb-4 border border-red-200 bg-red-50 text-red-800 text-[12px] px-3 py-2"
        >
            Status sync failed: {syncError}
        </div>
    {/if}

    {#if !complete}
        <p class="mt-2 mb-2 text-[13px] text-neutral-500">
            {match.format_label}
            <span class="mx-1.5 text-neutral-300">·</span>
        </p>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 flex flex-col gap-4">
                <div class="bg-white border border-black/10">
                    {#each [{ side: 1 as Side, name: match.side_a_name }, { side: 2 as Side, name: match.side_b_name }] as row (row.side)}
                        {@const colors = playerColor(row.side)}
                        {@const serving = score.firstServer !== null && score.server === row.side}
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
                                        aria-label="Serving"
                                    ></span>
                                {/if}
                            </span>
                            <div class="flex items-center gap-1.5">
                                {#each score.sets as set, i (i)}
                                    <span
                                        class="font-mono text-[12px] text-neutral-500 tabular-nums min-w-[18px] text-center"
                                    >
                                        {set.a}{set.b}
                                    </span>
                                {/each}
                                <span
                                    class="font-mono text-[12px] text-neutral-400 tabular-nums min-w-[18px] text-center"
                                >
                                    {score.tiebreak ? '' : row.side === 1 ? score.setA : score.setB}
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

                {#if score.firstServer === null}
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
                                onclick={startMatch}
                                class="w-full mt-1 px-3 py-2.5 bg-amber-600 text-white text-[12px] font-bold uppercase tracking-[0.12em] hover:bg-neutral-800 transition-colors"
                            >
                                Start Match
                            </button>
                        {/if}
                    </div>
                {/if}

                {#if score.firstServer !== null}
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-500"
                        >
                            {score.server === 1 ? match.side_a_name : match.side_b_name} serving
                        </span>
                        <button
                            type="button"
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

            {#if score.firstServer !== null}
                <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-2">
                    <span
                        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-500 px-1"
                    >
                        Point won by
                    </span>
                    <button
                        type="button"
                        onclick={() => awardPoint(1)}
                        class="w-full px-2 py-3 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-display font-bold text-[12px] uppercase tracking-[0.12em] flex items-center justify-center gap-2 transition-colors"
                    >
                        <span class="size-2 rounded-full bg-emerald-600"></span>
                        {match.side_a_name}
                    </button>
                    <button
                        type="button"
                        onclick={() => awardPoint(2)}
                        class="w-full px-2 py-3 border border-sky-200 bg-sky-50 hover:bg-sky-100 text-sky-700 font-display font-bold text-[12px] uppercase tracking-[0.12em] flex items-center justify-center gap-2 transition-colors"
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
                        <span class="text-neutral-400">{showEventBar ? '−' : '+'}</span>
                    </button>
                    {#if showEventBar}
                        <div class="px-3 pb-3 flex flex-wrap gap-1.5 border-t border-black/10 pt-3">
                            {#each events as ev (ev)}
                                {@const active = !score.fault && ev !== 'Fault' && !ev.includes('Double') && score.pendingShot === ev}
                                {@const dfActive = ev === 'Double fault'}
                                {@const faultActive = ev === 'Fault' && score.fault}
                                {@const dfDisabled = dfActive && !score.fault}
                                <button
                                    type="button"
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
                        <span class="text-neutral-400">{showShotBar ? '−' : '+'}</span>
                    </button>
                    {#if showShotBar}
                        <div class="px-3 pb-3 flex flex-col gap-2 border-t border-black/10 pt-3">
                            <div class="flex flex-wrap gap-1.5">
                                {#each shots as shot (shot)}
                                    {@const active = score.pendingShot === shot}
                                    {@const isAce = shot === 'Ace'}
                                    {@const isWinner = shot === 'Winner'}
                                    <button
                                        type="button"
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
                            {#if score.pendingShot}
                                <div
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-neutral-700 text-center pt-1"
                                >
                                    Shot: {score.pendingShot} — now tap a player
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
        <div
            class="bg-white border border-black/10 flex flex-col items-center text-center px-6 py-12 gap-4"
        >
            <Trophy class="size-12 {score.win === 1 ? 'text-emerald-600' : 'text-sky-600'}" />
            <div>
                <div
                    class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400"
                >
                    Match Winner
                </div>
                <div
                    class="mt-2 font-display font-black text-[32px] tracking-[-0.03em] {score.win ===
                    1
                        ? 'text-emerald-700'
                        : 'text-sky-700'}"
                >
                    {score.win === 1 ? match.side_a_name : match.side_b_name}
                </div>
            </div>

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
                        >
                            {set.a}
                        </span>
                        <span class="text-neutral-400 min-w-[8px] text-center">–</span>
                        <span
                            class="font-mono text-[14px] font-bold tabular-nums min-w-[24px] text-center {bWin
                                ? 'text-sky-700'
                                : ''}"
                        >
                            {set.b}
                        </span>
                        <span class="text-[12px] font-medium flex-1 text-right">
                            {match.side_b_name}
                        </span>
                    </div>
                {/each}
            </div>

            <div class="flex flex-wrap gap-2 justify-center mt-2">
                <button
                    type="button"
                    onclick={backToList}
                    class="px-5 py-2 border border-black/10 bg-white text-[11px] font-bold uppercase tracking-[0.12em] text-neutral-700 hover:bg-neutral-50 transition-colors"
                >
                    Back to matches
                </button>
            </div>
        </div>
    {/if}
</AdminPage>
