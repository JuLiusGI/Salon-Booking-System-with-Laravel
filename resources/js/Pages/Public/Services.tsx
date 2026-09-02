import PublicLayout from '@/Layouts/PublicLayout';
import { ButtonLink } from '@/Components/Button';
import { Container, SectionHeading } from '@/Components/Section';
import type { PageProps, PublicCategory } from '@/types';

const peso = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 0,
});

function duration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`;
}

export default function Services({ categories }: PageProps<{ categories: PublicCategory[] }>) {
    return (
        <PublicLayout
            title="Services"
            description="Browse every hair, nail, skin, and spa service with its real duration and price."
        >
            <section className="border-b border-line bg-surface">
                <Container className="py-16 sm:py-20">
                    <SectionHeading
                        level={1}
                        eyebrow="Service menu"
                        title="Every service, with the time and price it actually takes"
                        description="Durations are what we block out in the diary, so the slot you book is the slot you get."
                    />
                </Container>
            </section>

            {categories.length === 0 ? (
                <Container className="py-20">
                    <p className="rounded-2xl border border-dashed border-line-strong bg-surface p-12 text-center text-ink-muted">
                        No services are published yet. Please check back shortly.
                    </p>
                </Container>
            ) : (
                <>
                    {/* In-page jump list, useful once the menu is long. */}
                    <Container className="pt-12">
                        <nav aria-label="Service categories">
                            <ul className="flex flex-wrap gap-2">
                                {categories.map((category) => (
                                    <li key={category.id}>
                                        <a
                                            href={`#${category.slug}`}
                                            className="inline-block rounded-full border border-line-strong bg-surface px-4 py-2 text-sm text-ink transition-colors hover:bg-canvas-soft"
                                        >
                                            {category.name}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    </Container>

                    <Container className="space-y-16 py-12">
                        {categories.map((category) => (
                            <section key={category.id} id={category.slug} aria-labelledby={`${category.slug}-title`}>
                                <div className="border-b border-line pb-5">
                                    <h2 id={`${category.slug}-title`} className="text-2xl text-ink sm:text-3xl">
                                        {category.name}
                                    </h2>
                                    {category.description && (
                                        <p className="mt-2 text-sm text-ink-muted">{category.description}</p>
                                    )}
                                </div>

                                <ul className="mt-2 divide-y divide-line">
                                    {category.services.map((service) => (
                                        <li
                                            key={service.id}
                                            className="flex flex-col gap-3 py-6 sm:flex-row sm:items-baseline sm:justify-between sm:gap-8"
                                        >
                                            <div className="max-w-xl">
                                                <h3 className="text-lg text-ink">{service.name}</h3>
                                                {service.description && (
                                                    <p className="mt-1.5 text-sm leading-relaxed text-ink-muted">
                                                        {service.description}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex shrink-0 items-baseline gap-6 sm:justify-end">
                                                <span className="text-sm text-ink-muted">
                                                    {duration(service.duration_minutes)}
                                                </span>
                                                <span className="font-display text-xl text-ink">
                                                    {peso.format(Number(service.price))}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </Container>
                </>
            )}

            <section className="bg-primary py-16 text-ink-inverted">
                <Container className="flex flex-col items-center justify-between gap-6 text-center sm:flex-row sm:text-left">
                    <div>
                        <h2 className="text-2xl">Found what you need?</h2>
                        <p className="mt-2 text-ink-inverted/75">
                            Pick more than one service and we will block the full time for you.
                        </p>
                    </div>
                    <ButtonLink href="/book" variant="secondary" size="lg">
                        Book an appointment
                    </ButtonLink>
                </Container>
            </section>
        </PublicLayout>
    );
}
