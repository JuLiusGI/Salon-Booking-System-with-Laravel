import { Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import { ButtonLink } from '@/Components/Button';
import { Container, Rule, SectionHeading } from '@/Components/Section';
import type { GalleryItem, PageProps, PublicCategory, PublicStaff } from '@/types';

interface HomeProps {
    categories: PublicCategory[];
    staff: PublicStaff[];
    gallery: GalleryItem[];
}

const PROMISES = [
    {
        title: 'Book in under a minute',
        body: 'Pick your services, choose a stylist, and see the times that are genuinely free.',
    },
    {
        title: 'The stylist you asked for',
        body: 'Every stylist lists the services they are trained in, so you are never double-guessed.',
    },
    {
        title: 'No overlapping bookings',
        body: 'Availability is checked against live schedules, breaks, and closures before you confirm.',
    },
];

export default function Home({ categories, staff, gallery }: PageProps<HomeProps>) {
    return (
        <PublicLayout
            title="Salon Booking"
            description="Book hair, nail, and skin appointments with the stylist you want, at a time that is actually free."
        >
            {/* Hero */}
            <section className="border-b border-line">
                <Container className="grid items-center gap-12 py-20 lg:grid-cols-2 lg:py-28">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.18em] text-secondary uppercase">
                            Hair &middot; Nails &middot; Skin &middot; Spa
                        </p>

                        <h1 className="mt-5 text-4xl leading-[1.1] text-ink sm:text-5xl lg:text-6xl">
                            Time for yourself, booked without the back and forth.
                        </h1>

                        <p className="mt-6 max-w-xl text-lg leading-relaxed text-ink-muted">
                            Choose your services, pick the stylist you trust, and confirm a slot that is genuinely
                            open. No phone tag, no waiting to hear back.
                        </p>

                        <div className="mt-9 flex flex-wrap items-center gap-4">
                            <ButtonLink href="/book" size="lg">
                                Book an appointment
                            </ButtonLink>
                            <ButtonLink href="/services" variant="secondary" size="lg">
                                Browse services
                            </ButtonLink>
                        </div>
                    </div>

                    {/* Decorative composition. Marked aria-hidden because it carries
                        no information a screen reader user would miss. */}
                    <div aria-hidden="true" className="relative hidden lg:block">
                        <div className="absolute -top-6 -left-6 h-64 w-64 rounded-full bg-accent/35" />
                        <div className="absolute right-4 -bottom-10 h-40 w-40 rounded-full bg-support/30" />
                        <div className="relative rounded-3xl border border-line-strong bg-surface p-10 shadow-sm">
                            <p className="font-display text-2xl text-ink">Open Tuesday to Sunday</p>
                            <p className="mt-3 text-sm text-ink-muted">
                                Late nights on Thursday and Friday until 8pm.
                            </p>
                            <span className="mt-8 block h-px w-full bg-line" />
                            <dl className="mt-8 grid grid-cols-2 gap-6">
                                <div>
                                    <dt className="text-xs tracking-wide text-ink-muted uppercase">Stylists</dt>
                                    <dd className="mt-1 font-display text-3xl text-ink">{staff.length || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs tracking-wide text-ink-muted uppercase">Services</dt>
                                    <dd className="mt-1 font-display text-3xl text-ink">
                                        {categories.reduce((total, category) => total + category.services.length, 0) ||
                                            '—'}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </Container>
            </section>

            {/* Promises */}
            <section className="bg-surface">
                <Container className="py-20">
                    <ul className="grid gap-10 md:grid-cols-3">
                        {PROMISES.map((promise) => (
                            <li key={promise.title}>
                                <Rule />
                                <h2 className="mt-5 text-xl text-ink">{promise.title}</h2>
                                <p className="mt-3 text-sm leading-relaxed text-ink-muted">{promise.body}</p>
                            </li>
                        ))}
                    </ul>
                </Container>
            </section>

            {/* Services preview */}
            <section className="py-20">
                <Container>
                    <div className="flex flex-wrap items-end justify-between gap-6">
                        <SectionHeading
                            eyebrow="What we do"
                            title="Services for every kind of appointment"
                            description="From a standing trim to a full colour transformation, each service lists its real duration and price."
                        />
                        <ButtonLink href="/services" variant="secondary">
                            See all services
                        </ButtonLink>
                    </div>

                    {categories.length === 0 ? (
                        <p className="mt-12 rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-ink-muted">
                            Our service menu is being updated. Please check back shortly.
                        </p>
                    ) : (
                        <ul className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {categories.map((category) => (
                                <li
                                    key={category.id}
                                    className="rounded-2xl border border-line bg-surface p-7 transition-colors hover:border-line-strong"
                                >
                                    <h3 className="text-xl text-ink">{category.name}</h3>
                                    <p className="mt-2 text-sm text-ink-muted">
                                        {category.services.length} service
                                        {category.services.length === 1 ? '' : 's'}
                                    </p>
                                    <ul className="mt-5 space-y-2 text-sm text-ink-muted">
                                        {category.services.slice(0, 3).map((service) => (
                                            <li key={service.id}>{service.name}</li>
                                        ))}
                                    </ul>
                                    <Link
                                        href="/services"
                                        className="mt-6 inline-block text-sm text-secondary underline underline-offset-4 hover:text-secondary-hover"
                                    >
                                        View {category.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Container>
            </section>

            {/* Team preview */}
            {staff.length > 0 && (
                <section className="bg-surface py-20">
                    <Container>
                        <SectionHeading
                            eyebrow="Who you will see"
                            title="Stylists who know their craft"
                            description="Book the person you already trust, or let us match you with someone who specialises in what you need."
                            align="center"
                        />

                        <ul className="mx-auto mt-14 grid max-w-4xl gap-8 sm:grid-cols-3">
                            {staff.map((member) => (
                                <li key={member.id} className="text-center">
                                    <div
                                        aria-hidden="true"
                                        className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-canvas font-display text-2xl text-ink"
                                    >
                                        {member.name.charAt(0)}
                                    </div>
                                    <h3 className="mt-5 text-lg text-ink">{member.name}</h3>
                                    {member.title && <p className="mt-1 text-sm text-ink-muted">{member.title}</p>}
                                </li>
                            ))}
                        </ul>

                        <div className="mt-12 text-center">
                            <ButtonLink href="/team" variant="secondary">
                                Meet the whole team
                            </ButtonLink>
                        </div>
                    </Container>
                </section>
            )}

            {/* Gallery preview */}
            {gallery.length > 0 && (
                <section className="py-20">
                    <Container>
                        <SectionHeading eyebrow="Inside the salon" title="A room worth sitting in" />

                        <ul className="mt-12 grid grid-cols-2 gap-4 md:grid-cols-3">
                            {gallery.map((image, index) => (
                                <li
                                    key={image.id}
                                    className={`overflow-hidden rounded-2xl border border-line bg-canvas-soft ${
                                        index === 0 ? 'col-span-2 md:col-span-1' : ''
                                    }`}
                                >
                                    <div className="flex aspect-4/3 items-center justify-center p-6 text-center">
                                        <span className="text-sm text-ink-muted">{image.title ?? 'Salon photo'}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Container>
                </section>
            )}

            {/* Closing call to action */}
            <section className="bg-primary py-20 text-ink-inverted">
                <Container className="text-center">
                    <h2 className="mx-auto max-w-2xl text-3xl leading-tight sm:text-4xl">
                        Ready when you are.
                    </h2>
                    <p className="mx-auto mt-5 max-w-xl text-ink-inverted/75">
                        Create an account once, then rebook your usual in seconds.
                    </p>
                    <div className="mt-9">
                        <ButtonLink href="/book" variant="secondary" size="lg">
                            Book an appointment
                        </ButtonLink>
                    </div>
                </Container>
            </section>
        </PublicLayout>
    );
}
