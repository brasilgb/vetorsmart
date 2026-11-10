/**
 * Original vector illustration of an electrician checking a visit on his phone. It is drawn for
 * this project, not photographed, so it carries no third-party licence. Decorative: the text
 * alternative is the figure's label.
 */
export function ElectricianAppIllustration() {
    return (
        <figure className="mx-auto w-full max-w-md">
            <svg viewBox="0 0 480 420" role="img" aria-label="Ilustração de um eletricista consultando o aplicativo VetorSmart no celular" className="h-auto w-full">
                <rect x="0" y="0" width="480" height="420" rx="28" fill="#EFF6FF" />
                <ellipse cx="240" cy="402" rx="170" ry="12" fill="#CBD5E1" />

                {/* Left arm, hanging, with hand */}
                <rect x="152" y="200" width="30" height="110" rx="15" fill="#2563EB" />
                <circle cx="167" cy="318" r="13" fill="#F1C7A0" />

                {/* Torso, belt and overalls */}
                <rect x="180" y="190" width="100" height="120" rx="28" fill="#2563EB" />
                <rect x="186" y="262" width="88" height="60" rx="10" fill="#1E3A5F" />
                <rect x="182" y="286" width="96" height="14" rx="4" fill="#92400E" />
                <rect x="260" y="290" width="18" height="22" rx="3" fill="#78350F" />
                <rect x="194" y="236" width="8" height="40" rx="3" fill="#1E3A5F" />
                <rect x="258" y="236" width="8" height="40" rx="3" fill="#1E3A5F" />

                {/* Legs and boots */}
                <rect x="192" y="300" width="34" height="96" rx="10" fill="#1E3A5F" />
                <rect x="240" y="300" width="34" height="96" rx="10" fill="#1E3A5F" />
                <rect x="186" y="392" width="44" height="16" rx="6" fill="#0B1220" />
                <rect x="236" y="392" width="44" height="16" rx="6" fill="#0B1220" />

                {/* Raised right arm holding the phone */}
                <path d="M268 222 Q296 226 322 210" stroke="#2563EB" strokeWidth="24" strokeLinecap="round" fill="none" />
                <circle cx="324" cy="208" r="12" fill="#F1C7A0" />

                {/* Head, helmet and face */}
                <circle cx="230" cy="140" r="40" fill="#F1C7A0" />
                <path d="M186 132 Q188 84 230 84 Q272 84 274 132 Z" fill="#FACC15" />
                <rect x="178" y="124" width="104" height="12" rx="6" fill="#EAB308" />
                <circle cx="218" cy="144" r="3" fill="#0B1220" />
                <circle cx="242" cy="144" r="3" fill="#0B1220" />
                <path d="M220 160 Q230 168 240 160" stroke="#0B1220" strokeWidth="2.5" strokeLinecap="round" fill="none" />

                {/* Phone with the app screen */}
                <rect x="326" y="118" width="84" height="150" rx="14" fill="#0F172A" />
                <rect x="334" y="130" width="68" height="126" rx="8" fill="#F8FAFC" />
                <rect x="334" y="130" width="68" height="22" rx="8" fill="#2563EB" />
                <circle cx="344" cy="170" r="5" fill="#22C55E" />
                <rect x="354" y="167" width="40" height="6" rx="3" fill="#CBD5E1" />
                <circle cx="344" cy="188" r="5" fill="#22C55E" />
                <rect x="354" y="185" width="34" height="6" rx="3" fill="#CBD5E1" />
                <circle cx="344" cy="206" r="5" fill="#CBD5E1" />
                <rect x="354" y="203" width="38" height="6" rx="3" fill="#CBD5E1" />
                <rect x="340" y="224" width="56" height="22" rx="5" fill="#DBEAFE" />

                {/* Status card floating above the phone */}
                <rect x="40" y="200" width="150" height="58" rx="14" fill="#FFFFFF" stroke="#CBD5E1" />
                <circle cx="68" cy="229" r="13" fill="#DCFCE7" />
                <path d="M62 229 L66 233 L74 225" stroke="#166534" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" fill="none" />
                <text x="90" y="226" fontSize="15" fontWeight="600" fill="#0B1220" fontFamily="inherit">Agendado</text>
                <text x="90" y="243" fontSize="11" fill="#475569" fontFamily="inherit">Visita confirmada</text>
            </svg>
        </figure>
    );
}
