import PublicLayout from '@/Layouts/PublicLayout';
import { ButtonLink } from '@/Components/Button';
import { Container, SectionHeading } from '@/Components/Section';
import type { PageProps, SalonHourRow } from '@/types';

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function formatTime(value: string): string {
    const [hourText, minuteText] = value.split(':');
    const hour = Number(hourText);
    const suffix = hour >= 12 ? 'pm' : 'am';
    const display = hour % 12 === 0 ? 12 : hour % 12;

    return minuteText === '00' ? `${display}${suffix}` : `${display}:${minuteText}${suffix}`;
}

export default function Contact({ hours }: PageProps<{ hours: SalonHourRow[] }>) {
    const today = new Date().getDay();

    return (
        <PublicLayout title="Contact" description="Opening hours, address, and how to reach the salon.">
            <section className="border-b border-line bg-surface">
                <Container className="py-16 sm:py-20">
                    <SectionHeading
                        eyebrow="Contact"
                        title="Find us, or just book online"
                        description="The fastest way to get a slot is to book directly. For anything else, call or email and we will come back to you."
                    />
                </Container>
            </section>

            <Container className="grid gap-12 py-16 lg:grid-cols-2">
                <div className="space-y-10">
                    <div>
                        <h2 className="text-xl text-ink">Visit</h2>
                        <address className="mt-4 space-y-1 text-base text-ink-muted not-italic">
                            <p>12 Camia Street</p>
                            <p>Quezon City, Metro Manila</p>
                        </address>
                    </div>

                    <div>
                        <h2 className="text-xl text-ink">Talk to us</h2>
                        <ul className="mt-4 space-y-3 text-base">
                            <li>
                                <span className="text-ink-muted">Phone: </span>
                                <a
                                    href="tel:+6320000000"
                                    className="text-secondary underline underline-offset-4 hover:text-secondary-hover"
                                >
                                    (02) 8000 0000
                                </a>
                            </li>
                            <li>
                                <span className="text-ink-muted">Email: </span>
                                <a
                                    href="mailto:hello@salon.test"
                                    className="text-secondary underline underline-offset-4 hover:text-secondary-hover"
                                >
                                    hello@salon.test
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div className="rounded-2xl border border-line-strong bg-surface p-7">
                        <h2 className="text-lg text-ink">Need to change an appointment?</h2>
                        <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                            Sign in to your account to reschedule or cancel, subject to the salon&rsquo;s notice
                            period.
                        </p>
                        <div className="mt-5 flex flex-wrap gap-3">
                            <ButtonLink href="/login" variant="secondary">
                                Log in
                            </ButtonLink>
                            <ButtonLink href="/book">Book an appointment</ButtonLink>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 className="text-xl text-ink">Opening hours</h2>

                    <table className="mt-4 w-full text-left text-base">
                        <caption className="sr-only">Salon opening hours by day of the week</caption>
                        <thead className="sr-only">
                            <tr>
                                <th scope="col">Day</th>
                                <th scope="col">Hours</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {hours.map((row) => {
                                const isToday = row.day_of_week === today;

                                return (
                                    <tr key={row.day_of_week} className={isToday ? 'bg-surface' : undefined}>
                                        <th
                                            scope="row"
                                            className="py-3.5 pr-4 pl-3 font-normal text-ink"
                                        >
                                            {DAY_NAMES[row.day_of_week]}
                                            {/* Today is marked with a word, not only a
                                                background tint, so the cue is not colour-only. */}
                                            {isToday && (
                                                <span className="ml-2 text-xs tracking-wide text-secondary uppercase">
                                                    Today
                                                </span>
                                            )}
                                        </th>
                                        <td className="py-3.5 pr-3 text-right text-ink-muted">
                                            {row.is_closed || !row.opens_at || !row.closes_at
                                                ? 'Closed'
                                                : `${formatTime(row.opens_at)} – ${formatTime(row.closes_at)}`}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>

                    {hours.length === 0 && (
                        <p className="mt-4 rounded-2xl border border-dashed border-line-strong bg-surface p-8 text-center text-ink-muted">
                            Opening hours have not been published yet.
                        </p>
                    )}
                </div>
            </Container>
        </PublicLayout>
    );
}
