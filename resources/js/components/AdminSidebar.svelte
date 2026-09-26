<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import Settings from 'lucide-svelte/icons/settings';
    import Swords from 'lucide-svelte/icons/swords';
    import Trophy from 'lucide-svelte/icons/trophy';
    import Users from 'lucide-svelte/icons/users';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';

    const url = currentUrlState();
    const isSuperAdmin = $derived(
        page.props.auth.user?.is_super_admin === true,
    );
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href="/admin/dashboard"
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.isCurrentUrl(
                        '/admin/dashboard',
                        url.currentUrl,
                    )}
                    tooltip="Dashboard"
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href="/admin/dashboard"
                            class={props.class}
                        >
                            <LayoutGrid class="size-4 shrink-0" />
                            <span>Dashboard</span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.currentUrl.includes('/admin/tournaments')}
                    tooltip="Tournaments"
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href="/admin/tournaments"
                            class={props.class}
                        >
                            <Trophy class="size-4 shrink-0" />
                            <span>Tournaments</span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.currentUrl.includes('/admin/tennis-matches')}
                    tooltip="Tennis Matches"
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href="/admin/tennis-matches"
                            class={props.class}
                        >
                            <Swords class="size-4 shrink-0" />
                            <span>Tennis Matches</span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.currentUrl.includes('/admin/settings')}
                    tooltip="System Settings"
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href="/admin/settings"
                            class={props.class}
                        >
                            <Settings class="size-4 shrink-0" />
                            <span>System Settings</span>
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
            {#if isSuperAdmin}
                <SidebarMenuItem>
                    <SidebarMenuButton
                        asChild
                        isActive={url.currentUrl.includes('/admin/users')}
                        tooltip="Users"
                    >
                        {#snippet children(props)}
                            <Link
                                {...props}
                                href="/admin/users"
                                class={props.class}
                            >
                                <Users class="size-4 shrink-0" />
                                <span>Users</span>
                            </Link>
                        {/snippet}
                    </SidebarMenuButton>
                </SidebarMenuItem>
            {/if}
        </SidebarMenu>
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
