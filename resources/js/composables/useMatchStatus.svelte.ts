import { router, useHttp } from '@inertiajs/svelte';
import type { MatchStatusValue } from '@/lib/tennis-engine';
import { status as statusRoute } from '@/routes/admin/tennis-matches';

/** The statuses a referee can set from the match list / rubbers modal. */
export type CourtStatus = Extract<
    MatchStatusValue,
    'pending' | 'running' | 'completed'
>;

/**
 * Flips a match's status (send to court / recall / complete) via the JSON status
 * endpoint and refreshes the given Inertia props. Shared by the rubbers modal
 * and the admin match list so both surfaces behave identically.
 */
export function useMatchStatus() {
    const http = useHttp<{ status: CourtStatus }, unknown>({
        status: 'running',
    });

    let busyId = $state<number | null>(null);

    async function setStatus(
        matchId: number,
        status: CourtStatus,
        reloadOnly: string[],
    ): Promise<boolean> {
        busyId = matchId;

        try {
            http.setStore({ status });
            await http.post(statusRoute.url({ match: matchId }));
            router.reload({ only: reloadOnly });

            return true;
        } catch {
            return false;
        } finally {
            busyId = null;
        }
    }

    return { setStatus, busyId };
}
