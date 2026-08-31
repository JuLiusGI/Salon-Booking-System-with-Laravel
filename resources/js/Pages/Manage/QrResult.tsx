import AppLayout from '@/Layouts/AppLayout';
import { ButtonLink } from '@/Components/Button';
import { ArrivalCard } from '@/Pages/Manage/CheckIn';
import type { Arrival } from '@/Pages/Manage/CheckIn';
import type { PageProps } from '@/types';

interface QrResultProps {
    found: Arrival | null;
    problem: string | null;
}

/**
 * Where a scanned code lands.
 *
 * An unrecognised code and a code for an appointment this user may not see give
 * the same answer, so scanning cannot be used to probe for valid codes.
 */
export default function QrResult({ found, problem }: PageProps<QrResultProps>) {
    return (
        <AppLayout title="Scanned code">
            <div className="max-w-xl space-y-5">
                {problem && (
                    <div
                        role="alert"
                        className="rounded-2xl border border-line-strong bg-canvas-soft px-5 py-4 text-sm text-ink"
                    >
                        {problem}
                    </div>
                )}

                {found ? (
                    <ArrivalCard arrival={found} highlight />
                ) : (
                    <div className="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center">
                        <h2 className="text-lg text-ink">Nothing to show</h2>
                        <p className="mx-auto mt-2 max-w-sm text-sm text-ink-muted">
                            Look the appointment up by reference instead. Every appointment can be found without a
                            code.
                        </p>
                    </div>
                )}

                <div className="flex flex-wrap gap-3">
                    <ButtonLink href="/manage/check-in" variant="secondary">
                        Back to check in
                    </ButtonLink>
                    <ButtonLink href="/manage/calendar" variant="ghost">
                        Calendar
                    </ButtonLink>
                </div>
            </div>
        </AppLayout>
    );
}
