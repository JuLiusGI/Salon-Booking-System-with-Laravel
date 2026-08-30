export type UserRole = 'admin' | 'receptionist' | 'stylist' | 'customer';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
}

/**
 * Props shared with every Inertia response by HandleInertiaRequests::share().
 * Keep this in sync with that middleware.
 */
export interface SharedProps {
    name: string;
    auth: {
        user: AuthUser | null;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    /** Validation errors shared by Inertia on every response. */
    errors: Record<string, string>;

    // Inertia's usePage constraint requires an index signature.
    [key: string]: unknown;
}

/**
 * Helper for typing a page component's own props alongside the shared ones.
 *
 *   export default function Dashboard({ auth, role }: PageProps<{ role: UserRole }>) {}
 */
export type PageProps<T = Record<string, unknown>> = SharedProps & T;

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

/* Public website payloads --------------------------------------------------- */

export interface PublicService {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string;
    image_url: string | null;
}

export interface PublicCategory {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image_url: string | null;
    services: PublicService[];
}

export interface PublicStaff {
    id: number;
    name: string;
    title: string | null;
    bio: string | null;
    photo_url: string | null;
}

export interface GalleryItem {
    id: number;
    title: string | null;
    alt_text: string | null;
}

export interface SalonHourRow {
    day_of_week: number;
    opens_at: string | null;
    closes_at: string | null;
    is_closed: boolean;
}
