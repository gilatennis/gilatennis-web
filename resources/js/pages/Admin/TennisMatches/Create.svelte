<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Form } from '@inertiajs/svelte';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import Save from 'lucide-svelte/icons/save';
    import AdminPage from '@/components/admin/AdminPage.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Switch } from '@/components/ui/switch';
    import { store as storeMatch } from '@/routes/admin/tennis-matches';

    let extendAtTie = $state(false);

    const inputClass =
        'w-full h-9 px-3 border border-black/12 bg-white text-[13px] text-neutral-900 outline-none focus:border-black/35 transition-colors placeholder:text-neutral-300 font-mono';
    const textClass = inputClass.replace('font-mono', '');
</script>

<AppHead title="New Match" />

<AdminPage>
    {#snippet breadcrumb()}
        <button
            type="button"
            onclick={() => router.visit('/admin/tennis-matches')}
            class="text-[12px] text-neutral-400 font-medium hover:text-neutral-700 flex items-center gap-1 transition-colors"
        >
            <ChevronLeft class="size-3" />
            Tennis Matches
        </button>
        <span class="text-neutral-300 text-[12px]">/</span>
        <span class="text-[12px] font-semibold text-neutral-800">New Match</span
        >
    {/snippet}

    {#snippet heading()}
        <h1
            class="font-display font-black text-[28px] tracking-[-0.025em] leading-none"
        >
            New Match
        </h1>
    {/snippet}

    <div class="max-w-[768px]">

            <Form
                action={storeMatch.url()}
                method="post"
                options={{ preserveScroll: true }}
            >
                {#snippet children({ errors, processing })}
                    <div class="bg-white border border-black/10 p-6 space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    required>Side A</Label
                                >
                                <Input
                                    type="text"
                                    name="side_a_name"
                                    maxlength={60}
                                    placeholder="e.g. Roger Federer"
                                    class={textClass}
                                />
                                <InputError
                                    class="mt-1"
                                    message={errors.side_a_name}
                                />
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    required>Side B</Label
                                >
                                <Input
                                    type="text"
                                    name="side_b_name"
                                    maxlength={60}
                                    placeholder="e.g. Rafael Nadal"
                                    class={textClass}
                                />
                                <InputError
                                    class="mt-1"
                                    message={errors.side_b_name}
                                />
                            </div>
                        </div>

                        <div class="border-t border-black/8 pt-5">
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-400 mb-4"
                            >
                                Format
                            </p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label
                                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                        required>Sets to win</Label
                                    >
                                    <input
                                        type="number"
                                        name="sets_to_win"
                                        min={1}
                                        max={5}
                                        value="2"
                                        class={inputClass}
                                    />
                                    <InputError
                                        class="mt-1"
                                        message={errors.sets_to_win}
                                    />
                                </div>
                                <div>
                                    <Label
                                        class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                        required>Games per set</Label
                                    >
                                    <input
                                        type="number"
                                        name="games_to_win"
                                        min={1}
                                        max={7}
                                        value="6"
                                        class={inputClass}
                                    />
                                    <InputError
                                        class="mt-1"
                                        message={errors.games_to_win}
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-t border-black/8 pt-5 grid grid-cols-2 gap-4 items-end"
                        >
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    >Extend at tie</Label
                                >
                                <div class="h-9 flex items-center gap-3">
                                    <Switch bind:checked={extendAtTie} />
                                    <span class="text-[12px] text-neutral-500">
                                        {extendAtTie
                                            ? 'Yes — play one more game at tie, then tiebreak'
                                            : 'No — tiebreak at games per set'}
                                    </span>
                                </div>
                                <input
                                    type="hidden"
                                    name="extend_at_tie"
                                    value={extendAtTie ? '1' : '0'}
                                />
                                <InputError
                                    class="mt-1"
                                    message={errors.extend_at_tie}
                                />
                            </div>
                            <div>
                                <Label
                                    class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-500 mb-1.5"
                                    required>Tiebreak to</Label
                                >
                                <input
                                    type="number"
                                    name="tiebreak_to"
                                    min={1}
                                    max={21}
                                    value="7"
                                    class={inputClass}
                                />
                                <InputError
                                    class="mt-1"
                                    message={errors.tiebreak_to}
                                />
                            </div>
                        </div>

                        <div
                            class="border-t border-black/8 pt-5 flex items-center justify-end gap-3"
                        >
                            <button
                                type="button"
                                onclick={() =>
                                    router.visit('/admin/tennis-matches')}
                                class="h-9 px-4 text-[11px] font-bold uppercase tracking-[0.2em] text-neutral-600 hover:text-neutral-900 transition-colors"
                            >
                                Cancel
                            </button>
                            <Button
                                type="submit"
                                disabled={processing}
                                class="h-9 px-5 bg-coral text-white inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.18em] hover:opacity-90 transition-opacity disabled:opacity-50"
                            >
                                <Save class="size-3.5" />
                                {processing ? 'Creating…' : 'Create Match'}
                            </Button>
                        </div>
                    </div>
                {/snippet}
            </Form>
        </div>
</AdminPage>
