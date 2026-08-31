import type { AppointmentStatus } from '@/types';

/**
 * Appointment status.
 *
 * The label is always rendered, so status never depends on colour alone
 * (MASTER_SPEC section 13). Tints are drawn from the brand palette where the
 * meaning allows it, with true red kept for the genuinely negative outcomes.
 */
const TINTS: Record<AppointmentStatus, string> = {
    pending: 'bg-canvas text-ink border-line-strong',
    confirmed: 'bg-support/25 text-ink border-support/40',
    checked_in: 'bg-secondary/15 text-ink border-secondary/30',
    in_progress: 'bg-secondary/25 text-ink border-secondary/40',
    completed: 'bg-primary text-ink-inverted border-primary',
    cancelled: 'bg-accent/25 text-ink border-accent/50',
    no_show: 'bg-red-50 text-red-900 border-red-200',
};

interface StatusPillProps {
    status: AppointmentStatus;
    label: string;
}

export default function StatusPill({ status, label }: StatusPillProps) {
    return (
        <span
            className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium ${TINTS[status]}`}
        >
            {label}
        </span>
    );
}
