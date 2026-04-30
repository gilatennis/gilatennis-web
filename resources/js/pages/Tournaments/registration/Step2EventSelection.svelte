<script lang="ts">
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    let {
        eventTypes,
        eventType = $bindable(''),
        partnerName = $bindable(''),
        partnerEmail = $bindable(''),
        partnerPhone = $bindable(''),
        partnerDateOfBirth = $bindable(''),
        errors = {} as Record<string, string>,
    }: {
        eventTypes: string[];
        eventType?: string;
        partnerName?: string;
        partnerEmail?: string;
        partnerPhone?: string;
        partnerDateOfBirth?: string;
        errors?: Record<string, string>;
    } = $props();

    const partnerRequired = $derived(eventType === 'doubles' || eventType === 'both');
</script>

<div class="space-y-6">
    <h2 class="font-display font-black text-2xl mb-6">Event selection</h2>

    <div>
        <Label required class="text-[11px] uppercase tracking-[0.2em] font-bold mb-3 block">
            Event type
        </Label>
        <div class="flex flex-col gap-2">
            {#each eventTypes as id}
                {@const label = id.charAt(0).toUpperCase() + id.slice(1)}
                <button type="button" onclick={() => eventType = id}
                    class="text-left border p-4 flex items-center justify-between transition-colors {eventType === id ? 'border-black dark:border-white bg-black/5 dark:bg-white/5' : 'border-black/15 dark:border-white/15 hover:border-black/30 dark:hover:border-white/30'}">
                    <div class="flex items-center gap-3">
                        <div class="h-4 w-4 rounded-full border-2 grid place-items-center {eventType === id ? 'border-black dark:border-white' : 'border-black/30 dark:border-white/30'}">
                            {#if eventType === id}
                                <div class="h-2 w-2 rounded-full bg-black dark:bg-white"></div>
                            {/if}
                        </div>
                        <span class="font-display font-bold text-base">{label}</span>
                    </div>
                </button>
            {/each}
        </div>
        <input type="hidden" name="event_type" value={eventType} />
        {#if errors.event_type}
            <p class="mt-1 text-xs text-coral font-medium">{errors.event_type}</p>
        {/if}
    </div>

    {#if partnerRequired}
        <div class="border border-black/10 dark:border-white/10 p-5 space-y-4">
            <div class="space-y-3">
                <div>
                    <Label for="partnerName" required={partnerRequired} class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                        Partner name
                    </Label>
                    <Input id="partnerName" name="partner_name" type="text" placeholder="Partner full name" bind:value={partnerName}
                        class={errors.partner_name ? 'border-coral ring-2 ring-coral/30' : ''} />
                    {#if errors.partner_name}
                        <p class="mt-1 text-xs text-coral font-medium">{errors.partner_name}</p>
                    {/if}
                </div>
                <div>
                    <Label for="partnerEmail" required={partnerRequired} class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                        Partner email
                    </Label>
                    <Input id="partnerEmail" name="partner_email" type="email" placeholder="partner@email.com" bind:value={partnerEmail}
                        class={errors.partner_email ? 'border-coral ring-2 ring-coral/30' : ''} />
                    {#if errors.partner_email}
                        <p class="mt-1 text-xs text-coral font-medium">{errors.partner_email}</p>
                    {/if}
                </div>
                <div>
                    <Label for="partnerPhone" required={partnerRequired} class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                        Partner phone
                    </Label>
                    <Input id="partnerPhone" name="partner_phone" type="tel" placeholder="0821123123123" bind:value={partnerPhone}
                        class={errors.partner_phone ? 'border-coral ring-2 ring-coral/30' : ''} />
                    {#if errors.partner_phone}
                        <p class="mt-1 text-xs text-coral font-medium">{errors.partner_phone}</p>
                    {/if}
                </div>
                <div>
                    <Label for="partnerDob" required={partnerRequired} class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block">
                        Partner date of birth
                    </Label>
                    <Input id="partnerDob" name="partner_date_of_birth" type="date" bind:value={partnerDateOfBirth}
                        class={errors.partner_date_of_birth ? 'border-coral ring-2 ring-coral/30' : ''} />
                    {#if errors.partner_date_of_birth}
                        <p class="mt-1 text-xs text-coral font-medium">{errors.partner_date_of_birth}</p>
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</div>
