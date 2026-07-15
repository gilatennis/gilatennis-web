<script lang="ts">
    import ErrorBanner from '@/components/registration/ErrorBanner.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import type { FieldErrors, FormErrors } from '@/types/errors';
    import type { DivisionContext } from '@/types/registration';

    let {
        divisions,
        selectedDivisionId = $bindable(null),
        teamName = $bindable(''),
        division,
        fieldErrors = {} as FieldErrors,
        bannerErrors = {} as Record<string, string[]>,
    }: {
        divisions: any[];
        selectedDivisionId?: number | null;
        teamName?: string;
        division: DivisionContext;
        fieldErrors?: FieldErrors;
        bannerErrors?: Record<string, string[]>;
    } = $props();

    const genderReqParts = $derived.by(() => {
        const parts: string[] = [];

        if (division.minFemalePlayers > 0) {
            parts.push(`at least ${division.minFemalePlayers} female`);
        }

        if (division.minMalePlayers > 0) {
            parts.push(`at least ${division.minMalePlayers} male`);
        }

        return parts;
    });

    const genderReqText = $derived(
        genderReqParts.length > 0
            ? 'Requires ' + genderReqParts.join(' and ') + ' player(s).'
            : '',
    );

    const levelReqText = $derived.by(() => {
        const parts: string[] = [];

        for (const [lvl, req] of Object.entries(division.levelRequirements)) {
            if ((req ?? 0) > 0) {
                parts.push(`at least ${req} ${lvl}`);
            }
        }

        return parts.length > 0
            ? 'Requires ' + parts.join(' and ') + ' player(s).'
            : '';
    });

    const formatIDR = (amount: number) =>
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);

    const selectedDivision = $derived(
        divisions.find((d: any) => d.id === selectedDivisionId) ?? null,
    );

    export function validate(): FormErrors {
        const errs: FormErrors = {};

        if (!selectedDivisionId) {
            errs.division_id = 'Please select a division.';
        }

        if (division.isTeam && !teamName.trim()) {
            errs.team_name = 'Team name is required.';
        }

        return errs;
    }
</script>

<div class="space-y-6">
    <h2 class="font-display font-black text-2xl mb-6">Select division</h2>

    <ErrorBanner
        errors={bannerErrors}
        exclude={['division_id', 'team_name', 'players']}
    />

    <div>
        <Label
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-3 block"
        >
            Division
        </Label>
        <div class="flex flex-col gap-2">
            {#each divisions as div}
                {@const soldOut = (div.spots_available ?? 0) <= 0}
                <button
                    type="button"
                    onclick={() => !soldOut && (selectedDivisionId = div.id)}
                    disabled={soldOut}
                    class="text-left border p-4 flex items-center justify-between transition-colors
                        {selectedDivisionId === div.id
                        ? 'border-black dark:border-white bg-black/5 dark:bg-white/5'
                        : 'border-black/15 dark:border-white/15 hover:border-black/30 dark:hover:border-white/30'}
                        {soldOut ? 'opacity-40 cursor-not-allowed' : ''}"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="h-4 w-4 rounded-full border-2 grid place-items-center shrink-0 {selectedDivisionId ===
                            div.id
                                ? 'border-black dark:border-white'
                                : 'border-black/30 dark:border-white/30'}"
                        >
                            {#if selectedDivisionId === div.id}
                                <div
                                    class="h-2 w-2 rounded-full bg-black dark:bg-white"
                                ></div>
                            {/if}
                        </div>
                        <div>
                            <span class="font-display font-bold text-base"
                                >{div.name}</span
                            >
                            <span
                                class="ml-2 text-[10px] uppercase tracking-[0.15em] text-neutral-400"
                            >
                                {div.participation_category}
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-display font-bold text-sm">
                            {formatIDR(div.fee)}
                        </div>
                        <div
                            class="text-[10px] uppercase tracking-[0.15em] {soldOut
                                ? 'text-coral font-bold'
                                : 'text-neutral-400'}"
                        >
                            {soldOut
                                ? 'Sold out'
                                : `${div.spots_available} spots left`}
                        </div>
                    </div>
                </button>
            {/each}
        </div>
        <input
            type="hidden"
            name="division_id"
            value={selectedDivisionId ?? ''}
        />
        {#if fieldErrors.division_id}
            <p class="mt-1 text-xs text-coral font-medium">
                {fieldErrors.division_id}
            </p>
        {/if}
    </div>

    {#if selectedDivision && division.isTeam}
        <div>
            <Label
                for="teamName"
                required
                class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
            >
                Team name
            </Label>
            <Input
                id="teamName"
                name="team_name"
                type="text"
                placeholder="Enter team name"
                bind:value={teamName}
                class={fieldErrors.team_name
                    ? 'border-coral ring-2 ring-coral/30'
                    : ''}
            />
            {#if fieldErrors.team_name}
                <p class="mt-1 text-xs text-coral font-medium">
                    {fieldErrors.team_name}
                </p>
            {/if}
        </div>
        {#if genderReqText}
            <p class="text-[11px] text-neutral-500 leading-relaxed mt-2">
                {genderReqText}
            </p>
        {/if}
        {#if levelReqText}
            <p class="text-[11px] text-neutral-500 leading-relaxed mt-1">
                {levelReqText}
            </p>
        {/if}
    {/if}
</div>
