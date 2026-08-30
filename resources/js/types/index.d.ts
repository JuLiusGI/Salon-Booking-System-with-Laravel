/**
 * Props shared with every Inertia response by HandleInertiaRequests::share().
 * Keep this in sync with that middleware.
 */
export interface SharedProps {
    name: string;
    flash: {
        success: string | null;
        error: string | null;
    };
}

/**
 * Helper for typing a page component's own props alongside the shared ones.
 *
 *   export default function Welcome({ name, salonName }: PageProps<{ salonName: string }>) {}
 */
export type PageProps<T = Record<string, unknown>> = SharedProps & T;
