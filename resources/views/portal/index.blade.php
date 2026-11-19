@php
    use App\Services\Portal\PortalSearch;
    use App\Services\Portal\PortalText;
    use App\Services\Websites\SegmentTheme;
    use Illuminate\Support\Facades\Vite;
    use Illuminate\Support\Str;

    $description = 'Encontre prestadores de serviço por tipo de serviço e município, conheça o site de cada empresa e solicite atendimento diretamente a ela, sem custo.';
    $value = fn (string $field): string => (string) ($filters[$field] ?? '');
    $moreOpen = $value('categoria') !== '' || $value('nome') !== '' || $searchErrors->has('nome') || $searchErrors->has('categoria');
    $tokens = PortalText::tokens($filters['q'] ?? null);
    $icons = [
        'search' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-4.35-4.35',
        'pin' => 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        'close' => 'M6 6l12 12M18 6 6 18',
        'filter' => 'M4 6h16M7 12h10M10 18h4',
        'arrow' => 'M5 12h14M13 6l6 6-6 6',
        'chat' => 'M4 5h16v11H8l-4 4z',
        'check' => 'M5 12l5 5 9-10',
    ];
    $icon = fn (string $path, string $class = 'icon'): string => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="'.e($path).'"/></svg>';
    $structuredData = $indexable && ! $searched ? json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Portal Regional VetorSmart',
        'url' => $home,
        'potentialAction' => ['@type' => 'SearchAction', 'target' => $home.'?q={q}', 'query-input' => 'required name=q'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : null;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    @unless ($indexable)
        <meta name="robots" content="noindex, follow">
    @endunless
    <meta name="theme-color" content="#0F172A">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="VetorSmart">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $assets }}/images/vetor-smart-logo-transparent.png">
    <link rel="icon" href="{{ $assets }}/images/vetor-smart-favicon-32.png" sizes="32x32">
    @if ($structuredData)
        <script type="application/ld+json">{!! $structuredData !!}</script>
    @endif
    <style>
        :root {
            --text: #0B1220; --muted: #475569; --border: #CBD5E1; --surface: #F1F5F9; --card: #FFFFFF; --page: #FFFFFF;
            --brand: #1D4ED8; --brand-hover: #1E40AF; --brand-soft: #EFF6FF; --brand-ink: #1E3A8A; --navy: #0F172A;
            --link: #1D4ED8; --danger: #B91C1C; --focus: #1D4ED8; --input: #64748B;
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --text: #E2E8F0; --muted: #A3B3C9; --border: #334155; --surface: #111A2E; --card: #16213A; --page: #0B1220;
                --brand: #2563EB; --brand-hover: #1D4ED8; --brand-soft: #1E293B; --brand-ink: #BFDBFE; --navy: #020617;
                --link: #93C5FD; --danger: #FCA5A5; --focus: #93C5FD; --input: #64748B;
                color-scheme: dark;
            }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 16px; color: var(--text); background: var(--page); line-height: 1.55; }
        a { color: var(--link); }
        :focus-visible { outline: 3px solid var(--focus); outline-offset: 2px; }
        h1, h2, h3 { line-height: 1.25; }
        .wrap { max-width: 1160px; margin: 0 auto; padding: 0 16px; }
        .icon { width: 20px; height: 20px; flex: 0 0 auto; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .skip { position: absolute; left: -9999px; top: 8px; }
        .skip:focus { left: 16px; background: var(--card); color: var(--text); padding: 10px 14px; border-radius: 8px; z-index: 20; }

        /* Header */
        header.top { background: var(--card); border-bottom: 1px solid var(--border); }
        header.top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 64px; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none; }
        .brand img { height: 34px; width: auto; }
        .brand strong { display: block; font-size: 1rem; }
        .brand small { display: block; color: var(--muted); font-size: .78rem; }
        header.top nav a { color: var(--text); text-decoration: none; font-weight: 500; padding: 10px 6px; }
        header.top nav a:hover { text-decoration: underline; }

        /* Search */
        .search-band { background: var(--navy); color: #F8FAFC; padding: 28px 0 24px; }
        .search-band h1 { margin: 0 0 6px; font-size: clamp(1.5rem, 4.5vw, 2.3rem); }
        .search-band p.lead { margin: 0 0 18px; color: #CBD5E1; max-width: 640px; }
        form.search { background: var(--card); color: var(--text); border-radius: 16px; padding: 14px; display: grid; gap: 12px; box-shadow: 0 8px 24px rgba(2, 6, 23, .25); }
        .row { display: grid; gap: 12px; }
        @media (min-width: 720px) { .row.main { grid-template-columns: 1.6fr 1.2fr 110px auto; align-items: end; } .row.more { grid-template-columns: 1fr 1fr; } }
        .field label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: 6px; }
        .control { position: relative; }
        .control .icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
        .control input, .control select { padding-left: 42px; }
        input, select { width: 100%; font: inherit; font-size: 16px; min-height: 48px; padding: 10px 12px; border: 1px solid var(--input); border-radius: 12px; background: var(--page); color: var(--text); }
        select { padding-right: 28px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 10px 18px; border-radius: 12px; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; border: 1px solid transparent; }
        .btn.primary { background: var(--brand); color: #fff; }
        .btn.primary:hover { background: var(--brand-hover); }
        .btn.secondary { background: var(--card); color: var(--text); border-color: var(--border); }
        .btn.secondary:hover { background: var(--surface); }
        .btn[disabled] { opacity: .8; cursor: progress; }
        details.more summary { cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--link); min-height: 44px; list-style: none; }
        details.more summary::-webkit-details-marker { display: none; }
        details.more[open] summary { margin-bottom: 8px; }
        .field-error { color: var(--danger); font-size: .88rem; margin: 6px 0 0; }

        /* Active filters */
        .active-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 16px 0 0; padding: 0; list-style: none; }
        .active-filters a.chip { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 6px 12px; border-radius: 999px; background: var(--brand-soft); color: var(--brand-ink); border: 1px solid var(--border); text-decoration: none; font-size: .92rem; font-weight: 500; }
        .active-filters a.chip .icon { width: 16px; height: 16px; }
        .active-filters a.clear { font-weight: 600; padding: 8px 4px; }

        /* Sections */
        main { padding: 24px 0 48px; }
        section.block { margin-top: 32px; }
        section.block:first-child { margin-top: 0; }
        .section-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 14px; }
        .section-head h2 { margin: 0; font-size: 1.3rem; }
        .section-head p { margin: 0; color: var(--muted); }

        /* Categories */
        ul.categories { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 172px), 1fr)); }
        ul.categories span { min-width: 0; overflow-wrap: anywhere; }
        ul.categories a { display: flex; align-items: center; gap: 10px; min-height: 56px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 14px; background: var(--card); color: var(--text); text-decoration: none; font-weight: 600; font-size: .95rem; }
        ul.categories a:hover { border-color: var(--brand); }
        ul.categories a[aria-current="true"] { border-color: var(--brand); background: var(--brand-soft); color: var(--brand-ink); }
        ul.categories .icon { width: 24px; height: 24px; color: var(--brand); }
        @media (prefers-color-scheme: dark) { ul.categories .icon { color: var(--link); } }

        /* Cards */
        .summary { margin: 0 0 14px; color: var(--muted); }
        ul.results { list-style: none; margin: 0; padding: 0; display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 18px; display: flex; flex-direction: column; gap: 12px; height: 100%; }
        .card .head { display: flex; gap: 14px; align-items: flex-start; }
        .avatar { width: 64px; height: 64px; flex: 0 0 64px; border-radius: 14px; border: 1px solid var(--border); background: #fff; object-fit: contain; }
        .avatar.placeholder { display: grid; place-items: center; color: #1E293B; }
        .avatar.placeholder svg { width: 30px; height: 30px; }
        .card h3 { margin: 0 0 4px; font-size: 1.1rem; overflow-wrap: anywhere; }
        .card .activity { margin: 0; color: var(--muted); font-size: .92rem; display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; }
        .card .activity span { display: inline-flex; align-items: center; gap: 4px; }
        .card .activity .icon { width: 16px; height: 16px; }
        .card .about { margin: 0; color: var(--muted); }
        .card .match { margin: 0; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--text); }
        .card .match .icon { width: 18px; height: 18px; color: #15803D; }
        @media (prefers-color-scheme: dark) { .card .match .icon { color: #4ADE80; } }
        .card .region { margin: 0; font-size: .92rem; }
        .card ul.services { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 6px; }
        .card ul.services li { font-size: .86rem; padding: 4px 10px; border-radius: 999px; background: var(--surface); border: 1px solid var(--border); }
        .card .actions { margin-top: auto; display: grid; gap: 8px; grid-template-columns: 1fr; padding-top: 4px; }
        /* Side by side only where a card is wide (a single column on large phones and small tablets). */
        @media (min-width: 480px) and (max-width: 699px) { .card .actions { grid-template-columns: 1fr 1fr; } }
        .card .actions .btn { white-space: nowrap; }

        /* States */
        .notice { border: 1px dashed var(--border); border-radius: 16px; padding: 20px; background: var(--card); }
        .notice h2 { margin: 0 0 6px; font-size: 1.2rem; }
        .notice p { margin: 0 0 10px; color: var(--muted); }
        .notice ul { margin: 8px 0 0; padding-left: 20px; }
        .notice li { margin: 6px 0; }
        .notice li a { font-weight: 600; }
        nav.pages { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: center; margin-top: 28px; }
        nav.pages span { color: var(--muted); padding: 8px; }

        /* How it works */
        ol.steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); counter-reset: step; }
        ol.steps li { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 16px; }
        ol.steps li::before { counter-increment: step; content: counter(step); display: inline-grid; place-items: center; width: 32px; height: 32px; border-radius: 999px; background: var(--brand); color: #fff; font-weight: 700; margin-bottom: 8px; }
        ol.steps h3 { margin: 0 0 4px; font-size: 1.02rem; }
        ol.steps p { margin: 0; color: var(--muted); }
        .trust { margin-top: 16px; color: var(--muted); font-size: .95rem; }

        /* Footer */
        footer.site { background: var(--surface); border-top: 1px solid var(--border); padding: 28px 0; color: var(--muted); font-size: .92rem; }
        footer.site .cols { display: grid; gap: 18px; grid-template-columns: 1fr; }
        @media (min-width: 720px) { footer.site .cols { grid-template-columns: 2fr 1fr; } }
        footer.site strong { color: var(--text); }
        footer.site ul { list-style: none; margin: 0; padding: 0; }
        footer.site li { margin: 6px 0; }
        footer.site p { margin: 6px 0; }
    </style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>

<header class="top">
    <div class="wrap">
        <a class="brand" href="/">
            <img src="{{ $assets }}/images/vetor-smart-logo-reduced.png" alt="" width="47" height="34">
            <span><strong>Portal Regional</strong><small>VetorSmart</small></span>
        </a>
        <nav aria-label="Navegação do portal">
            <a href="/#como-funciona">Como funciona</a>
        </nav>
    </div>
</header>

<div class="search-band">
    <div class="wrap">
        @if ($searched)
            <h1 style="font-size:1.35rem">Buscar profissionais</h1>
            <p class="lead" style="margin-bottom:12px">Ajuste o serviço, o município ou os filtros a qualquer momento.</p>
        @else
            <h1>Encontre profissionais na sua região</h1>
            <p class="lead">Busque pelo serviço que você precisa, conheça cada empresa e peça atendimento diretamente a ela, sem custo.</p>
        @endif

        <form class="search" method="get" action="/" role="search" aria-label="Encontrar profissionais" data-search-form novalidate>
            <div class="row main">
                <div class="field">
                    <label for="q">Qual serviço você precisa?</label>
                    <div class="control">
                        {!! $icon($icons['search']) !!}
                        <input id="q" name="q" type="search" value="{{ $value('q') }}" maxlength="80" placeholder="Ex.: instalação de ar-condicionado" autocomplete="off" enterkeyhint="search" @if ($searchErrors->has('q')) aria-invalid="true" aria-describedby="q-error" @endif>
                    </div>
                    @if ($searchErrors->has('q'))<p class="field-error" id="q-error">{{ $searchErrors->first('q') }}</p>@endif
                </div>
                <div class="field">
                    <label for="cidade">Município</label>
                    <div class="control">
                        {!! $icon($icons['pin']) !!}
                        <input id="cidade" name="cidade" type="text" value="{{ $value('cidade') }}" maxlength="80" list="cidades" placeholder="Ex.: Campinas" autocomplete="address-level2" @if ($searchErrors->has('cidade')) aria-invalid="true" aria-describedby="cidade-error" @endif>
                    </div>
                    <datalist id="cidades">
                        @foreach ($cities as $city)
                            <option value="{{ Str::beforeLast($city, ' - ') }}">{{ $city }}</option>
                        @endforeach
                    </datalist>
                    @if ($searchErrors->has('cidade'))<p class="field-error" id="cidade-error">{{ $searchErrors->first('cidade') }}</p>@endif
                </div>
                <div class="field">
                    <label for="uf">UF</label>
                    <select id="uf" name="uf" @if ($searchErrors->has('uf')) aria-invalid="true" aria-describedby="uf-error" @endif>
                        <option value="">Todas</option>
                        @foreach (PortalText::STATES as $state)
                            <option value="{{ $state }}" @selected($value('uf') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    @if ($searchErrors->has('uf'))<p class="field-error" id="uf-error">{{ $searchErrors->first('uf') }}</p>@endif
                </div>
                <div><button class="btn primary" type="submit" data-search-button style="width:100%">{!! $icon($icons['search']) !!}<span data-search-label>Encontrar profissionais</span></button></div>
            </div>
            <details class="more" @if ($moreOpen) open @endif>
                <summary>{!! $icon($icons['filter']) !!} Mais filtros</summary>
                <div class="row more">
                    <div class="field">
                        <label for="categoria">Categoria</label>
                        <select id="categoria" name="categoria" @if ($searchErrors->has('categoria')) aria-invalid="true" aria-describedby="categoria-error" @endif>
                            <option value="">Todas as categorias</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['slug'] }}" @selected($value('categoria') === $category['slug'])>{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                        @if ($searchErrors->has('categoria'))<p class="field-error" id="categoria-error">{{ $searchErrors->first('categoria') }}</p>@endif
                    </div>
                    <div class="field">
                        <label for="nome">Nome da empresa</label>
                        <input id="nome" name="nome" type="text" value="{{ $value('nome') }}" maxlength="80" autocomplete="off" @if ($searchErrors->has('nome')) aria-invalid="true" aria-describedby="nome-error" @endif>
                        @if ($searchErrors->has('nome'))<p class="field-error" id="nome-error">{{ $searchErrors->first('nome') }}</p>@endif
                    </div>
                </div>
            </details>
        </form>

        @if ($activeFilters !== [])
            <ul class="active-filters" aria-label="Filtros ativos">
                @foreach ($activeFilters as $filter)
                    <li><a class="chip" href="{{ $filter['remove'] }}" aria-label="Remover filtro {{ $filter['label'] }}">{{ $filter['label'] }} {!! $icon($icons['close']) !!}</a></li>
                @endforeach
                <li><a class="clear" href="/" style="color:#BFDBFE">Limpar filtros</a></li>
            </ul>
        @endif
    </div>
</div>

<main class="wrap" id="conteudo" tabindex="-1">
    <div class="sr-only" aria-live="polite" data-search-status></div>

    @if ($searchErrors->has('pagina'))
        <div class="notice" role="alert"><h2>Refine a busca</h2><p>{{ $searchErrors->first('pagina') }}</p></div>
    @elseif ($searchErrors->isNotEmpty())
        <div class="notice" role="alert">
            <h2>Revise a busca</h2>
            <p>Corrija o campo indicado acima e tente novamente.</p>
        </div>
    @endif

    @if ($results !== null)
        <section class="block" aria-labelledby="resultados-titulo">
            <div class="section-head">
                <h2 id="resultados-titulo">{{ $categoryName && array_keys(array_filter($filters)) === ['categoria'] ? $categoryName : 'Resultados da busca' }}</h2>
            </div>

            @if ($results->total() === 0)
                <div class="notice" role="status">
                    <h2>Nenhum profissional encontrado</h2>
                    <p>Ainda não há empresas participantes com esses critérios. Experimente ampliar a busca:</p>
                    <ul>
                        @foreach ($suggestions as $suggestion)
                            <li><a href="{{ $suggestion['url'] }}">{{ $suggestion['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="summary" role="status">
                    {{ $results->total() }} {{ $results->total() === 1 ? 'profissional encontrado' : 'profissionais encontrados' }}@if ($results->lastPage() > 1) · página {{ $results->currentPage() }} de {{ $results->lastPage() }}@endif
                </p>
                @include('portal.partials.cards', ['listings' => $results, 'tokens' => $tokens])

                @if ($results->lastPage() > 1)
                    <nav class="pages" aria-label="Páginas de resultados">
                        @if ($results->previousPageUrl())
                            <a class="btn secondary" href="{{ $results->previousPageUrl() }}" rel="prev">← Anterior</a>
                        @endif
                        <span>Página {{ $results->currentPage() }} de {{ $results->lastPage() }}</span>
                        @if ($results->hasMorePages() && $results->currentPage() < PortalSearch::MAX_PAGE)
                            <a class="btn secondary" href="{{ $results->nextPageUrl() }}" rel="next">Próxima →</a>
                        @endif
                    </nav>
                @endif
            @endif
        </section>
    @endif

    @if ($categories !== [])
        <section class="block" id="categorias" aria-labelledby="categorias-titulo">
            <div class="section-head">
                <h2 id="categorias-titulo">Categorias de serviços</h2>
                <p>Somente categorias com empresas participantes.</p>
            </div>
            <ul class="categories">
                @foreach ($categories as $category)
                    <li>
                        <a href="/?categoria={{ $category['slug'] }}" @if ($value('categoria') === $category['slug']) aria-current="true" @endif>
                            {!! $icon($category['icon']) !!}<span>{{ $category['name'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($featured !== null)
        <section class="block" aria-labelledby="destaque-titulo">
            <div class="section-head">
                <h2 id="destaque-titulo">Profissionais no portal</h2>
                @if ($featured->total() > 0)
                    <p>A ordem muda a cada dia. Não há posições pagas.</p>
                @endif
            </div>
            @if ($featured->total() === 0)
                <div class="notice">
                    <h2 style="font-size:1.05rem">As primeiras empresas estão chegando</h2>
                    <p>Ainda não há empresas participantes no portal. Volte em breve.</p>
                </div>
            @else
                @include('portal.partials.cards', ['listings' => $featured, 'tokens' => []])
                @if ($featured->total() > $featured->count())
                    <p style="margin-top:16px"><a class="btn secondary" href="/?pagina=1">Ver todos os profissionais {!! $icon($icons['arrow']) !!}</a></p>
                @endif
            @endif
        </section>
    @endif

    <section class="block" id="como-funciona" aria-labelledby="como-titulo">
        <div class="section-head"><h2 id="como-titulo">Como funciona</h2></div>
        <ol class="steps">
            <li><h3>Busque o serviço</h3><p>Digite o que você precisa e o seu município, ou escolha uma categoria.</p></li>
            <li><h3>Conheça a empresa</h3><p>Veja os serviços, a região atendida e o site de cada profissional.</p></li>
            <li><h3>Solicite atendimento</h3><p>Envie seu pedido direto à empresa, que entra em contato com você.</p></li>
        </ol>
        <p class="trust">Aparecem apenas empresas com site publicado que escolheram participar. A ordem considera o serviço buscado, a região atendida e as informações públicas de cada empresa; empresas equivalentes alternam de posição a cada dia. O portal não intermedeia pagamentos: o orçamento e o atendimento são combinados diretamente com a empresa.</p>
    </section>
</main>

<footer class="site">
    <div class="wrap cols">
        <div>
            <p><strong>Portal Regional VetorSmart</strong></p>
            <p>Diretório gratuito de empresas de serviços que usam o VetorSmart. As informações exibidas são publicadas pelas próprias empresas.</p>
        </div>
        <div>
            <ul>
                <li><a href="/#como-funciona">Como funciona</a></li>
                <li><a href="/#categorias">Categorias</a></li>
            </ul>
        </div>
    </div>
</footer>

<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        var form = document.querySelector('[data-search-form]');
        var button = document.querySelector('[data-search-button]');
        var label = document.querySelector('[data-search-label]');
        var status = document.querySelector('[data-search-status]');
        if (!form || !button || !label) { return; }
        form.addEventListener('submit', function () {
            // Empty fields stay out of the address, so a shared link keeps only the filters really chosen.
            Array.prototype.forEach.call(form.querySelectorAll('input[name], select[name]'), function (field) {
                if (field.value === '') { field.disabled = true; }
            });
            button.disabled = true;
            label.textContent = 'Buscando…';
            form.setAttribute('aria-busy', 'true');
            if (status) { status.textContent = 'Buscando profissionais…'; }
        });
        window.addEventListener('pageshow', function () {
            Array.prototype.forEach.call(form.querySelectorAll('[disabled]'), function (field) { field.disabled = false; });
            label.textContent = 'Encontrar profissionais';
            form.removeAttribute('aria-busy');
        });
    })();
</script>
</body>
</html>
