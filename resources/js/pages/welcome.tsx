import { Head } from '@inertiajs/react';
import { AiAssistantSection } from '@/components/landing/ai-assistant-section';
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
 * Public commercial page, following the Stitch "VetorSmart — Landing Page Oficial" screens with the
 * hero imagery kept. Composition only: every block lives in components/landing.
 */
export default function Welcome({ segments }: Props) {
    return (
        <>
            <Head title="VetorSmart — Seu negócio mais organizado" />

            <div className="flex min-h-dvh flex-col overflow-x-clip bg-vs-canvas text-vs-text antialiased">
                <LandingHeader />

                <main className="flex-1">
                    <HeroSection />

                    <SectionShell
                        id="funcionalidades"
                        tone="canvas"
                        eyebrow="Recursos essenciais"
                        title="Tudo o que sua empresa precisa em um único lugar."
                        lead="Projetado para simplificar o dia a dia e gerar novos negócios sem complexidade. Recursos em desenvolvimento estão identificados em cada card."
                    >
                        <FeatureVisualGrid />
                    </SectionShell>

                    <JourneySection />

                    <AiAssistantSection />

                    <SegmentMosaic segments={segments} />

                    <GrowthSection />

                    <SectionShell
                        id="planos"
                        tone="tint"
                        eyebrow="Sem surpresas"
                        title="Planos simples e transparentes."
                        lead="Sem taxas escondidas. Cada recurso mostra se já está disponível ou em desenvolvimento."
                    >
                        <PricingSection />
                    </SectionShell>

                    <SectionShell
                        id="faq"
                        tone="canvas"
                        eyebrow="Tire suas dúvidas"
                        title="Perguntas frequentes"
                        lead="Respostas diretas sobre como o VetorSmart funciona hoje."
                    >
                        <FaqSection />
                    </SectionShell>

                    <FinalCtaSection />
                </main>

                <LandingFooter />
            </div>
        </>
    );
}
