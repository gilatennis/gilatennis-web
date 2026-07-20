<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
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
        displayGameScore,
        isComplete,
        scorePoint,
        setServer,
        statusLine,
        tagShot,
        undo
    } from '@/lib/tennis-engine';
    import type {EventTag, MatchScore, MatchState, MatchStatusValue, ShotTag, Side} from '@/lib/tennis-engine';
    import { index as indexRoute, update as updateRoute } from '@/routes/admin/tennis-matches';

    const { match }: { match: MatchState } = $props();

    let score: MatchState = $state({
        ...match,
        pending_shot: null,
        fault: false,
        history: [],
    });
    let selectedSide: Side | null = $state(score.first_server !== null ? score.server : null);
    let pending: MatchState | null = $state(null);
    let lastSavedAt: Date | null = $state(null);
    let saveTimer: ReturnType<typeof setTimeout> | null = null;

    let complete = $derived(isComplete(score));
    let undoable = $derived(canUndo(score));
    let statusBanner = $derived(statusLine(score));
    let showShotBar = $state(true);
    let showEventBar = $state(true);

    const saveForm = useForm<{ status: MatchStatusValue; state: MatchScore }>({
        status: match.status,
        state: extractPersistedFields(match),
    });

    const SAVE_DEBOUNCE_MS = 1500;
    const shots: ShotTag[] = ['Ace', 'Winner', 'Unforced error', 'Forced error'];
    const events: EventTag[] = ['Fault', 'Double fault', 'Let'];

    function extractPersistedFields(s: MatchState): MatchScore {
        return {
            server: s.server,
            first_server: s.first_server,
            set_a: s.set_a,
            set_b: s.set_b,
            game_a: s.game_a,
            game_b: s.game_b,
            tiebreak: s.tiebreak,
            tb_a: s.tb_a,
            tb_b: s.tb_b,
            tb_first: s.tb_first,
            sets: s.sets.map((sc) => ({ a: sc.a, b: sc.b })),
            win: s.win,
        };
    }

    function schedule(next: MatchState) {
        score = next;
        pending = next;
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(flush, SAVE_DEBOUNCE_MS);
    }

    function flush() {
        if (pending === null) return;
        if (saveForm.processing) return;
        const snapshot = pending;
        pending = null;

        saveForm
            .transform(() => ({
                status: snapshot.status,
                state: extractPersistedFields(snapshot),
            }))
            .put(updateRoute({ match: match.id }).url, {
                preserveScroll: true,
                showProgress: false,
                onSuccess: () => {
                    lastSavedAt = new Date();
                },
                onError: () => {},
                onFinish: () => {
                    if (pending !== null) {
                        if (saveTimer) clearTimeout(saveTimer);
                        saveTimer = setTimeout(flush, 0);
                    }
                },
            });
    }

    function onBeforeUnload() {
        if (pending === null) return;
        const url = updateRoute({ match: match.id }).url;
        const body = new Blob(
            [JSON.stringify({ status: pending.status, state: extractPersistedFields(pending) })],
            { type: 'application/json' },
        );
        navigator.sendBeacon(url, body);
    }

    function pickServer(side: Side) {
        selectedSide = side;
    }

    function startMatch() {
        if (selectedSide === null) {
            return;
        }

        const next = setServer(score, selectedSide);
        schedule({ ...next, status: 'running' });
    }

    function awardPoint(side: Side) {
        if (complete) {
            return;
        }

        const next = scorePoint(score, side);
        const status: MatchState['status'] = next.win !== null ? 'completed' : 'running';
        schedule({ ...next, status });
    }

    function rollback() {
        const next = undo(score);

        if (next) {
            schedule({ ...next, status: next.history.length === 0 && next.first_server === null ? 'pending' : 'running' });
            return;
        }

        if (score.first_server !== null) {
            const fresh: MatchState = {
                ...score,
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
                pending_shot: null,
                fault: false,
                history: [],
            };
            selectedSide = null;
            schedule({ ...fresh, status: 'pending' });
        }
    }

    function tag(label: ShotTag) {
        score = tagShot(score, label);
    }

    function fireEvent(label: EventTag) {
        if (label === 'Double fault' && !score.fault) {
            return;
        }

        const next = applyEvent(score, label);
        const status: MatchState['status'] = next.win !== null ? 'completed' : 'running';
        schedule({ ...next, status });
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

    function formatTime(d: Date): string {
        const pad = (n: number) => String(n).padStart(2, '0');

        return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    let saveError = $derived(saveForm.errors.status ?? saveForm.errors.state ?? null);
    let isDirty = $derived(pending !== null);

    const statusToneClass: Record<string, string> = {
        neutral: 'text-neutral-500',
        blue: 'text-sky-600',
        emerald: 'text-emerald-700',
        amber: 'text-amber-600',
    };
    let statusTone = $derived(statusToneClass[statusBanner.tone] ?? 'text-neutral-500');

    onMount(() => {
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            if (saveTimer) clearTimeout(saveTimer);
            if (pending !== null) flush();
        };
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
        <div>
            <div class="flex items-center space-x-2">
                <span class=""><MatchStatusBadge status={score.status} /></span>
                <p class="pt-1 text-[13px] text-neutral-500">
                    {#if match.started_at}
                        Started {formatDate(match.started_at)}
                    {:else}
                        Not started yet
                    {/if}
                </p>
            </div>
            <div class="h-2">
                <p class="pt-1 text-[11px] text-neutral-400">
                    {#if saveError}
                        <span class="text-red-600">{saveError}</span>
                    {:else if isDirty}
                        Saving…
                    {:else if lastSavedAt}
                        Autosaved at {formatTime(lastSavedAt)}
                    {/if}
                </p>
            </div>
        </div>
    {/snippet}

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
                        {@const serving = score.first_server !== null && score.server === row.side}
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
                                    {score.tiebreak ? '' : row.side === 1 ? score.set_a : score.set_b}
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
                        class="w-full px-2 py-3 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-display font-bold text-[12px] uppercase tracking-[0.12em] flex items-center justify-center gap-2 transition-colors"
                    >
                        <span class="size-2 rounded-full bg-emerald-600"></span>
                        {match.side_a_name}
                    </button>
                    <button
                        type="button"
                        data-testid="point-b"
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
                                {@const active = !score.fault && ev !== 'Fault' && !ev.includes('Double') && score.pending_shot === ev}
                                {@const dfActive = ev === 'Double fault'}
                                {@const faultActive = ev === 'Fault' && score.fault}
                                {@const dfDisabled = dfActive && !score.fault}
                                <button
                                    type="button"
                                    data-testid="event-{ev.toLowerCase().replace(' ', '-')}"
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
                                    {@const active = score.pending_shot === shot}
                                    {@const isAce = shot === 'Ace'}
                                    {@const isWinner = shot === 'Winner'}
                                    <button
                                        type="button"
                                        data-testid="shot-{shot.toLowerCase().replace(' ', '-')}"
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
                    data-testid="match-winner"
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
