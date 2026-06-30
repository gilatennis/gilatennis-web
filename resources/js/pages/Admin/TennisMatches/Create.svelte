<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
</script>

<AppHead title="New Match" />

<div class="flex-1 flex flex-col overflow-hidden bg-neutral-50">
    <div class="h-14 bg-white border-b border-black/8 flex items-center px-8 shrink-0 gap-2">
        <span class="text-[12px] text-neutral-400 font-medium">Admin</span>
        <span class="text-neutral-300 text-[12px]">/</span>
        <a href="/admin/tennis-matches" class="text-[12px] font-medium text-neutral-500 hover:text-neutral-900">Tennis Matches</a>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800">New Match</span>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-8">
        <h1 class="font-display font-black text-[28px] tracking-[-0.025em] leading-none">New Match</h1>
        <p class="mt-1.5 text-[13px] text-neutral-400">Match setup form coming next.</p>
    </div>
</div>
