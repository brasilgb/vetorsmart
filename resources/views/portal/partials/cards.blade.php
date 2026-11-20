{{--
    Company cards of the Regional Portal. Only published, public data: no phone, e-mail, rating or availability.
    Links go to the company's own standard address with the signed portal reference, the id of the searched service
    when the company publishes it and the IBGE code of the searched municipality (never a position); the site decides
    the company from its address only.
--}}
<ul class="results">
    @foreach ($listings as $listing)
        @php
            $base = $siteBase($listing);
            $theme = \App\Services\Websites\SegmentTheme::for($listing->getAttribute('segment_slug'));
            $matched = $tokens === [] ? null : collect($listing->services)->first(function (array $service) use ($tokens): bool {
                $name = \App\Services\Portal\PortalText::normalize($service['name']);
                foreach ($tokens as $token) {
                    if (! str_contains($name, $token)) {
                        return false;
                    }
                }

                return true;
            });
            $serviceQuery = is_array($matched) && is_int($matched['id'] ?? null) ? '&servico='.$matched['id'] : '';
            $serviceQuery .= ($municipality ?? null) !== null ? '&municipio='.$municipality->id : '';
            $services = array_slice($listing->services, 0, 4);
            $basedHere = ($filters['cidade'] ?? null) === null || (int) $listing->getAttribute('region_score') === 2;
        @endphp
        <li>
            <article class="card" aria-labelledby="empresa-{{ $listing->id }}">
                <div class="head">
                    @if (is_array($listing->logo))
                        <img class="avatar" src="{{ $base }}/midia/{{ $listing->logo['file'] }}" alt="" width="64" height="64" loading="lazy" decoding="async">
                    @else
                        <span class="avatar placeholder" style="background: {{ $theme['tone'] }}" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="{{ $theme['icon'] }}"/></svg>
                        </span>
                    @endif
                    <div>
                        <h3 id="empresa-{{ $listing->id }}">{{ $listing->business_name }}</h3>
                        <p class="activity">
                            <span>{{ $listing->getAttribute('segment_name') }}</span>
                        </p>
                    </div>
                </div>
                <ul class="places" aria-label="Localização de {{ $listing->business_name }}">
                    <li>{!! $icon($icons['pin']) !!}Sede em {{ $listing->city }}, {{ $listing->state }}</li>
                    @if (! $basedHere)
                        <li class="served-here">{!! $icon($icons['check']) !!}Atende em {{ ($municipality ?? null)?->label() ?? $filters['cidade'] }}</li>
                    @endif
                </ul>
                @if (is_array($matched))
                    <p class="match">{!! $icon($icons['check']) !!}Oferece: {{ $matched['name'] }}</p>
                @endif
                @if ($listing->summary)
                    <p class="about">{{ \Illuminate\Support\Str::limit($listing->summary, 160) }}</p>
                @endif
                @if ($listing->region)
                    <p class="region"><strong>Área atendida:</strong> {{ \Illuminate\Support\Str::limit($listing->region, 140) }}</p>
                @endif
                @if ($services !== [])
                    <p class="services-title">Principais serviços</p>
                    <ul class="services" aria-label="Principais serviços de {{ $listing->business_name }}">
                        @foreach ($services as $service)
                            <li>{{ $service['name'] }}</li>
                        @endforeach
                        @if (count($listing->services) > 4)
                            <li>+{{ count($listing->services) - 4 }} serviços</li>
                        @endif
                    </ul>
                @endif
                <div class="actions">
                    <a class="btn primary" href="{{ $base }}?{{ $portalQuery($listing) }}{{ $serviceQuery }}">Ver site da empresa<span class="sr-only">: {{ $listing->business_name }}</span></a>
                    <a class="btn secondary" href="{{ $base }}/solicitar?{{ $portalQuery($listing) }}{{ $serviceQuery }}">Solicitar orçamento<span class="sr-only"> a {{ $listing->business_name }}</span></a>
                </div>
            </article>
        </li>
    @endforeach
</ul>
