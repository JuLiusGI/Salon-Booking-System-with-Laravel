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
