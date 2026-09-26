<script lang="ts">
    import {
        MapPin,
        Clock,
        Calendar,
        Users,
        Trophy,
    } from 'lucide-svelte/icons';
    import { cn } from '@/lib/utils';

    let {
        value,
        label,
        icon = null,
    }: {
        value: string;
        label: string;
        icon?: 'map' | 'clock' | 'calendar' | 'users' | 'trophy' | null;
    } = $props();

    const icons = {
        map: MapPin,
        clock: Clock,
        calendar: Calendar,
        users: Users,
        trophy: Trophy,
    };

    const IconComp = $derived(icon ? icons[icon] : null);
</script>

<div class="flex flex-col gap-0.5">
    <div
        class={cn(
            'font-display font-black tracking-[-0.02em] leading-none',
            value.length > 5 ? 'text-2xl' : 'text-3xl lg:text-4xl',
        )}
    >
        {value}
    </div>
    <div
        class="flex items-center gap-1.5 text-[11px] uppercase tracking-[0.18em] text-muted-foreground"
    >
        {#if IconComp}
            <IconComp size={11} />
        {/if}
        {label}
    </div>
</div>
