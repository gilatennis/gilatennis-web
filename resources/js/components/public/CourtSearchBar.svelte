<script lang="ts">
    import { Search } from 'lucide-svelte/icons';
    import { Button } from '@/components/ui/button';

    let {
        surfaces = ['Hard', 'Clay', 'Grass'],
        location = 'near me',
    }: {
        surfaces?: string[];
        location?: string;
    } = $props();
</script>

<div class="border-b border-border bg-card">
    <div class="max-w-[1400px] mx-auto px-6 py-6">
        <div
            class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center"
        >
            <!-- Location input -->
            <div class="relative flex-1">
                <Search
                    size={16}
                    class="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"
                />
                <input
                    type="text"
                    placeholder={location === 'near me'
                        ? 'City, state, or zip...'
                        : location}
                    class="w-full h-12 pl-11 pr-4 border border-input bg-background text-sm outline-none focus:border-foreground transition-colors placeholder:text-muted-foreground rounded-md"
                />
            </div>

            <!-- Surface pills -->
            <div class="flex items-center gap-2 overflow-x-auto">
                {#each surfaces as surf (surf)}
                    <button
                        class="shrink-0 px-4 h-12 text-xs uppercase tracking-[0.18em] font-bold border border-input hover:border-foreground hover:bg-foreground hover:text-background transition-colors rounded-md"
                    >
                        {surf}
                    </button>
                {/each}
            </div>

            <!-- Search button -->
            <Button variant="coral" class="h-12 px-8 shrink-0">
                Search courts
            </Button>
        </div>
    </div>
</div>
