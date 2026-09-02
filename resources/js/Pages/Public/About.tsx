import PublicLayout from '@/Layouts/PublicLayout';
import { ButtonLink } from '@/Components/Button';
import { Container, Rule, SectionHeading } from '@/Components/Section';

const VALUES = [
    {
        title: 'Time you can rely on',
        body: 'We block the real duration of every service, so appointments start when they say they will.',
    },
    {
        title: 'Consultation first',
        body: 'Colour and treatment work starts with a conversation about your hair, not a fixed formula.',
    },
    {
        title: 'Products that suit you',
        body: 'We keep a small, considered range and tell you honestly when you do not need something.',
    },
];

export default function About() {
    return (
        <PublicLayout
            title="About"
            description="A neighbourhood salon built around unhurried appointments and stylists who stay."
        >
            <section className="border-b border-line bg-surface">
                <Container className="py-16 sm:py-20">
                    <SectionHeading
                        level={1}
                        eyebrow="About us"
                        title="A small salon that runs on time"
                        description="We opened with one idea: an appointment should feel unhurried, and it should start when it was booked to start."
                    />
                </Container>
            </section>

            <Container className="grid gap-14 py-16 lg:grid-cols-2 lg:py-20">
                <div className="space-y-5 text-base leading-relaxed text-ink-muted">
                    <p>
                        The salon began as two chairs and a standing Saturday queue. What kept people coming back was
                        not a trend or a treatment, but the fact that we ran to time and remembered what they liked.
                    </p>
                    <p>
                        As we grew, the diary got harder to hold in one head. Double bookings crept in, and the
                        unhurried feeling started to slip. So we built the booking system this site runs on: it checks
                        the real schedule, the real breaks, and the real closures before it offers you a slot.
                    </p>
                    <p>
                        Today the team covers hair, colour, nails, skin, and spa treatments. Every stylist lists the
                        services they are trained in, so when you request someone by name, the system already knows
                        whether they can do the work.
                    </p>
                </div>

                <aside className="rounded-3xl border border-line-strong bg-surface p-9">
                    <h2 className="font-display text-2xl text-ink">What we hold to</h2>
                    <ul className="mt-8 space-y-8">
                        {VALUES.map((value) => (
                            <li key={value.title}>
                                <Rule />
                                <h3 className="mt-4 text-lg text-ink">{value.title}</h3>
                                <p className="mt-2 text-sm leading-relaxed text-ink-muted">{value.body}</p>
                            </li>
                        ))}
                    </ul>
                </aside>
            </Container>

            <section className="bg-primary py-16 text-ink-inverted">
                <Container className="flex flex-col items-center justify-between gap-6 text-center sm:flex-row sm:text-left">
                    <div>
                        <h2 className="text-2xl">Come and see for yourself.</h2>
                        <p className="mt-2 text-ink-inverted/75">We will keep your preferences on file for next time.</p>
                    </div>
                    <ButtonLink href="/book" variant="secondary" size="lg">
                        Book an appointment
                    </ButtonLink>
                </Container>
            </section>
        </PublicLayout>
    );
}
