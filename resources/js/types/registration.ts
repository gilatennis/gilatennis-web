export interface Player {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    date_of_birth?: string | null;
    rating_type?: string | null;
    rating_value?: number | null;
    tshirt_size?: string | null;
    dietary?: string | null;
}

export interface Registration {
    id: number;
    confirmation_code: string;
    name: string;
    email: string;
    phone?: string | null;
    date_of_birth?: string | null;
    tshirt_size?: string | null;
    dietary?: string | null;
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
