<script lang="ts">
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectTrigger,
        SelectContent,
        SelectItem,
    } from '@/components/ui/select';

    let {
        name = $bindable(''),
        email = $bindable(''),
        phone = $bindable(''),
        gender = $bindable(''),
        errors = {} as Record<string, string>,
    }: {
        name?: string;
        email?: string;
        phone?: string;
        gender?: string;
        errors?: Record<string, string>;
    } = $props();
</script>

<div class="space-y-5">
    <h2 class="font-display font-black text-2xl mb-6">Player information</h2>

    <div>
        <Label
            for="name"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Full Name
        </Label>
        <Input
            id="name"
            name="name"
            type="text"
            placeholder="Jane Smith"
            bind:value={name}
            class={errors.name ? 'border-coral ring-2 ring-coral/30' : ''}
        />
        {#if errors.name}
            <p class="mt-1 text-xs text-coral font-medium">{errors.name}</p>
        {/if}
    </div>

    <div>
        <Label
            for="email"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Email
        </Label>
        <Input
            id="email"
            name="email"
            type="email"
            placeholder="you@example.com"
            bind:value={email}
            class={errors.email ? 'border-coral ring-2 ring-coral/30' : ''}
        />
        {#if errors.email}
            <p class="mt-1 text-xs text-coral font-medium">{errors.email}</p>
        {/if}
    </div>

    <div>
        <Label
            for="phone"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Phone
        </Label>
        <Input
            id="phone"
            name="phone"
            type="tel"
            placeholder="08123123123"
            bind:value={phone}
            class={errors.phone ? 'border-coral ring-2 ring-coral/30' : ''}
        />
        {#if errors.phone}
            <p class="mt-1 text-xs text-coral font-medium">{errors.phone}</p>
        {/if}
    </div>

    <div>
        <Label
            for="gender"
            required
            class="text-[11px] uppercase tracking-[0.2em] font-bold mb-2 block"
        >
            Gender
        </Label>
        <Select bind:value={gender}>
            <SelectTrigger
                id="gender"
                class="w-full h-9 px-3 text-[13px] {errors.gender
                    ? 'border-coral ring-2 ring-coral/30'
                    : ''}"
            >
                {gender
                    ? gender === 'male'
                        ? 'Male'
                        : 'Female'
                    : 'Select gender'}
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="male" label="Male" />
                <SelectItem value="female" label="Female" />
            </SelectContent>
        </Select>
        {#if errors.gender}
            <p class="mt-1 text-xs text-coral font-medium">{errors.gender}</p>
        {/if}
    </div>
</div>
