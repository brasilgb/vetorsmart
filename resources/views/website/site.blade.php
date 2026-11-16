@php
    $business = $content['business_name'] ?? '';
    $seo = $content['seo'] ?? [];
    $title = $seo['title'] ?: $business;
    $description = $seo['description'] ?: ($content['about'] ?: null);
    $accent = in_array($content['accent_color'] ?? '', ['#0F172A', '#1E3A5F', '#2563EB', '#06B6D4'], true) ? $content['accent_color'] : '#2563EB';
    // Sem aspas: o valor é impresso com escape dentro de <style>, e um &quot; invalidaria a declaração inteira.
    $fontStack = ($content['font'] ?? 'sans') === 'serif' ? 'Georgia, Times New Roman, serif' : 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
    $contact = $content['contact'] ?? [];
    $phoneDigits = preg_replace('/\D+/', '', (string) ($contact['phone'] ?? ''));
    $whatsDigits = preg_replace('/\D+/', '', (string) ($contact['whatsapp'] ?? ''));
    $dayNames = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $hours = $content['hours'] ?? null;
    $hasContact = collect($contact)->filter(fn ($value) => filled($value))->isNotEmpty();
    $location = trim(implode(', ', array_filter([$contact['address'] ?? null, $contact['city'] ?? null, $contact['state'] ?? null])));
    $page = $page ?? 'home';
    $links = $links ?? ['home' => null, 'services' => null, 'assistant' => null];
    $title = $page === 'services' ? 'Serviços · '.$title : $title;
    // Built inside this PHP block: in the template body, Blade would compile the schema.org context key as a directive.
    $structuredData = json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ProfessionalService',
        'name' => $business,
        'description' => ($content['about'] ?? null) ?: null,
        'telephone' => ($contact['phone'] ?? null) ?: null,
        'email' => ($contact['email'] ?? null) ?: null,
        'url' => $canonical,
        'address' => ($contact['city'] ?? null) ? array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => ($contact['address'] ?? null) ?: null,
            'addressLocality' => $contact['city'],
            'addressRegion' => ($contact['state'] ?? null) ?: null,
            'addressCountry' => 'BR',
        ]) : null,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--site-font); color: var(--site-text); background: #fff; line-height: 1.6; }
        a { color: var(--site-accent); }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 20px; }
        header.site { border-bottom: 1px solid var(--site-border); }
        header.site .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-top: 16px; padding-bottom: 16px; flex-wrap: wrap; }
        header.site .name { font-weight: 700; font-size: 1.1rem; color: var(--site-text); text-decoration: none; }
        .cta { display: inline-block; background: var(--site-accent); color: #fff; padding: 12px 22px; border-radius: 10px; text-decoration: none; font-weight: 600; }
        .hero { padding-top: 64px; padding-bottom: 40px; }
        .hero h1 { font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1.15; margin: 0 0 16px; }
        .hero p { color: var(--site-muted); font-size: 1.05rem; max-width: 720px; margin: 0 0 24px; white-space: pre-line; }
        section.block { padding: 40px 0; border-top: 1px solid var(--site-border); }
        section.block h2 { font-size: 1.5rem; margin: 0 0 16px; }
        .cards { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .card { background: var(--site-surface); border: 1px solid var(--site-border); border-radius: 14px; padding: 18px; }
        .card h3 { margin: 0 0 6px; font-size: 1.05rem; }
        .card p { margin: 0; color: var(--site-muted); font-size: .95rem; white-space: pre-line; }
        .price { color: var(--site-accent); font-weight: 600; margin-top: 8px; }
        ul.plain { list-style: none; padding: 0; margin: 0; }
        ul.plain li { padding: 6px 0; border-bottom: 1px dashed var(--site-border); }
        table.hours { border-collapse: collapse; }
        table.hours td { padding: 6px 16px 6px 0; color: var(--site-muted); }
        table.hours td:first-child { color: var(--site-text); font-weight: 600; }
        .preview-banner { background: #FEF3C7; color: #78350F; text-align: center; padding: 10px; font-size: .9rem; font-weight: 600; }
        footer.site { border-top: 1px solid var(--site-border); padding: 28px 0; color: var(--site-muted); font-size: .9rem; }
        nav.site { display: flex; flex-wrap: wrap; gap: 4px 16px; align-items: center; }
        nav.site a { color: var(--site-text); text-decoration: none; font-weight: 500; padding: 8px 0; }
        nav.site a[aria-current="page"] { color: var(--site-accent); text-decoration: underline; text-underline-offset: 4px; }
        a:focus-visible { outline: 3px solid var(--site-accent); outline-offset: 2px; }
        @media (max-width: 640px) { .hero { padding-top: 40px; padding-bottom: 28px; } }
    </style>
</head>
<body>
@if ($preview)
    <div class="preview-banner">Prévia do rascunho. Esta versão ainda não está publicada para os visitantes.</div>
@endif

<header class="site">
    <div class="wrap">
        <a class="name" href="{{ $links['home'] ?? '#' }}">{{ $business }}</a>
        @if ($links['home'] && ($links['services'] || $links['assistant']))
            <nav class="site" aria-label="Navegação do site">
                <a href="{{ $links['home'] }}" @if ($page === 'home') aria-current="page" @endif>Início</a>
                @if ($links['services'])
                    <a href="{{ $links['services'] }}" @if ($page === 'services') aria-current="page" @endif>Serviços</a>
                @endif
                @if ($links['assistant'])
                    <a href="{{ $links['assistant'] }}">Atendimento</a>
                @endif
            </nav>
        @endif
        @if ($phoneDigits !== '' || $whatsDigits !== '')
            <a class="cta" href="{{ $whatsDigits !== '' ? 'https://wa.me/'.$whatsDigits : 'tel:+'.$phoneDigits }}">Entrar em contato</a>
        @endif
    </div>
</header>

<main>
    @if ($page === 'services')
    <section class="wrap hero">
        <h1>Serviços</h1>
        <p>Conheça os serviços de {{ $business }}.</p>
    </section>
    @else
    <section class="wrap hero">
        <h1>{{ $content['headline'] ?: $business }}</h1>
        @if (filled($content['about'] ?? null))
            <p>{{ $content['about'] }}</p>
        @endif
        @if (($content['segment'] ?? null) !== null)
            <p style="font-size:.95rem">{{ $content['segment'] }}</p>
        @endif
    </section>
    @endif

    @if ($page === 'home' && filled($content['about'] ?? null))
        <section class="block">
            <div class="wrap">
                <h2>Sobre a empresa</h2>
                <p style="white-space: pre-line; color: var(--site-muted); margin:0; max-width:760px">{{ $content['about'] }}</p>
            </div>
        </section>
    @endif

    @if (! empty($content['services'] ?? []))
        <section class="block">
            <div class="wrap">
                <h2>{{ $page === 'services' ? 'Todos os serviços' : 'Serviços' }}</h2>
                <div class="cards">
                    @foreach ($content['services'] as $service)
                        <div class="card">
                            <h3>{{ $service['name'] }}</h3>
                            @if (filled($service['description'] ?? null))
                                <p>{{ $service['description'] }}</p>
                            @endif
                            @if (! empty($service['duration_minutes']))
                                <p style="margin-top:6px">Duração estimada: {{ $service['duration_minutes'] }} min</p>
                            @endif
                            @if (filled($service['price_label'] ?? null))
                                <div class="price">{{ $service['price_label'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($page === 'home')
    @if (! empty($content['differentials'] ?? []))
        <section class="block">
            <div class="wrap">
                <h2>Diferenciais</h2>
                <ul class="plain">
                    @foreach ($content['differentials'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if (! empty($content['professionals'] ?? []))
        <section class="block">
            <div class="wrap">
                <h2>Profissionais</h2>
                <ul class="plain">
                    @foreach ($content['professionals'] as $person)
                        <li>{{ $person['name'] }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if (is_array($hours) && collect($hours)->filter()->isNotEmpty())
        <section class="block">
            <div class="wrap">
                <h2>Horários</h2>
                <table class="hours">
                    @foreach (range(1, 6) as $weekday)
                        @php($day = $hours[(string) $weekday] ?? $hours[$weekday] ?? null)
                        <tr>
                            <td>{{ $dayNames[$weekday] }}</td>
                            <td>{{ $day ? $day['open'].' às '.$day['close'] : 'Fechado' }}</td>
                        </tr>
                    @endforeach
                    @php($sunday = $hours['0'] ?? $hours[0] ?? null)
                    <tr>
                        <td>{{ $dayNames[0] }}</td>
                        <td>{{ $sunday ? $sunday['open'].' às '.$sunday['close'] : 'Fechado' }}</td>
                    </tr>
                </table>
            </div>
        </section>
    @endif

    @if ($hasContact)
        <section class="block" id="contato">
            <div class="wrap">
                <h2>Contato</h2>
                <ul class="plain">
                    @if (filled($contact['phone'] ?? null))<li>Telefone: {{ $contact['phone'] }}</li>@endif
                    @if (filled($contact['whatsapp'] ?? null))<li>WhatsApp: {{ $contact['whatsapp'] }}</li>@endif
                    @if (filled($contact['email'] ?? null))<li>E-mail: {{ $contact['email'] }}</li>@endif
                </ul>
            </div>
        </section>
    @endif

    @if ($location !== '')
        <section class="block">
            <div class="wrap">
                <h2>Localização</h2>
                <p style="margin:0; color: var(--site-muted)">{{ $location }}</p>
            </div>
        </section>
    @endif
    @endif
</main>

<footer class="site">
    <div class="wrap">
        {{ $business }}@if ($location !== '') · {{ $contact['city'] ?? '' }}@endif
        <div style="margin-top:6px">Site criado com VetorSmart.</div>
    </div>
</footer>
</body>
</html>
