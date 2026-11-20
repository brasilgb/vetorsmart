@php
    use App\Services\Websites\SegmentTheme;

    $business = $content['business_name'] ?? '';
    $seo = $content['seo'] ?? [];
    $title = ($seo['title'] ?? null) ?: $business;
    $description = ($seo['description'] ?? null) ?: (($content['about'] ?? null) ?: null);
    $colors = SegmentTheme::colors($content);
    $accent = $colors['accent'];
    // Sem aspas: o valor é impresso com escape dentro de <style>, e um &quot; invalidaria a declaração inteira.
    $fontStack = ($content['font'] ?? 'sans') === 'serif' ? 'Georgia, Times New Roman, serif' : 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
    $contact = $content['contact'] ?? [];
    $phoneDigits = preg_replace('/\D+/', '', (string) ($contact['phone'] ?? ''));
    $whatsDigits = preg_replace('/\D+/', '', (string) ($contact['whatsapp'] ?? ''));
    $dayNames = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $hours = $content['hours'] ?? null;
    $hasHours = is_array($hours) && collect($hours)->filter()->isNotEmpty();
    $page = $page ?? 'home';
    $homolog = $homolog ?? false;
    $online = $online ?? null;
    $links = array_merge(['home' => null, 'services' => null, 'assistant' => null, 'request' => null], $links ?? []);
    $title = $page === 'services' ? 'Serviços · '.$title : $title;
    $theme = $theme ?? SegmentTheme::for($content['segment_slug'] ?? null);
    $mediaBase = $mediaBase ?? null;
    $ogImage = $ogImage ?? null;
    $logo = is_array($content['logo'] ?? null) && $mediaBase ? $content['logo'] : null;
    $hero = is_array($content['hero'] ?? null) && $mediaBase ? $content['hero'] : null;
    $aboutPhoto = is_array($content['about_photo'] ?? null) && $mediaBase ? $content['about_photo'] : null;
    $services = array_values(array_filter((array) ($content['services'] ?? []), 'is_array'));
    $location = is_array($content['location'] ?? null) ? $content['location'] : [];
    $base = is_array($location['base'] ?? null) ? $location['base'] : null;
    $served = array_values(array_filter((array) ($location['served'] ?? []), 'is_array'));
    $mode = $location['mode'] ?? null;
    $legacyRegion = ($content['service_region'] ?? null) ?: null;
    $city = $base['name'] ?? (($contact['city'] ?? null) ?: null);
    $state = $base['state'] ?? (($contact['state'] ?? null) ?: null);
    $place = $city !== null ? $city.($state ? ', '.$state : '') : null;
    $segmentName = ($content['segment'] ?? null) ?: null;
    $heading = ($content['headline'] ?? null) ?: ($segmentName !== null && $place !== null ? $segmentName.' em '.$place : $business);
    $serviceNames = array_map(fn (array $service): string => (string) $service['name'], $services);
    $whatWeDo = $serviceNames === [] ? null : (count($serviceNames) <= 3 ? implode(', ', array_slice($serviceNames, 0, -1)).(count($serviceNames) > 1 ? ' e ' : '').end($serviceNames) : implode(', ', array_slice($serviceNames, 0, 3)).' e outros serviços');
    $whereWeServe = match (true) {
        $place !== null && $served !== [] => 'Sede em '.$place.' · atendemos '.(count($served) === 1 ? 'também '.$served[0]['name'] : (count($served) + 1).' municípios da região'),
        $place !== null && $legacyRegion !== null => 'Sede em '.$place.' · atendemos: '.$legacyRegion,
        $place !== null => 'Sede em '.$place,
        $legacyRegion !== null => 'Atendemos: '.$legacyRegion,
        default => null,
    };
    $modeText = match ($mode) {
        'at_company' => 'Você vem até nós: o atendimento acontece no nosso endereço.',
        'at_customer' => 'Vamos até você: o atendimento acontece no seu endereço.',
        'both' => 'Atendemos no nosso endereço ou vamos até o seu, como preferir.',
        default => null,
    };
    $address = ($contact['address'] ?? null) ?: null;
    $fullAddress = $address !== null ? trim(implode(', ', array_filter([$address, $city, $state]))) : null;
    $mapsUrl = $fullAddress !== null ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($fullAddress) : null;
    $homeAnchor = fn (string $anchor): string => $page === 'home' || $links['home'] === null ? '#'.$anchor : $links['home'].'#'.$anchor;
    $requestLink = fn (?int $serviceId = null): ?string => $links['request'] === null ? null : ($serviceId === null || $links['request'] === '#' ? $links['request'] : $links['request'].(str_contains($links['request'], '?') ? '&' : '?').'servico='.$serviceId);
    $onlineLabel = $online === 'schedule' ? 'Agendar online' : 'Atendimento online';
    $hasContact = filled($contact['phone'] ?? null) || filled($contact['whatsapp'] ?? null) || filled($contact['email'] ?? null);
    $hasAbout = filled($content['about'] ?? null) || ! empty($content['differentials'] ?? []) || ! empty($content['trust_items'] ?? []) || $aboutPhoto !== null;
    $hasArea = $place !== null || $served !== [] || $legacyRegion !== null || $modeText !== null;
    $areaServed = array_values(array_filter([
        ...($base !== null ? [['@type' => 'City', 'name' => $base['name'], 'containedInPlace' => ['@type' => 'State', 'name' => $base['state']]]] : []),
        ...array_map(fn (array $item): array => ['@type' => 'City', 'name' => $item['name'], 'containedInPlace' => ['@type' => 'State', 'name' => $item['state']]], $served),
    ]));
    $openingHours = [];
    if ($hasHours) {
        $schemaDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach (range(0, 6) as $weekday) {
            $day = $hours[(string) $weekday] ?? $hours[$weekday] ?? null;
            if (is_array($day) && isset($day['open'], $day['close'])) {
                $openingHours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $schemaDays[$weekday], 'opens' => $day['open'], 'closes' => $day['close']];
            }
        }
    }
    // Built inside this PHP block: in the template body, Blade would compile the schema.org context key as a directive.
    $structuredData = json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ProfessionalService',
        'name' => $business,
        'description' => $description,
        'telephone' => ($contact['phone'] ?? null) ?: null,
        'email' => ($contact['email'] ?? null) ?: null,
        'url' => $canonical,
        'image' => $ogImage,
        'areaServed' => $areaServed !== [] ? $areaServed : $legacyRegion,
        'address' => $city !== null ? array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressLocality' => $city,
            'addressRegion' => $state,
            'addressCountry' => 'BR',
        ]) : null,
        'openingHoursSpecification' => $openingHours !== [] ? $openingHours : null,
        'hasOfferCatalog' => $services !== [] ? ['@type' => 'OfferCatalog', 'name' => 'Serviços', 'itemListElement' => array_map(fn (array $service): array => ['@type' => 'Offer', 'itemOffered' => array_filter(['@type' => 'Service', 'name' => $service['name'], 'description' => ($service['description'] ?? null) ?: null])], $services)] : null,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $icons = [
        'check' => 'M5 12l5 5 9-10',
        'pin' => 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2',
        'mail' => 'M4 6h16v12H4zM4 7l8 6 8-6',
        'chat' => 'M4 5h16v11H8l-4 4z',
        'badge' => 'M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 15.6 7.1 18.2 8 12.7 4 8.8 9.5 8z',
    ];
    $icon = fn (string $name): string => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="'.e($icons[$name]).'"/></svg>';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($noindex || $preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    <meta name="theme-color" content="{{ $accent }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    @if ($business !== '')
        <meta property="og:site_name" content="{{ $business }}">
    @endif
    <meta property="og:title" content="{{ $title }}">
    @if ($description)
        <meta property="og:description" content="{{ $description }}">
    @endif
    @if ($canonical)
        <meta property="og:url" content="{{ $canonical }}">
    @endif
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    @if (! $noindex && ! $preview && $business !== '')
        <script type="application/ld+json">
        {!! $structuredData !!}
        </script>
    @endif
    <style>
        :root {
            --site-accent: {{ $accent }};
            --site-font: {{ $fontStack }};
            --site-text: #0B1220;
            --site-muted: #475569;
            --site-border: #CBD5E1;
            --site-surface: #F8FAFC;
            --site-tone: {{ $colors['tone'] }};
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
        body { margin: 0; font-family: var(--site-font); color: var(--site-text); background: #fff; line-height: 1.6; font-size: 16px; }
        a { color: var(--site-accent); }
        a:focus-visible, button:focus-visible, summary:focus-visible { outline: 3px solid var(--site-accent); outline-offset: 3px; border-radius: 4px; }
        img { max-width: 100%; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 20px; }
        .icon { width: 20px; height: 20px; flex: 0 0 auto; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .skip { position: absolute; left: -9999px; top: 8px; }
        .skip:focus { left: 16px; background: #fff; color: var(--site-text); padding: 10px 14px; border-radius: 8px; z-index: 20; }
        .preview-banner, .homolog-banner { background: #FEF3C7; color: #78350F; text-align: center; padding: 10px 16px; font-size: .9rem; font-weight: 600; }
        main:focus { outline: none; }

        /* Header */
        header.site { background: #fff; border-bottom: 1px solid var(--site-border); position: sticky; top: 0; z-index: 10; }
        header.site .bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 68px; padding-top: 10px; padding-bottom: 10px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--site-text); text-decoration: none; font-weight: 700; font-size: 1.08rem; min-width: 0; }
        .brand img { height: 44px; width: auto; max-width: 150px; object-fit: contain; }
        .brand span { overflow-wrap: anywhere; }
        nav.site { display: flex; gap: 4px 18px; align-items: center; flex-wrap: wrap; }
        nav.site a { color: var(--site-text); text-decoration: none; font-weight: 500; padding: 10px 2px; white-space: nowrap; }
        nav.site a:hover { color: var(--site-accent); }
        nav.site a[aria-current="page"] { color: var(--site-accent); text-decoration: underline; text-underline-offset: 5px; }
        .cta { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: var(--site-accent); color: #fff; padding: 12px 20px; min-height: 48px; border-radius: 12px; text-decoration: none; font-weight: 700; border: 2px solid var(--site-accent); text-align: center; }
        .cta:hover { filter: brightness(.92); }
        .cta.secondary { background: #fff; color: var(--site-text); border-color: var(--site-border); }
        .cta.secondary:hover { border-color: var(--site-accent); filter: none; }
        header.site .cta { padding: 10px 16px; min-height: 44px; }
        @media (max-width: 860px) {
            header.site .bar { flex-wrap: wrap; }
            nav.site { order: 3; width: 100%; flex-wrap: nowrap; overflow-x: auto; gap: 14px; justify-content: space-between; border-top: 1px solid var(--site-border); padding-top: 2px; scrollbar-width: thin; }
            nav.site a { font-size: .92rem; }
        }
        @media (max-width: 420px) { header.site .cta { padding: 8px 12px; font-size: .92rem; } .brand img { height: 36px; } }

        /* Hero */
        .hero-band { background: var(--site-tone); }
        .hero-band .wrap { display: grid; gap: 28px; align-items: center; padding-top: 48px; padding-bottom: 48px; }
        @media (min-width: 900px) { .hero-band .wrap.split { grid-template-columns: 1.05fr 1fr; padding-top: 64px; padding-bottom: 64px; } }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: .95rem; margin: 0 0 12px; }
        .eyebrow svg { width: 22px; height: 22px; stroke: var(--site-accent); }
        .hero h1 { font-size: clamp(1.85rem, 4.4vw, 2.9rem); line-height: 1.14; margin: 0 0 14px; overflow-wrap: anywhere; }
        .hero .lead { font-size: 1.08rem; margin: 0 0 10px; max-width: 640px; }
        .hero .where { display: flex; align-items: flex-start; gap: 8px; margin: 0 0 22px; font-weight: 600; }
        .hero .where .icon { color: var(--site-accent); margin-top: 3px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .hero-image { width: 100%; height: auto; border-radius: 20px; display: block; object-fit: cover; aspect-ratio: 16 / 11; max-height: 440px; background: var(--site-surface); box-shadow: 0 10px 30px rgba(15, 23, 42, .12); }
        @media (max-width: 640px) { .actions .cta { flex: 1 1 100%; } .hero-band .wrap { padding-top: 32px; padding-bottom: 36px; } }

        /* Sections */
        section.block { padding: 56px 0; }
        section.block.alt { background: var(--site-surface); border-top: 1px solid var(--site-border); border-bottom: 1px solid var(--site-border); }
        section.block h2 { font-size: clamp(1.45rem, 3vw, 1.9rem); margin: 0 0 8px; line-height: 1.2; }
        section.block .intro { color: var(--site-muted); margin: 0 0 24px; max-width: 720px; }
        .about { display: grid; gap: 28px; align-items: start; }
        @media (min-width: 900px) { .about.has-photo { grid-template-columns: 1.2fr 1fr; } }
        .about p.text { white-space: pre-line; margin: 12px 0 18px; }
        .about img { width: 100%; height: auto; border-radius: 18px; object-fit: cover; aspect-ratio: 4 / 3; }
        ul.checks { list-style: none; padding: 0; margin: 0 0 18px; display: grid; gap: 10px; }
        ul.checks li { display: flex; gap: 10px; align-items: flex-start; }
        ul.checks .icon { color: var(--site-accent); margin-top: 3px; }
        ul.trust { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); }
        ul.trust li { background: #fff; border: 1px solid var(--site-border); border-radius: 14px; padding: 14px 16px; display: flex; gap: 10px; }
        ul.trust .icon { color: var(--site-accent); margin-top: 3px; }
        ul.trust strong { display: block; }
        ul.trust small { color: var(--site-muted); font-size: .88rem; }

        /* Services */
        .services { display: grid; gap: 18px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); }
        .services.single { grid-template-columns: minmax(0, 620px); }
        .service { background: #fff; border: 1px solid var(--site-border); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; }
        .service img { width: 100%; height: auto; aspect-ratio: 4 / 3; object-fit: cover; display: block; background: var(--site-surface); }
        .service .body { padding: 18px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .service h3 { margin: 0; font-size: 1.12rem; line-height: 1.3; overflow-wrap: anywhere; }
        .service p { margin: 0; color: var(--site-muted); white-space: pre-line; }
        .service .meta { display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: baseline; margin-top: auto; padding-top: 6px; }
        .service .price { color: var(--site-text); font-weight: 700; }
        .service .duration { color: var(--site-muted); font-size: .92rem; }
        .service .cta { margin-top: 10px; width: 100%; }

        /* Area */
        .area { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); margin-top: 16px; }
        .area .panel { background: #fff; border: 1px solid var(--site-border); border-radius: 16px; padding: 20px; }
        .area h3 { margin: 0 0 8px; font-size: 1.05rem; }
        .area p { margin: 0; }
        ul.pills { list-style: none; padding: 0; margin: 10px 0 0; display: flex; flex-wrap: wrap; gap: 8px; }
        ul.pills li { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 999px; background: var(--site-tone); font-size: .94rem; }
        ul.pills li.base { font-weight: 700; }
        ul.pills .icon { width: 16px; height: 16px; color: var(--site-accent); }

        ul.plain { list-style: none; padding: 0; margin: 12px 0 0; }
        ul.plain li { padding: 6px 0; border-bottom: 1px dashed var(--site-border); }

        /* Footer */
        footer.site { background: #0F172A; color: #E2E8F0; padding: 48px 0 28px; }
        footer.site a { color: #BFDBFE; }
        footer.site h2 { font-size: 1.35rem; margin: 0 0 18px; color: #fff; }
        footer.site h3 { font-size: 1rem; margin: 0 0 10px; color: #fff; }
        footer.site .cols { display: grid; gap: 28px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); }
        footer.site ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        footer.site li { display: flex; gap: 8px; align-items: flex-start; overflow-wrap: anywhere; }
        footer.site li .icon { margin-top: 3px; color: #93C5FD; }
        footer.site table { border-collapse: collapse; font-size: .95rem; }
        footer.site td { padding: 2px 14px 2px 0; vertical-align: top; }
        footer.site .legal { border-top: 1px solid #334155; margin-top: 32px; padding-top: 18px; color: #CBD5E1; font-size: .9rem; display: flex; flex-wrap: wrap; gap: 8px 20px; justify-content: space-between; }
    </style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>
@if ($preview)
    <div class="preview-banner">Prévia do rascunho. Esta versão ainda não está publicada para os visitantes.</div>
@endif

@if ($homolog)
    <div class="homolog-banner" role="note">Ambiente de teste da VetorSmart. Esta não é uma empresa real: os dados são fictícios e nenhum pedido feito aqui será atendido.</div>
@endif

<header class="site">
    <div class="wrap bar">
        <a class="brand" href="{{ $links['home'] ?? '#' }}">
            @if ($logo)
                <img src="{{ $mediaBase.$logo['file'] }}" alt="Logomarca de {{ $business }}" width="{{ $logo['width'] ?: '' }}" height="{{ $logo['height'] ?: '' }}">
            @endif
            <span>{{ $business }}</span>
        </a>
        <nav class="site" aria-label="Navegação do site">
            @if ($services !== [])
                <a href="{{ $page === 'services' ? '#servicos' : $homeAnchor('servicos') }}" @if ($page === 'services') aria-current="page" @endif>Serviços</a>
            @endif
            @if ($hasAbout)
                <a href="{{ $homeAnchor('quem-somos') }}">Quem somos</a>
            @endif
            @if ($hasArea)
                <a href="{{ $homeAnchor('onde-atendemos') }}">Onde atendemos</a>
            @endif
            <a href="#contato">Contato</a>
        </nav>
        @if ($links['request'])
            <a class="cta" href="{{ $requestLink($preferredService ?? null) }}">Solicitar orçamento</a>
        @endif
    </div>
</header>

<main id="conteudo" tabindex="-1">
    @if ($page === 'services')
        <div class="hero-band">
            <div class="wrap">
                <section class="hero" aria-labelledby="titulo-principal">
                    <h1 id="titulo-principal">Serviços de {{ $business }}</h1>
                    @if ($whereWeServe)
                        <p class="where">{!! $icon('pin') !!}<span>{{ $whereWeServe }}</span></p>
                    @endif
                    <p class="lead">Escolha um serviço para solicitar seu orçamento. Você pode trocar o serviço no formulário.</p>
                </section>
            </div>
        </div>
    @else
        <div class="hero-band">
            <div class="wrap split">
                <section class="hero" aria-labelledby="titulo-principal">
                    @if ($segmentName !== null)
                        <p class="eyebrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $colors['icon'] }}"/></svg>
                            {{ $segmentName }}
                        </p>
                    @endif
                    <h1 id="titulo-principal">{{ $heading }}</h1>
                    {{-- What the company does, from its published services; the "Quem somos" text appears only once, below. --}}
                    @if ($whatWeDo)
                        <p class="lead">{{ $business }}: {{ $whatWeDo }}.</p>
                    @endif
                    @if ($whereWeServe)
                        <p class="where">{!! $icon('pin') !!}<span>{{ $whereWeServe }}</span></p>
                    @endif
                    <div class="actions">
                        @if ($links['request'])
                            <a class="cta" href="{{ $requestLink($preferredService ?? null) }}">Solicitar orçamento</a>
                        @endif
                        @if ($links['assistant'])
                            <a class="cta secondary" href="{{ $links['assistant'] }}">{!! $icon('chat') !!}{{ $onlineLabel }}</a>
                        @endif
                        @if ($whatsDigits !== '')
                            <a class="cta secondary" href="https://wa.me/{{ $whatsDigits }}" rel="noopener">Falar no WhatsApp</a>
                        @endif
                    </div>
                </section>
                @if ($hero)
                    <img class="hero-image" src="{{ $mediaBase.$hero['file'] }}" alt="{{ ($content['hero_alt'] ?? null) ?: 'Trabalho realizado por '.$business }}" width="{{ $hero['width'] ?: '' }}" height="{{ $hero['height'] ?: '' }}" fetchpriority="high" decoding="async">
                @else
                    @include('website.partials.default-hero', ['accent' => $accent, 'icon' => $colors['icon'], 'segment' => $segmentName])
                @endif
            </div>
        </div>
    @endif

    @if ($services !== [])
        <section class="block" id="servicos" aria-labelledby="servicos-titulo">
            <div class="wrap">
                <h2 id="servicos-titulo">Serviços oferecidos</h2>
                <p class="intro">Escolha o serviço e peça seu orçamento. O valor final é combinado diretamente com {{ $business }}.</p>
                <div @class(['services', 'single' => count($services) === 1])>
                    @foreach ($services as $service)
                        @php($photo = is_array($service['photo'] ?? null) && $mediaBase ? $service['photo'] : null)
                        <article class="service" aria-labelledby="servico-{{ $loop->index }}">
                            @if ($photo)
                                <img src="{{ $mediaBase.$photo['file'] }}" alt="{{ $service['name'] }}" width="{{ $photo['width'] ?: '' }}" height="{{ $photo['height'] ?: '' }}" loading="lazy" decoding="async">
                            @endif
                            <div class="body">
                                <h3 id="servico-{{ $loop->index }}">{{ $service['name'] }}</h3>
                                @if (filled($service['description'] ?? null))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                                <div class="meta">
                                    <span class="price">{{ filled($service['price_label'] ?? null) ? $service['price_label'] : 'Sob consulta' }}</span>
                                    @if (! empty($service['duration_minutes']))
                                        <span class="duration">Duração estimada: {{ $service['duration_minutes'] }} min</span>
                                    @endif
                                </div>
                                @if ($links['request'])
                                    <a class="cta" href="{{ $requestLink(isset($service['id']) ? (int) $service['id'] : null) }}">Solicitar orçamento<span class="sr-only">: {{ $service['name'] }}</span></a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($page === 'home')
        @if ($hasAbout)
            <section class="block alt" id="quem-somos" aria-labelledby="quem-somos-titulo">
                <div @class(['wrap', 'about', 'has-photo' => $aboutPhoto !== null])>
                    <div>
                        <h2 id="quem-somos-titulo">Quem somos</h2>
                        @if (filled($content['about'] ?? null))
                            <p class="text">{{ $content['about'] }}</p>
                        @endif
                        @if (! empty($content['differentials'] ?? []))
                            <h3 class="sr-only">Diferenciais</h3>
                            <ul class="checks">
                                @foreach ($content['differentials'] as $item)
                                    <li>{!! $icon('check') !!}<span>{{ $item }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($content['trust_items'] ?? []))
                            <h3 class="sr-only">Certificações e referências</h3>
                            <ul class="trust">
                                @foreach ($content['trust_items'] as $item)
                                    <li>{!! $icon('badge') !!}<span><strong>{{ $item['label'] }}</strong><small>Fonte: @if (! empty($item['url']))<a href="{{ $item['url'] }}" rel="noopener nofollow" target="_blank">{{ $item['source'] }}</a>@else{{ $item['source'] }}@endif</small></span></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @if ($aboutPhoto)
                        <img src="{{ $mediaBase.$aboutPhoto['file'] }}" alt="Equipe ou espaço de {{ $business }}" width="{{ $aboutPhoto['width'] ?: '' }}" height="{{ $aboutPhoto['height'] ?: '' }}" loading="lazy" decoding="async">
                    @endif
                </div>
            </section>
        @endif

        @if ($hasArea)
            <section class="block" id="onde-atendemos" aria-labelledby="onde-titulo">
                <div class="wrap">
                    <h2 id="onde-titulo">Onde atendemos</h2>
                    <div class="area">
                        @if ($place !== null || $served !== [] || $legacyRegion !== null)
                            <div class="panel">
                                <h3>Municípios atendidos</h3>
                                @if ($place !== null)
                                    <p>Sede em <strong>{{ $place }}</strong>.</p>
                                @endif
                                @if ($served !== [] || $base !== null)
                                    <ul class="pills" aria-label="Municípios atendidos">
                                        @if ($base !== null)
                                            <li class="base">{!! $icon('pin') !!}{{ $base['name'] }}, {{ $base['state'] }}</li>
                                        @endif
                                        @foreach ($served as $item)
                                            <li>{{ $item['name'] }}, {{ $item['state'] }}</li>
                                        @endforeach
                                    </ul>
                                @elseif ($legacyRegion !== null)
                                    <p style="margin-top:8px">{{ $legacyRegion }}</p>
                                @endif
                            </div>
                        @endif
                        @if ($modeText !== null)
                            <div class="panel">
                                <h3>Como atendemos</h3>
                                <p>{{ $modeText }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if (! empty($content['professionals'] ?? []))
            <section class="block alt" aria-labelledby="equipe-titulo">
                <div class="wrap">
                    <h2 id="equipe-titulo">Equipe</h2>
                    <ul class="plain">
                        @foreach ($content['professionals'] as $person)
                            <li>{{ $person['name'] }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    @endif
</main>

<footer class="site" id="contato">
    <div class="wrap">
        <h2>Contato</h2>
        <div class="cols">
            <div>
                <h3>{{ $business }}</h3>
                <ul>
                    @if (filled($contact['phone'] ?? null))<li>{!! $icon('phone') !!}<a href="tel:+{{ str_starts_with($phoneDigits, '55') ? $phoneDigits : '55'.$phoneDigits }}">Telefone: {{ $contact['phone'] }}</a></li>@endif
                    @if (filled($contact['whatsapp'] ?? null))<li>{!! $icon('chat') !!}<a href="https://wa.me/{{ $whatsDigits }}" rel="noopener">WhatsApp: {{ $contact['whatsapp'] }}</a></li>@endif
                    @if (filled($contact['email'] ?? null))<li>{!! $icon('mail') !!}<a href="mailto:{{ $contact['email'] }}">E-mail: {{ $contact['email'] }}</a></li>@endif
                    @if ($fullAddress !== null)
                        <li>{!! $icon('pin') !!}<span>{{ $fullAddress }}<br><a href="{{ $mapsUrl }}" rel="noopener" target="_blank">Como chegar<span class="sr-only"> (abre o mapa em outra aba)</span></a></span></li>
                    @elseif ($place !== null)
                        <li>{!! $icon('pin') !!}<span>{{ $place }}</span></li>
                    @endif
                </ul>
                @if (! $hasContact && $links['request'])
                    <p style="margin:8px 0 0">Fale com a empresa pelo formulário de orçamento.</p>
                @endif
            </div>
            @if ($hasHours)
                <div>
                    <h3>Horários</h3>
                    <table>
                        <caption class="sr-only">Horários de atendimento</caption>
                        @foreach ([1, 2, 3, 4, 5, 6, 0] as $weekday)
                            @php($day = $hours[(string) $weekday] ?? $hours[$weekday] ?? null)
                            <tr><td>{{ $dayNames[$weekday] }}</td><td>{{ is_array($day) ? $day['open'].' às '.$day['close'] : 'Fechado' }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @endif
            @if ($whereWeServe || $modeText)
                <div>
                    <h3>Região</h3>
                    @if ($whereWeServe)<p style="margin:0">{{ $whereWeServe }}</p>@endif
                    @if ($modeText)<p style="margin:6px 0 0">{{ $modeText }}</p>@endif
                </div>
            @endif
            <div>
                <h3>Links úteis</h3>
                <ul>
                    @if ($links['request'])<li><a href="{{ $requestLink() }}">Solicitar orçamento</a></li>@endif
                    @if ($links['services'] && $page !== 'services')<li><a href="{{ $links['services'] }}">Todos os serviços</a></li>@endif
                    @if ($links['assistant'])<li><a href="{{ $links['assistant'] }}">{{ $onlineLabel }}</a></li>@endif
                    @if (filled($contact['google_review_url'] ?? null) && str_starts_with((string) $contact['google_review_url'], 'https://'))<li><a href="{{ $contact['google_review_url'] }}" rel="noopener nofollow" target="_blank">Avalie-nos no Google</a></li>@endif
                </ul>
            </div>
        </div>
        <div class="legal">
            <span>© {{ now()->year }} {{ $business }}</span>
            <span>Site criado com VetorSmart.</span>
        </div>
    </div>
</footer>
</body>
</html>
