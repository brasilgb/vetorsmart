import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type Municipality = { id: number; name: string; state: string; label: string; input: string };

/**
 * Suggestions from the IBGE registry while typing (same-origin endpoint, no position involved).
 */
function useSuggestions(text: string) {
    const [items, setItems] = useState<Municipality[]>([]);

    useEffect(() => {
        const query = text.trim();

        if (query.length < 2) {
            setItems([]);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`/localizacao/municipios?busca=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => (response.ok ? response.json() : { municipalities: [] }))
                .then((data: { municipalities?: Municipality[] }) => setItems(data.municipalities ?? []))
                .catch(() => undefined);
        }, 200);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [text]);

    return items;
}

/**
 * A text field that lists matching municipalities as buttons; choosing one calls onChoose. Keyboard: type, Tab to the
 * list, Enter to choose.
 */
function Search({ id, label, hint, onChoose, exclude, disabled, error }: { id: string; label: string; hint?: string; onChoose: (municipality: Municipality) => void; exclude: number[]; disabled?: boolean; error?: string }) {
    const [text, setText] = useState('');
    const suggestions = useSuggestions(text).filter((item) => !exclude.includes(item.id));
    const listId = useId();
    const input = useRef<HTMLInputElement>(null);

    return (
        <div className="grid gap-1.5">
            <label htmlFor={id} className="text-sm font-medium text-vs-text">{label}</label>
            <Input id={id} ref={input} value={text} disabled={disabled} onChange={(event) => setText(event.target.value)} placeholder="Digite o nome do município" autoComplete="off" className="h-10" aria-describedby={`${id}-hint`} aria-controls={listId} aria-invalid={error ? true : undefined} />
            <p id={`${id}-hint`} className="text-xs text-vs-muted">{hint ?? 'Digite ao menos duas letras e escolha na lista.'}</p>
            {error && <p className="text-sm text-vs-danger">{error}</p>}
            <ul id={listId} className="grid gap-1" aria-label="Municípios encontrados">
                {suggestions.map((item) => (
                    <li key={item.id}>
                        <button
                            type="button"
                            className="w-full rounded-md border border-vs-border/70 px-3 py-2 text-left text-sm text-vs-text hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none"
                            onClick={() => {
                                onChoose(item);
                                setText('');
                                input.current?.focus();
                            }}
                        >
                            {item.label}
                        </button>
                    </li>
                ))}
            </ul>
            <span className="sr-only" aria-live="polite">{text.trim().length >= 2 ? `${suggestions.length} municípios encontrados` : ''}</span>
        </div>
    );
}

export function MunicipalityPicker({ id, label, value, onChange, disabled, error }: { id: string; label: string; value: Municipality | null; onChange: (value: Municipality | null) => void; disabled?: boolean; error?: string }) {
    return (
        <div className="grid gap-2">
            {value ? (
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-vs-muted">{label}:</span>
                    <strong className="text-vs-text">{value.label}</strong>
                    {!disabled && <Button type="button" variant="outline" size="sm" onClick={() => onChange(null)}>Trocar</Button>}
                </div>
            ) : (
                <Search id={id} label={label} onChoose={onChange} exclude={[]} disabled={disabled} error={error} />
            )}
        </div>
    );
}

export function MunicipalityMultiPicker({ id, label, values, onChange, exclude, max, disabled, error }: { id: string; label: string; values: Municipality[]; onChange: (values: Municipality[]) => void; exclude: number[]; max: number; disabled?: boolean; error?: string }) {
    return (
        <div className="grid gap-3">
            {values.length > 0 && (
                <ul className="flex flex-wrap gap-2" aria-label="Municípios atendidos escolhidos">
                    {values.map((item) => (
                        <li key={item.id} className="inline-flex items-center gap-1 rounded-full border border-vs-border/70 bg-muted px-3 py-1 text-sm text-vs-text">
                            {item.label}
                            {!disabled && (
                                <button type="button" className="ml-1 rounded-full px-1 text-vs-muted hover:text-vs-danger focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none" aria-label={`Remover ${item.label}`} onClick={() => onChange(values.filter((value) => value.id !== item.id))}>
                                    ×
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {values.length < max ? (
                <Search id={id} label={label} hint={`Adicione os municípios onde você atende, além da sede (até ${max}).`} onChoose={(item) => onChange([...values, item])} exclude={[...exclude, ...values.map((value) => value.id)]} disabled={disabled} error={error} />
            ) : (
                <p className="text-xs text-vs-muted">Limite de {max} municípios atingido.</p>
            )}
        </div>
    );
}
