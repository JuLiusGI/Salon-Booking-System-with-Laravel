import AppLayout from '@/Layouts/AppLayout';
import type { PageProps, UserRole } from '@/types';

const ROLE_SUMMARY: Record<UserRole, { heading: string; description: string }> = {
    admin: {
        heading: 'Administrator',
        description: 'You have full management access to staff, services, schedules, and appointments.',
    },
    receptionist: {
        heading: 'Receptionist',
        description: 'You can manage appointments, check customers in, and book on their behalf.',
    },
    stylist: {
        heading: 'Stylist',
        description: 'You can view your schedule and the appointments assigned to you.',
    },
    customer: {
        heading: 'Customer',
        description: 'You can book appointments and review your booking history.',
    },
};

export default function Dashboard({ auth, role }: PageProps<{ role: UserRole }>) {
    const summary = ROLE_SUMMARY[role];

    return (
        <AppLayout title={`Welcome, ${auth.user?.name ?? ''}`}>
            <div className="rounded-2xl border border-line bg-surface p-6">
                <p className="text-xs font-medium tracking-wide text-ink-muted uppercase">Signed in as</p>
                <p className="mt-1 text-lg font-semibold text-ink">{summary.heading}</p>
                <p className="mt-2 text-sm text-ink-muted">{summary.description}</p>
            </div>

            <p className="mt-6 text-sm text-ink-muted">
                Booking, scheduling, and reporting features are added in later phases.
            </p>
        </AppLayout>
    );
}
