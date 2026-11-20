{{--
    Default main image of a segment, used while the company has no photo of its own: an illustration drawn inline with
    the segment icon and the palette tone (no external file, no stock photo presented as the company's work).
--}}
<svg class="hero-image hero-default" viewBox="0 0 640 420" role="img" aria-label="Ilustração de {{ $segment ?: 'serviços' }}" focusable="false" preserveAspectRatio="xMidYMid slice">
    <rect width="640" height="420" rx="24" fill="#FFFFFF"/>
    <rect x="0" y="0" width="640" height="420" rx="24" fill="{{ $accent }}" opacity=".08"/>
    <circle cx="520" cy="80" r="120" fill="{{ $accent }}" opacity=".10"/>
    <circle cx="110" cy="370" r="150" fill="{{ $accent }}" opacity=".07"/>
    <rect x="70" y="70" width="230" height="150" rx="18" fill="#FFFFFF" stroke="{{ $accent }}" stroke-opacity=".25" stroke-width="2"/>
    <rect x="95" y="100" width="120" height="12" rx="6" fill="{{ $accent }}" opacity=".55"/>
    <rect x="95" y="126" width="170" height="10" rx="5" fill="#94A3B8" opacity=".55"/>
    <rect x="95" y="148" width="140" height="10" rx="5" fill="#94A3B8" opacity=".45"/>
    <rect x="95" y="178" width="90" height="22" rx="11" fill="{{ $accent }}" opacity=".85"/>
    <g transform="translate(330 120) scale(11)" fill="none" stroke="{{ $accent }}" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
        <path d="{{ $icon }}"/>
    </g>
    <rect x="70" y="250" width="160" height="96" rx="16" fill="#FFFFFF" stroke="{{ $accent }}" stroke-opacity=".2" stroke-width="2"/>
    <path d="M100 300l18 18 34-38" fill="none" stroke="{{ $accent }}" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
