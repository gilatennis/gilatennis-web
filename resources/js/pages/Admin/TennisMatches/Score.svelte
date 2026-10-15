<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import Copy from 'lucide-svelte/icons/copy';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import MatchScoreboard from '@/components/match/MatchScoreboard.svelte';
    import type { MatchState } from '@/lib/tennis-engine';
    import {
        index as indexRoute,
        points as pointsRoute,
        server as serverRoute,
        status as statusRoute,
        undo as undoRoute,
    } from '@/routes/admin/tennis-matches';

    let {
        match,
        bracketUrl = null,
        shareUrl = null,
    }: {
        match: MatchState;
        bracketUrl?: string | null;
        shareUrl?: string | null;
    } = $props();

    let copied = $state(false);

    async function copyScoreLink() {
        if (!shareUrl) {
            return;
        }

        try {
            await navigator.clipboard?.writeText(shareUrl);
            copied = true;
            setTimeout(() => (copied = false), 2000);
        } catch {
            copied = false;
        }
    }

    const listHref = indexRoute().url;
</script>

<AppHead
    title={`Match #${match.id} · ${match.side_a_name} vs ${match.side_b_name}`}
/>

<AdminPage>
    {#snippet breadcrumb()}
        <Link
            href={listHref}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" />
            Tennis Matches
        </Link>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800"
            >Match #{match.id}</span
        >
    {/snippet}

    {#if shareUrl}
        <div
            class="mb-4 flex items-center gap-3 bg-white border border-black/10 px-3 py-2"
        >
            <span
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 shrink-0"
            >
                Score link
            </span>
            <span
                class="flex-1 min-w-0 truncate font-mono text-[11px] text-neutral-500"
            >
                {shareUrl}
            </span>
            <button
                type="button"
                data-testid="copy-score-link"
                onclick={copyScoreLink}
                title="Copy the login-free umpire score link"
                class="shrink-0 inline-flex items-center gap-1.5 h-7 px-3 border border-black/10 text-[10px] font-bold uppercase tracking-[0.14em] text-neutral-600 hover:bg-neutral-50 transition-colors"
            >
                {#if copied}
                    <Check class="size-3 text-emerald-600" />
                    Copied
                {:else}
                    <Copy class="size-3" />
                    Copy
                {/if}
            </button>
        </div>
    {/if}

    <MatchScoreboard
        {match}
        bracketHref={bracketUrl}
        {listHref}
        endpoints={{
            point: pointsRoute.url({ match: match.id }),
            server: serverRoute.url({ match: match.id }),
            undo: undoRoute.url({ match: match.id }),
            status: statusRoute.url({ match: match.id }),
        }}
    />
</AdminPage>
