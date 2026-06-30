<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Swords from 'lucide-svelte/icons/swords';
    import AppHead from '@/components/AppHead.svelte';

    let {
        matches,
        stats,
    }: {
        matches: {
            data: unknown[];
            current_page: number;
            last_page: number;
            from: number;
            to: number;
            total: number;
        };
        stats: { total: number; running: number; completed: number };
    } = $props();
</script>

<AppHead title="Tennis Matches" />

<div class="flex-1 flex flex-col overflow-hidden bg-neutral-50">
    <div class="h-14 bg-white border-b border-black/8 flex items-center px-8 shrink-0 gap-2">
        <span class="text-[12px] text-neutral-400 font-medium">Admin</span>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800">Tennis Matches</span>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-8">
        <div class="flex items-start justify-between mb-7">
            <div>
                <h1 class="font-display font-black text-[28px] tracking-[-0.025em] leading-none">Tennis Matches</h1>
                <p class="mt-1.5 text-[13px] text-neutral-500">
                    {stats.total} total
                    <span class="mx-1.5 text-neutral-300">·</span>
                    <span class="text-emerald-600 font-medium">{stats.running} running</span>
                    <span class="mx-1.5 text-neutral-300">·</span>
                    <span class="text-blue-600 font-medium">{stats.completed} completed</span>
                </p>
            </div>

            <Link
                href="/admin/tennis-matches/create"
                class="h-10 px-5 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity"
            >
                <Plus class="size-3.5" />
                New Match
            </Link>
        </div>

        <div class="bg-white border border-black/10 flex flex-col items-center justify-center py-20 text-center px-8">
            <Swords class="size-14 mb-5 text-neutral-200" />
            <p class="font-display font-bold text-[17px] text-neutral-700">
                {stats.total === 0 ? 'No matches yet' : 'No matches found'}
            </p>
            <p class="mt-1 text-[13px] text-neutral-400">
                {matches.total} total
            </p>
            <Link
                href="/admin/tennis-matches/create"
                class="mt-6 h-10 px-6 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity"
            >
                <Plus class="size-3.5" />
                New Match
            </Link>
        </div>
    </div>
</div>
