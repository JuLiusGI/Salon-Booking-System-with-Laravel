import PublicLayout from '@/Layouts/PublicLayout';
import { Container, SectionHeading } from '@/Components/Section';
import type { GalleryItem, PageProps } from '@/types';

export default function Gallery({ images }: PageProps<{ images: GalleryItem[] }>) {
    return (
        <PublicLayout title="Gallery" description="A look inside the salon and the work our team does.">
            <section className="border-b border-line bg-surface">
                <Container className="py-16 sm:py-20">
                    <SectionHeading
                        level={1}
                        eyebrow="Gallery"
                        title="A look around"
                        description="The room, the light, and a little of the work that leaves it."
                    />
                </Container>
            </section>

            <Container className="py-16">
                {images.length === 0 ? (
                    <p className="rounded-2xl border border-dashed border-line-strong bg-surface p-12 text-center text-ink-muted">
                        There are no photos in the gallery yet.
                    </p>
                ) : (
                    <ul className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {images.map((image) => (
                            <li
                                key={image.id}
                                className="overflow-hidden rounded-2xl border border-line bg-canvas-soft"
                            >
                                {/*
                                 * Image files are uploaded through the admin interface in a
                                 * later phase. Until a file exists, this renders the caption
                                 * in a correctly proportioned frame rather than a broken
                                 * image, so the layout is already correct.
                                 */}
                                <div className="flex aspect-4/3 items-center justify-center p-8 text-center">
                                    <span className="text-sm text-ink-muted">
                                        {image.alt_text ?? image.title ?? 'Salon photograph'}
                                    </span>
                                </div>

                                {image.title && (
                                    <p className="border-t border-line bg-surface px-5 py-3 text-sm text-ink">
                                        {image.title}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Container>
        </PublicLayout>
    );
}
