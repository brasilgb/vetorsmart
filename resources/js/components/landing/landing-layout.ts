/** Shared layout and control classes for the public landing. One place, so every section stays aligned. */

/** Central container, 1280 px maximum, with a 20 px gutter on phones and 48 px on desktop (Stitch reference). */
export const landingContainer = 'mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-12';

/** Vertical rhythm shared by every section. */
export const landingSectionSpacing = 'py-16 lg:py-[4.5rem]';

/** Visible keyboard focus on light and navy surfaces. */
export const landingFocusRing = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vs-blue';

/** White card with a hairline border and soft shadow, the base surface of every Stitch card. */
export const landingCard = 'rounded-xl border border-vs-line bg-white shadow-sm';

const landingButtonBase = `inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3.5 text-sm font-semibold transition-all active:scale-[0.99] ${landingFocusRing}`;

export const landingButtonPrimary = `${landingButtonBase} bg-vs-blue-hover text-white shadow-md hover:bg-vs-ink-blue hover:shadow-lg`;

export const landingButtonSecondary = `${landingButtonBase} border border-vs-line bg-white text-vs-text shadow-sm hover:bg-vs-canvas-blue`;

export const landingButtonOnDark = `${landingButtonBase} border border-white/20 text-white hover:bg-white/10`;
