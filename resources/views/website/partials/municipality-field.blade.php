{{--
    Municipality field with suggestions from the IBGE registry and "Usar minha localização".

    Variables: $fieldId, $label, $value (text), $municipalityId (int|null), $error (string|null), $hint (string|null),
    $coverage (list of {id, name, state}; empty = no coverage check), $coverageCompany (string), $required (bool),
    $variant ('site' | 'portal').

    The browser asks for the position only after the click, rounds it to 3 decimal places and sends it in the body of a
    same-origin POST; the server answers only the municipality. Choosing by hand stays available at every moment.
--}}
@php
    $coverage = $coverage ?? [];
    $hint = $hint ?? null;
    $required = $required ?? false;
@endphp
<div class="municipality-field" data-municipality-field data-suggest-url="/localizacao/municipios" data-locate-url="/localizacao/municipio" data-coverage="{{ json_encode(array_map(fn ($item) => (int) $item['id'], $coverage)) }}">
    <label for="{{ $fieldId }}">{{ $label }}</label>
    <div class="municipality-row">
        <input type="text" id="{{ $fieldId }}" name="{{ $fieldName ?? 'municipality' }}" value="{{ $value }}" maxlength="80" list="{{ $fieldId }}-options" autocomplete="off" placeholder="Ex.: Salvador do Sul - RS" data-municipality-input
            aria-describedby="{{ $fieldId }}-hint{{ $error ? ' '.$fieldId.'-error' : '' }}" @if ($required) required @endif @if ($error) aria-invalid="true" @endif>
        <button type="button" class="locate" data-locate-button hidden>
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 2v3M12 19v3M2 12h3M19 12h3M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM12 11a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>
            <span data-locate-label>Usar minha localização</span>
        </button>
    </div>
    @if (($idName ?? 'municipality_id') !== false)
        <input type="hidden" name="{{ $idName ?? 'municipality_id' }}" value="{{ $municipalityId }}" data-municipality-id>
    @else
        <input type="hidden" value="{{ $municipalityId }}" data-municipality-id>
    @endif
    <datalist id="{{ $fieldId }}-options" data-municipality-options></datalist>
    <p class="hint" id="{{ $fieldId }}-hint">{{ $hint ?? 'Digite e escolha o município na lista.' }}</p>
    @if ($error)<p class="error field-error" id="{{ $fieldId }}-error">{{ $error }}</p>@endif
    <div class="locate-status" data-locate-status role="status" aria-live="polite"></div>
    @if ($coverage !== [])
        <div class="coverage-warning" data-coverage-warning hidden>
            <p><strong>Este município está fora da área informada por {{ $coverageCompany }}.</strong> A empresa atende em: {{ collect($coverage)->map(fn ($item) => $item['name'].', '.$item['state'])->implode('; ') }}.</p>
            <p>Revise o município ou mantenha-o, se precisar do serviço em outra cidade. A empresa avalia o pedido.</p>
        </div>
    @endif
</div>
