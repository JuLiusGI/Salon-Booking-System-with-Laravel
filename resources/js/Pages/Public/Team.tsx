import PublicLayout from '@/Layouts/PublicLayout';
import { ButtonLink } from '@/Components/Button';
import { Container, SectionHeading } from '@/Components/Section';
import type { PageProps, PublicStaff } from '@/types';

export default function Team({ staff }: PageProps<{ staff: PublicStaff[] }>) {
    return (
        <PublicLayout
            title="Our team"
            description="Meet the stylists, colourists, and therapists you can book at the salon."
        >
            <section className="border-b border-line bg-surface">
                <Container className="py-16 sm:py-20">
                    <SectionHeading
                        eyebrow="The team"
                        title="The people behind the chair"
                        description="Every stylist here can be requested by name when you book."
                    />
                </Container>
            </section>

            <Container className="py-16">
                {staff.length === 0 ? (
                    <p className="rounded-2xl border border-dashed border-line-strong bg-surface p-12 text-center text-ink-muted">
                        Our team profiles are being updated. Please check back shortly.
                    </p>
                ) : (
                    <ul className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                        {staff.map((member) => (
                            <li
                                key={member.id}
                                className="flex flex-col rounded-2xl border border-line bg-surface p-8"
                            >
                                <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-canvas font-display text-2xl text-ink">
                                    {member.photo_url ? (
                                        <img
                                            src={member.photo_url}
                                            alt={`${member.name}, ${member.title ?? 'salon team member'}`}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <span aria-hidden="true">{member.name.charAt(0)}</span>
                                    )}
                                </div>

                                <h2 className="mt-6 text-xl text-ink">{member.name}</h2>

                                {member.title && (
                                    <p className="mt-1 text-sm font-medium tracking-wide text-secondary">
                                        {member.title}
                                    </p>
                                )}

                                {member.bio && (
                                    <p className="mt-4 text-sm leading-relaxed text-ink-muted">{member.bio}</p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Container>

            <Container className="pb-20 text-center">
                <ButtonLink href="/book" size="lg">
                    Book with one of the team
                </ButtonLink>
            </Container>
        </PublicLayout>
    );
}
