import { Head } from '@inertiajs/react';
import { FaqSection } from '@/components/landing/faq-section';
import { FeatureVisualGrid } from '@/components/landing/feature-visual-grid';
import { FinalCtaSection } from '@/components/landing/final-cta-section';
import { GrowthSection } from '@/components/landing/growth-section';
import { HeroSection } from '@/components/landing/hero-section';
import { JourneySection } from '@/components/landing/journey-section';
import { LandingFooter } from '@/components/landing/landing-footer';
import { LandingHeader } from '@/components/landing/landing-header';
import { PricingSection } from '@/components/landing/pricing-section';
import { SectionShell } from '@/components/landing/section-shell';
import { SegmentMosaic, type LandingSegment } from '@/components/landing/segment-mosaic';

type Props = { segments: LandingSegment[] };

/**
 * Public commercial page. Composition only: every block lives in components/landing. Sections
 * alternate between white and the soft surface so the page reads as one sequence.
 */
export default function Welcome({ segments }: Props) {
    return (
        <>
            <Head title="VetorSmart — Seu crescimento é o nosso propósito" />

            <div className="flex min-h-dvh flex-col overflow-x-clip bg-white text-vs-text antialiased">
                <LandingHeader />

                <main className="flex-1">
                    <HeroSection />

                    <SegmentMosaic segments={segments} />

                    <SectionShell
                        id="funcionalidades"
                        eyebrow="Funcionalidades"
                        title="Tudo o que sua empresa precisa para crescer."
                        lead="Da presença digital ao relacionamento com seus clientes, uma plataforma para simplificar sua rotina e impulsionar seus resultados. Recursos em desenvolvimento estão identificados em cada card."
                    >
                        <FeatureVisualGrid />
                    </SectionShell>

                    <JourneySection />

                    <GrowthSection />

                    <SectionShell
                        id="planos"
                        tone="surface"
                        eyebrow="Planos"
                        title="Planos e preços"
                        lead="Recursos marcados como em desenvolvimento ainda não estão disponíveis."
                    >
                        <PricingSection />
                    </SectionShell>

                    <SectionShell id="faq" eyebrow="Dúvidas" title="Perguntas frequentes">
                        <FaqSection />
                    </SectionShell>

                    <FinalCtaSection />
                </main>

                <LandingFooter />
            </div>
        </>
    );
}
