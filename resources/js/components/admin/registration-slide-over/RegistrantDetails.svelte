<script lang="ts">
    import { formatDate } from '@/lib/format';
    import type { Registration } from '@/types';

    let { registration }: { registration: Registration | null } = $props();
</script>

<div class="px-4 sm:px-6 py-5 border-b border-black/6">
    <p
        class="text-[10px] font-bold uppercase tracking-[0.22em] text-neutral-400 mb-3"
    >
        Registrant
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 sm:gap-x-6 gap-y-3">
        <div>
            <div
                class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
            >
                Email
            </div>
            <div class="text-[13px] text-neutral-800">
                {registration?.email}
            </div>
        </div>
        <div>
            <div
                class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
            >
                Phone
            </div>
            <div class="text-[13px] text-neutral-800">
                {registration?.phone || '—'}
            </div>
        </div>
        <div>
            <div
                class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
            >
                Date of Birth
            </div>
            <div class="text-[13px] text-neutral-800">
                {formatDate(registration?.date_of_birth ?? '') || '—'}
            </div>
        </div>
        <div>
            <div
                class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
            >
                Division
            </div>
            <div class="text-[13px] text-neutral-800">
                {registration?.division?.name || '—'}
            </div>
        </div>
        <div>
            <div
                class="text-[10px] font-bold uppercase tracking-[0.18em] text-neutral-400 mb-0.5"
            >
                Team
            </div>
            <div class="text-[13px] text-neutral-800">
                {registration?.team?.name || '—'}
            </div>
        </div>
    </div>
</div>
