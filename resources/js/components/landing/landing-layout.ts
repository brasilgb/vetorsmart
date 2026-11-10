/** Shared layout and control classes for the public landing. One place, so every section stays aligned. */

/** Central container, 1280 px maximum, with a 20 px gutter on phones and 32 px from tablet up. */
export const landingContainer = 'mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8';

/** Vertical rhythm shared by every section. */
export const landingSectionSpacing = 'py-20 lg:py-28';

/** Visible keyboard focus on light and navy surfaces. */
export const landingFocusRing = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vs-blue';

const landingButtonBase = `inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3.5 text-sm font-semibold transition-colors ${landingFocusRing}`;

export const landingButtonPrimary = `${landingButtonBase} bg-vs-blue text-white shadow-sm hover:bg-vs-blue-hover`;

export const landingButtonSecondary = `${landingButtonBase} border border-vs-line bg-white text-vs-ink hover:bg-vs-surface`;

export const landingButtonOnDark = `${landingButtonBase} border border-white/20 text-white hover:bg-white/10`;
