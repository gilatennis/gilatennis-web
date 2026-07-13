export type PlayerFieldKey = 'name' | 'gender' | 'email' | 'phone' | 'level' | 'dob';

export interface PlayerFieldConfigEntry {
    label: string;
    default: boolean;
    locked: boolean;
}

export type PlayerFieldConfig = Record<PlayerFieldKey, PlayerFieldConfigEntry>;

export const PLAYER_FIELDS: PlayerFieldConfig = {
    name: { label: 'Name', default: true, locked: true },
    gender: { label: 'Gender', default: true, locked: false },
    email: { label: 'Email', default: true, locked: false },
    phone: { label: 'Phone', default: true, locked: false },
    level: { label: 'Level', default: true, locked: false },
    dob: { label: 'Date of Birth', default: false, locked: false },
};

export const PLAYER_FIELD_KEYS: PlayerFieldKey[] = [
    'name',
    'gender',
    'email',
    'phone',
    'level',
    'dob',
];

export type PlayerFieldRequirement = {
    field_key: PlayerFieldKey;
    required: boolean;
};

export type PlayerFieldRequirementMap = Record<PlayerFieldKey, boolean>;

export interface Player {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    gender?: string | null;
    date_of_birth?: string | null;
    rating_type?: string | null;
    rating_value?: number | null;
    level?: string | null;
}

export interface Registration {
    id: number;
    confirmation_code: string;
    name: string;
    email: string;
    phone?: string | null;
    date_of_birth?: string | null;
    payment_status: PaymentStatus;
    payment_reference?: string | null;
    payment_url?: string | null;
    amount_cents: number;
    fee: number;
    currency: string;
    paid_at?: string | null;
    created_at: string;
    division?: { id: number; name: string } | null;
    team?: { id: number; name: string } | null;
    players: Player[];
}

export type PaymentStatus =
    | 'pending'
    | 'processing'
    | 'paid'
    | 'failed'
    | 'refunded';

export interface ByDivision {
    division_id: number;
    name: string;
    count: number;
}

export interface RegistrationStats {
    total: number;
    paid: number;
    pending: number;
    failed: number;
    by_division: ByDivision[];
}

export interface Division {
    id: number;
    name: string;
    participation_category: 'singles' | 'doubles' | 'team';
    fee: number;
    spots_available: number;
    draw_size: number;
    min_team_size?: number;
    max_team_size?: number;
    min_female_players?: number;
    min_male_players?: number;
    level_requirements?: Record<string, number> | null;
    player_field_requirements?: PlayerFieldRequirement[];
    gender?: 'mens' | 'womens' | 'mixed' | null;
}

export type ParticipationCategory = 'singles' | 'doubles' | 'team' | '';

export interface DivisionContext {
    selected: Division | null;
    isTeam: boolean;
    participationCategory: ParticipationCategory;
    total: number;
    minTeamSize: number;
    maxTeamSize: number;
    minFemalePlayers: number;
    minMalePlayers: number;
    levelRequirements: Record<string, number>;
    playerFieldConfig: PlayerFieldRequirementMap;
}
