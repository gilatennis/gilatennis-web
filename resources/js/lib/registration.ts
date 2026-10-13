import { PLAYER_FIELDS, PLAYER_FIELD_KEYS } from '@/types/registration';
import type {
    DivisionContext,
    DivisionInput,
    ParticipationCategory,
    PlayerFieldRequirementMap,
} from '@/types/registration';

export function buildPlayerFieldConfig(
    d: DivisionInput | null | undefined,
): PlayerFieldRequirementMap {
    const config = {} as PlayerFieldRequirementMap;

    for (const key of PLAYER_FIELD_KEYS) {
        config[key] = key === 'name' ? true : PLAYER_FIELDS[key].default;
    }

    const rows = (d?.player_field_requirements ?? []) as Array<{
        field_key: string;
        required: boolean;
    }>;

    for (const row of rows) {
        if (
            (PLAYER_FIELD_KEYS as string[]).includes(row.field_key) &&
            row.field_key !== 'name'
        ) {
            config[row.field_key as keyof PlayerFieldRequirementMap] =
                !!row.required;
        }
    }

    return config;
}

export function buildDivisionContext(
    d: DivisionInput | null | undefined,
    transactionFee = 0,
): DivisionContext {
    const fee = d?.fee ?? 0;

    return {
        selected: d ?? null,
        isTeam: d?.participation_category === 'team',
        participationCategory: (d?.participation_category ??
            '') as ParticipationCategory,
        fee,
        transactionFee,
        total: fee + transactionFee,
        minTeamSize: d?.min_team_size ?? 0,
        maxTeamSize: d?.max_team_size ?? 0,
        minFemalePlayers: d?.min_female_players ?? 0,
        minMalePlayers: d?.min_male_players ?? 0,
        levelRequirements: (d?.level_requirements ?? {}) as Record<
            string,
            number
        >,
        playerFieldConfig: buildPlayerFieldConfig(d),
    };
}

export const LEVEL_OPTIONS = ['Upper Beginner', 'Intermediate'];
