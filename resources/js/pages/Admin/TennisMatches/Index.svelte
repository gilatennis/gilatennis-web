<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Swords from 'lucide-svelte/icons/swords';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import DataTable from '@/components/admin/DataTable.svelte';
    import MatchStatusBadge from '@/components/admin/MatchStatusBadge.svelte';
    import Pagination from '@/components/admin/Pagination.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { useMatchStatus } from '@/composables/useMatchStatus.svelte';
    import type { MatchStatusValue } from '@/lib/tennis-engine';
    import { show as showMatch } from '@/routes/admin/tennis-matches';

    interface Match {
        id: number;
        side_a_name: string;
        side_b_name: string;
        format_label: string;
        status: MatchStatusValue;
        started_at: string | null;
    }

    let {
        matches,
        stats,
    }: {
        matches: {
            data: Match[];
            current_page: number;
            last_page: number;
            from: number;
            to: number;
            total: number;
        };
        stats: {
            total: number;
            pending: number;
            running: number;
            completed: number;
        };
    } = $props();

    function changePage(page: number) {
        router.visit(window.location.pathname, {
            data: { page },
            preserveState: true,
        });
    }

    const { setStatus, busyId } = useMatchStatus();

    function sendToCourt(match: Match) {
        setStatus(match.id, 'running', ['matches', 'stats']);
    }

    function recall(match: Match) {
        setStatus(match.id, 'pending', ['matches', 'stats']);
    }

    function formatDate(iso: string | null): string {
        if (!iso) {
            return '—';
        }

        const d = new Date(iso);

        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }
</script>

<AppHead title="Tennis Matches" />

<AdminPage>
    {#snippet breadcrumb()}
        <span class="text-[12px] text-neutral-400 font-medium">Admin</span>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >Tennis Matches</span
        >
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
        >
            Tennis Matches
        </h1>
        <p class="mt-1.5 text-[13px] text-neutral-500">
            {stats.total} total
            <span class="mx-1.5 text-neutral-300">·</span>
            <span class="text-amber-600 font-medium"
                >{stats.pending} pending</span
            >
            <span class="mx-1.5 text-neutral-300">·</span>
            <span class="text-emerald-600 font-medium"
                >{stats.running} running</span
            >
            <span class="mx-1.5 text-neutral-300">·</span>
            <span class="text-blue-600 font-medium"
                >{stats.completed} completed</span
            >
        </p>
    {/snippet}

    {#snippet actions()}
        <Link
            href="/admin/tennis-matches/create"
            class="h-10 px-5 bg-coral text-white inline-flex items-center justify-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity w-full sm:w-auto sm:justify-start"
        >
            <Plus class="size-3.5" />
            New Match
        </Link>
    {/snippet}

    {#if matches.data.length === 0}
        <DataTable
            items={matches.data}
            getKey={(m) => m.id}
            columns="60px 2fr 1.6fr 110px 120px 150px"
        >
            {#snippet empty()}
                <div
                    class="bg-white border border-black/10 flex flex-col items-center justify-center py-20 text-center px-8"
                >
                    <Swords class="size-14 mb-5 text-neutral-200" />
                    <p
                        class="font-display font-bold text-[17px] text-neutral-700"
                    >
                        No matches yet
                    </p>
                    <p class="mt-1 text-[13px] text-neutral-400">
                        Create your first match to get started
                    </p>
                    <Link
                        href="/admin/tennis-matches/create"
                        class="mt-6 h-10 px-6 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity"
                    >
                        <Plus class="size-3.5" />
                        New Match
                    </Link>
                </div>
            {/snippet}
        </DataTable>
    {:else}
        <DataTable
            items={matches.data}
            getKey={(m) => m.id}
            columns="60px 2fr 1.6fr 110px 120px 150px"
        >
            {#snippet header()}
                <div>#</div>
                <div>Matchup</div>
                <div>Format</div>
                <div>Status</div>
                <div>Started</div>
                <div class="text-right">Court</div>
            {/snippet}

            {#snippet row(m)}
                <Link href={showMatch({ match: m.id }).url} class="contents">
                    <div
                        class="font-mono text-[13px] tabular-nums text-neutral-500"
                    >
                        #{m.id}
                    </div>
                    <div
                        class="font-semibold text-[13.5px] text-neutral-900 truncate pr-4"
                    >
                        {m.side_a_name}
                        <span class="text-neutral-300 font-normal mx-1.5"
                            >vs</span
                        >
                        {m.side_b_name}
                    </div>
                    <div class="text-[13px] text-neutral-500 truncate pr-3">
                        {m.format_label}
                    </div>
                    <div><MatchStatusBadge status={m.status} /></div>
                    <div class="text-[13px] text-neutral-500">
                        {formatDate(m.started_at)}
                    </div>
                </Link>
                <div class="flex justify-end">
                    {#if m.status === 'pending'}
                        <Button
                            variant="outline"
                            size="sm"
                            data-testid="match-send-court"
                            title="Send to court — appears on the umpire's screen"
                            disabled={busyId === m.id}
                            onclick={() => sendToCourt(m)}
                        >
                            Send to court
                        </Button>
                    {:else if m.status === 'running'}
                        <Button
                            variant="outline"
                            size="sm"
                            data-testid="match-recall"
                            title="Recall from court — back to pending"
                            disabled={busyId === m.id}
                            onclick={() => recall(m)}
                        >
                            Recall
                        </Button>
                    {/if}
                </div>
            {/snippet}

            {#snippet pagination()}
                <Pagination
                    class="border-t border-black/8"
                    from={matches.from}
                    to={matches.to}
                    total={matches.total}
                    currentPage={matches.current_page}
                    lastPage={matches.last_page}
                    onPage={changePage}
                />
            {/snippet}
        </DataTable>
    {/if}
</AdminPage>
