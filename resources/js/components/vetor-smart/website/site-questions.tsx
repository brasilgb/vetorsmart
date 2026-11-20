import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { draft as websiteDraft, preview } from '@/routes/tenant/website';
import { destroy as imageDestroy, store as imageStore } from '@/routes/tenant/website/images';
import { media as previewMedia } from '@/routes/tenant/website/preview';
import { destroy as servicePhotoDestroy, store as servicePhotoStore } from '@/routes/tenant/website/service-photo';
import { MunicipalityMultiPicker, MunicipalityPicker, type Municipality } from './municipality-picker';

export type SiteImage = { file: string; width: number; height: number };

type ServiceDetail = { description?: string | null; price_public?: boolean; photo?: SiteImage | null };

export type SiteDraft = {
    headline: string | null;
    about: string | null;
    differentials: string[];
    trust_items: { label: string; source: string; url: string | null }[];
    accent_color: string;
    palette: string | null;
    font: string;
    service_ids: number[];
    service_details: Record<string, ServiceDetail> | [];
    professional_ids: number[];
    show_professionals: boolean;
    show_hours: boolean;
    show_prices: boolean;
    show_phone: boolean;
    show_whatsapp: boolean;
    show_email: boolean;
    show_address: boolean;
    logo: SiteImage | null;
    hero: SiteImage | null;
    hero_alt: string | null;
    about_photo: SiteImage | null;
    base_municipality_id: number | null;
    served_municipality_ids: number[];
    service_mode: 'at_company' | 'at_customer' | 'both' | null;
    seo: { title: string | null; description: string | null; indexable: boolean };
};

export type QuestionsProps = {
    draft: SiteDraft;
    options: {
        services: { id: number; name: string; description: string | null; price: string }[];
        professionals: { id: number; name: string }[];
        palettes: { key: string; label: string; accent: string; tone: string; suggested: boolean }[];
    };
    business: {
        name: string;
        contacts: { phone: string | null; whatsapp: string | null; email: string | null; address: string | null; hours: boolean };
        settings_city: Municipality | null;
    };
    location: { base: Municipality | null; served: Municipality[] };
    seoSuggestion: { title: string; description: string };
    siteUrl: string | null;
    segment: string | null;
    canEdit: boolean;
};

const MAX_SERVED = 60;

const questions = [
    { key: 'identity', title: 'Como sua empresa deve aparecer no site?' },
    { key: 'photo', title: 'Qual foto representa melhor seu trabalho?' },
    { key: 'about', title: 'O que sua empresa faz e o que a diferencia?' },
    { key: 'location', title: 'Onde fica sua empresa e em quais municípios ela atende?' },
    { key: 'mode', title: 'O cliente vai até você, você vai até o cliente ou ambos?' },
    { key: 'services', title: 'Quais serviços você quer mostrar e em que ordem?' },
    { key: 'contact', title: 'Quais contatos, horários e endereço podem aparecer publicamente?' },
    { key: 'colors', title: 'Quais cores combinam com sua empresa?' },
    { key: 'seo', title: 'Como sua empresa deve aparecer no Google?' },
] as const;

type QuestionKey = (typeof questions)[number]['key'];

const textareaClass = 'w-full rounded-md border border-input bg-card p-3 text-sm text-vs-text disabled:opacity-60';
const primary = 'h-10 bg-vs-blue text-white hover:bg-vs-blue-hover';

/**
 * Meu Site as nine plain questions. Each answer saves only its own part of the draft; nothing reaches visitors before
 * the publication. The server validates every field and enforces the permission.
 */
export function SiteQuestions(props: QuestionsProps) {
    const { draft } = props;
    const [current, setCurrent] = useState<QuestionKey>('identity');
    const index = questions.findIndex((question) => question.key === current);
    const next = () => setCurrent(questions[Math.min(index + 1, questions.length - 1)].key);

    const answered: Record<QuestionKey, boolean> = {
        identity: Boolean(draft.logo || draft.headline),
        photo: Boolean(draft.hero),
        about: Boolean(draft.about || draft.differentials.length > 0),
        location: Boolean(draft.base_municipality_id),
        mode: Boolean(draft.service_mode),
        services: draft.service_ids.length > 0,
        contact: true,
        colors: Boolean(draft.palette),
        seo: Boolean(draft.seo.title || draft.seo.description),
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
            <nav aria-label="Perguntas do Meu Site">
                <ol className="grid gap-1">
                    {questions.map((question, position) => (
                        <li key={question.key}>
                            <button
                                type="button"
                                onClick={() => setCurrent(question.key)}
                                aria-current={current === question.key ? 'step' : undefined}
                                className={`flex w-full items-start gap-3 rounded-md px-3 py-2 text-left text-sm ${current === question.key ? 'bg-vs-blue text-white' : 'text-vs-text hover:bg-muted'}`}
                            >
                                <span className={`mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${current === question.key ? 'bg-white text-vs-blue' : answered[question.key] ? 'bg-emerald-600 text-white' : 'border border-vs-border text-vs-muted'}`} aria-hidden="true">
                                    {answered[question.key] && current !== question.key ? '✓' : position + 1}
                                </span>
                                <span>
                                    {question.title}
                                    <span className="sr-only">{answered[question.key] ? ' (respondida)' : ' (pendente)'}</span>
                                </span>
                            </button>
                        </li>
                    ))}
                </ol>
                <div className="mt-4 grid gap-2 text-sm">
                    <a href={preview.url()} target="_blank" rel="noopener noreferrer" className="font-medium text-vs-link hover:underline">Prévia no computador</a>
                    <a href={preview.url({ query: { dispositivo: 'celular' } })} target="_blank" rel="noopener noreferrer" className="font-medium text-vs-link hover:underline">Prévia no celular</a>
                </div>
            </nav>

            <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                <div>
                    <p className="text-xs font-medium tracking-wide text-vs-muted uppercase">Pergunta {index + 1} de {questions.length}</p>
                    <h2 className="mt-1 text-lg font-semibold text-vs-text">{questions[index].title}</h2>
                </div>
                {current === 'identity' && <IdentityStep {...props} onSaved={next} />}
                {current === 'photo' && <PhotoStep {...props} onSaved={next} />}
                {current === 'about' && <AboutStep {...props} onSaved={next} />}
                {current === 'location' && <LocationStep {...props} onSaved={next} />}
                {current === 'mode' && <ModeStep {...props} onSaved={next} />}
                {current === 'services' && <ServicesStep {...props} onSaved={next} />}
                {current === 'contact' && <ContactStep {...props} onSaved={next} />}
                {current === 'colors' && <ColorsStep {...props} onSaved={next} />}
                {current === 'seo' && <SeoStep {...props} onSaved={() => undefined} />}
                <p className="text-xs text-vs-muted">As respostas ficam no rascunho. Veja a prévia e publique na aba Publicação.</p>
            </Card>
        </div>
    );
}

type StepProps = QuestionsProps & { onSaved: () => void };

function SaveBar({ canEdit, processing, label = 'Salvar e continuar' }: { canEdit: boolean; processing: boolean; label?: string }) {
    if (!canEdit) {
        return <p className="text-sm text-vs-muted">Somente o proprietário ou um administrador altera o site.</p>;
    }

    return (
        <div>
            <Button type="submit" disabled={processing} className={primary}>{processing ? 'Salvando…' : label}</Button>
        </div>
    );
}

function StepForm({ onSubmit, children }: { onSubmit: () => void; children: ReactNode }) {
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
            className="grid gap-5"
        >
            {children}
        </form>
    );
}

function IdentityStep({ draft, business, segment, canEdit, onSaved }: StepProps) {
    const form = useForm({ section: 'identity', headline: draft.headline ?? '' });

    return (
        <div className="grid gap-6">
            <div className="grid gap-1 text-sm">
                <span className="text-vs-muted">Nome exibido no site</span>
                <strong className="text-vs-text">{business.name}</strong>
                <span className="text-xs text-vs-muted">O nome comercial é alterado em Configurações.</span>
            </div>
            <ImageField kind="logo" label="Logomarca" hint="Aparece no topo do site, ao lado do nome. Prefira fundo transparente (PNG ou WebP)." image={draft.logo} canEdit={canEdit} />
            <StepForm onSubmit={() => form.patch(websiteDraft.url(), { preserveScroll: true, onSuccess: onSaved })}>
                <Field id="headline" label="Frase de destaque (título principal)" error={form.errors.headline} hint={`Diga o que você faz e onde. Sem frase, o site usa "${segment ?? 'Seu segmento'} em ${business.settings_city?.label ?? 'sua cidade'}".`}>
                    <Input id="headline" value={form.data.headline} maxLength={160} disabled={!canEdit} onChange={(event) => form.setData('headline', event.target.value)} placeholder="Ex.: Instalação e manutenção de ar-condicionado em Montenegro" className="h-10" />
                </Field>
                <SaveBar canEdit={canEdit} processing={form.processing} />
            </StepForm>
        </div>
    );
}

function PhotoStep({ draft, segment, canEdit, onSaved }: StepProps) {
    const form = useForm({ section: 'photo', hero_alt: draft.hero_alt ?? '' });

    return (
        <div className="grid gap-6">
            <p className="text-sm text-vs-muted">Use uma foto real: da empresa, da equipe ou de um trabalho realizado. Ela aparece na primeira tela do site.</p>
            <ImageField kind="hero" label="Foto principal" hint="Na horizontal, com boa luz. JPG, PNG ou WebP de até 2 MB." image={draft.hero} canEdit={canEdit} />
            {!draft.hero && (
                <p role="note" className="rounded-md border border-vs-border/70 bg-muted p-3 text-sm text-vs-text">
                    Sem foto própria, o site mostra uma ilustração padrão de {segment ?? 'serviços'}. Você pode trocar quando quiser.
                </p>
            )}
            <StepForm onSubmit={() => form.patch(websiteDraft.url(), { preserveScroll: true, onSuccess: onSaved })}>
                <Field id="hero_alt" label="Descreva a foto (para leitores de tela e para o Google)" error={form.errors.hero_alt} hint="Ex.: Técnico instalando ar-condicionado split em uma sala.">
                    <Input id="hero_alt" value={form.data.hero_alt} maxLength={160} disabled={!canEdit} onChange={(event) => form.setData('hero_alt', event.target.value)} className="h-10" />
                </Field>
                <SaveBar canEdit={canEdit} processing={form.processing} />
            </StepForm>
        </div>
    );
}

function AboutStep({ draft, canEdit, onSaved }: StepProps) {
    const form = useForm({
        section: 'about',
        about: draft.about ?? '',
        differentials: draft.differentials.length > 0 ? draft.differentials : [''],
        trust_items: draft.trust_items.map((item) => ({ label: item.label, source: item.source, url: item.url ?? '' })),
    });
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <div className="grid gap-6">
            <StepForm onSubmit={() => form.patch(websiteDraft.url(), { preserveScroll: true, onSuccess: onSaved })}>
                <Field id="about" label="Apresentação (Quem somos)" error={form.errors.about} hint="Conte em poucas linhas o que a empresa faz, há quanto tempo e como trabalha.">
                    <textarea id="about" rows={5} maxLength={2000} value={form.data.about} disabled={!canEdit} onChange={(event) => form.setData('about', event.target.value)} className={textareaClass} />
                </Field>
                <fieldset className="grid gap-2">
                    <legend className="mb-1 text-sm font-medium text-vs-text">Diferenciais (até 6)</legend>
                    {form.data.differentials.map((item, position) => (
                        <div key={position} className="flex gap-2">
                            <Input aria-label={`Diferencial ${position + 1}`} value={item} maxLength={120} disabled={!canEdit} onChange={(event) => form.setData('differentials', form.data.differentials.map((value, i) => (i === position ? event.target.value : value)))} placeholder="Ex.: Atendimento no mesmo dia" className="h-10" />
                            {canEdit && <Button type="button" variant="ghost" onClick={() => form.setData('differentials', form.data.differentials.filter((_, i) => i !== position))}>Remover</Button>}
                        </div>
                    ))}
                    {canEdit && form.data.differentials.length < 6 && <Button type="button" variant="outline" className="w-fit" onClick={() => form.setData('differentials', [...form.data.differentials, ''])}>Adicionar diferencial</Button>}
                </fieldset>
                <fieldset className="grid gap-3">
                    <legend className="mb-1 text-sm font-medium text-vs-text">Certificações, tempo de atuação ou avaliações</legend>
                    <p className="text-xs text-vs-muted">Só aparecem no site com a fonte. Ex.: "Certificação NR-10" com fonte "SENAI, 2023"; "Desde 2012" com fonte "CNPJ aberto em 2012".</p>
                    {form.data.trust_items.map((item, position) => (
                        <div key={position} className="grid gap-2 rounded-md border border-vs-border/70 p-3 md:grid-cols-3">
                            <Field id={`trust-${position}-label`} label="Informação" error={errors[`trust_items.${position}.label`]}>
                                <Input id={`trust-${position}-label`} value={item.label} maxLength={120} disabled={!canEdit} onChange={(event) => form.setData('trust_items', form.data.trust_items.map((value, i) => (i === position ? { ...value, label: event.target.value } : value)))} className="h-10" />
                            </Field>
                            <Field id={`trust-${position}-source`} label="Fonte" error={errors[`trust_items.${position}.source`]}>
                                <Input id={`trust-${position}-source`} value={item.source} maxLength={160} disabled={!canEdit} onChange={(event) => form.setData('trust_items', form.data.trust_items.map((value, i) => (i === position ? { ...value, source: event.target.value } : value)))} className="h-10" />
                            </Field>
                            <Field id={`trust-${position}-url`} label="Link da fonte (opcional)" error={errors[`trust_items.${position}.url`]}>
                                <Input id={`trust-${position}-url`} value={item.url} maxLength={255} disabled={!canEdit} onChange={(event) => form.setData('trust_items', form.data.trust_items.map((value, i) => (i === position ? { ...value, url: event.target.value } : value)))} placeholder="https://" className="h-10" />
                            </Field>
                            {canEdit && <Button type="button" variant="ghost" className="w-fit md:col-span-3" onClick={() => form.setData('trust_items', form.data.trust_items.filter((_, i) => i !== position))}>Remover</Button>}
                        </div>
                    ))}
                    {canEdit && form.data.trust_items.length < 6 && <Button type="button" variant="outline" className="w-fit" onClick={() => form.setData('trust_items', [...form.data.trust_items, { label: '', source: '', url: '' }])}>Adicionar informação com fonte</Button>}
                </fieldset>
                <SaveBar canEdit={canEdit} processing={form.processing} />
            </StepForm>
            <ImageField kind="about" label="Foto de Quem somos (opcional)" hint="A equipe, a fachada ou o espaço de trabalho." image={draft.about_photo} canEdit={canEdit} />
        </div>
    );
}

function LocationStep({ location, business, canEdit, onSaved }: StepProps) {
    const [base, setBase] = useState<Municipality | null>(location.base ?? business.settings_city);
    const [served, setServed] = useState<Municipality[]>(location.served);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const save = () => {
        router.patch(
            websiteDraft.url(),
            { section: 'location', base_municipality_id: base?.id ?? null, served_municipality_ids: served.filter((item) => item.id !== base?.id).map((item) => item.id) },
            { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: (bag) => setErrors(bag), onSuccess: () => { setErrors({}); onSaved(); } },
        );
    };

    return (
        <StepForm onSubmit={save}>
            <MunicipalityPicker id="base-municipality" label="Município da sede" value={base} onChange={setBase} disabled={!canEdit} error={errors.base_municipality_id} />
            {business.settings_city && base?.id !== business.settings_city.id && <p className="-mt-3 text-xs text-vs-muted">Em Configurações, a cidade cadastrada é {business.settings_city.label}.</p>}
            <MunicipalityMultiPicker id="served-municipalities" label="Outros municípios atendidos" values={served} onChange={setServed} exclude={base ? [base.id] : []} max={MAX_SERVED} disabled={!canEdit} error={errors.served_municipality_ids} />
            <p className="text-xs text-vs-muted">No Portal Regional, sua empresa aparece nas buscas pela sede e por cada município atendido, com o rótulo "Atende em".</p>
            <SaveBar canEdit={canEdit} processing={processing} />
        </StepForm>
    );
}

function ModeStep({ draft, canEdit, onSaved }: StepProps) {
    const form = useForm({ section: 'mode', service_mode: draft.service_mode ?? '' });
    const choices = [
        { value: 'at_company', label: 'O cliente vem até nós', hint: 'O atendimento acontece no endereço da empresa.' },
        { value: 'at_customer', label: 'Nós vamos até o cliente', hint: 'O atendimento acontece no endereço do cliente.' },
        { value: 'both', label: 'As duas opções', hint: 'O cliente escolhe.' },
    ];

    return (
        <StepForm onSubmit={() => form.patch(websiteDraft.url(), { preserveScroll: true, onSuccess: onSaved })}>
            <fieldset className="grid gap-2">
                <legend className="sr-only">Como o atendimento acontece</legend>
                {choices.map((choice) => (
                    <label key={choice.value} className={`flex cursor-pointer items-start gap-3 rounded-md border px-4 py-3 text-sm ${form.data.service_mode === choice.value ? 'border-vs-blue ring-2 ring-vs-blue/30' : 'border-vs-border/70'}`}>
                        <input type="radio" name="service_mode" value={choice.value} checked={form.data.service_mode === choice.value} disabled={!canEdit} onChange={() => form.setData('service_mode', choice.value)} className="mt-1 size-4 accent-vs-blue" />
                        <span>
                            <span className="block font-medium text-vs-text">{choice.label}</span>
                            <span className="block text-vs-muted">{choice.hint}</span>
                        </span>
                    </label>
                ))}
            </fieldset>
            {form.errors.service_mode && <p className="text-sm text-vs-danger">{form.errors.service_mode}</p>}
            <SaveBar canEdit={canEdit} processing={form.processing} />
        </StepForm>
    );
}

function ServicesStep({ draft, options, canEdit, onSaved }: StepProps) {
    const details = (Array.isArray(draft.service_details) ? {} : draft.service_details) as Record<string, ServiceDetail>;
    const catalog = new Map(options.services.map((service) => [service.id, service]));
    const [order, setOrder] = useState<number[]>(draft.service_ids.filter((id) => catalog.has(id)));
    const [texts, setTexts] = useState<Record<number, { description: string; price_public: boolean }>>(
        Object.fromEntries(options.services.map((service) => [service.id, { description: details[String(service.id)]?.description ?? '', price_public: details[String(service.id)]?.price_public ?? true }])),
    );
    const [showProfessionals, setShowProfessionals] = useState(draft.show_professionals);
    const [professionals, setProfessionals] = useState<number[]>(draft.professional_ids);
    const [processing, setProcessing] = useState(false);

    const move = (id: number, step: -1 | 1) => {
        const position = order.indexOf(id);
        const target = position + step;

        if (target < 0 || target >= order.length) {
            return;
        }

        const copy = [...order];
        [copy[position], copy[target]] = [copy[target], copy[position]];
        setOrder(copy);
    };

    const save = () => {
        router.patch(
            websiteDraft.url(),
            {
                section: 'services',
                service_ids: order,
                service_details: Object.fromEntries(order.map((id) => [id, texts[id]])),
                show_prices: true,
                show_professionals: showProfessionals,
                professional_ids: professionals,
            },
            { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onSuccess: onSaved },
        );
    };

    const available = options.services.filter((service) => !order.includes(service.id));

    return (
        <StepForm onSubmit={save}>
            <p className="text-sm text-vs-muted">Os serviços vêm do seu catálogo (menu Serviços). Escolha quais aparecem no site e a ordem. Cada um ganha o botão "Solicitar orçamento".</p>
            {options.services.length === 0 && <p className="text-sm text-vs-muted">Nenhum serviço ativo cadastrado. Cadastre em Serviços.</p>}
            {order.length > 0 && (
                <ol className="grid gap-3" aria-label="Serviços no site, na ordem de exibição">
                    {order.map((id, position) => {
                        const service = catalog.get(id)!;
                        const photo = details[String(id)]?.photo ?? null;

                        return (
                            <li key={id} className="grid gap-3 rounded-md border border-vs-border/70 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <strong className="text-vs-text">{position + 1}. {service.name}</strong>
                                    {canEdit && (
                                        <div className="flex flex-wrap gap-1">
                                            <Button type="button" variant="outline" size="sm" disabled={position === 0} onClick={() => move(id, -1)} aria-label={`Subir ${service.name}`}>↑</Button>
                                            <Button type="button" variant="outline" size="sm" disabled={position === order.length - 1} onClick={() => move(id, 1)} aria-label={`Descer ${service.name}`}>↓</Button>
                                            <Button type="button" variant="ghost" size="sm" onClick={() => setOrder(order.filter((value) => value !== id))}>Tirar do site</Button>
                                        </div>
                                    )}
                                </div>
                                <Field id={`service-${id}-description`} label="Descrição no site" hint={service.description ? `Sem texto aqui, usamos a descrição do catálogo: "${service.description}"` : 'Explique o que está incluído.'}>
                                    <textarea id={`service-${id}-description`} rows={2} maxLength={500} value={texts[id]?.description ?? ''} disabled={!canEdit} onChange={(event) => setTexts({ ...texts, [id]: { ...texts[id], description: event.target.value } })} className={textareaClass} />
                                </Field>
                                <label className="flex items-center gap-3 text-sm text-vs-text">
                                    <input type="checkbox" checked={texts[id]?.price_public ?? true} disabled={!canEdit} onChange={(event) => setTexts({ ...texts, [id]: { ...texts[id], price_public: event.target.checked } })} className="size-4 accent-vs-blue" />
                                    Mostrar o preço ({service.price}). Desmarcado, o site mostra "Sob consulta".
                                </label>
                                <ServicePhoto serviceId={id} name={service.name} photo={photo} canEdit={canEdit} />
                            </li>
                        );
                    })}
                </ol>
            )}
            {available.length > 0 && canEdit && (
                <div className="grid gap-2">
                    <p className="text-sm font-medium text-vs-text">Adicionar ao site</p>
                    <div className="flex flex-wrap gap-2">
                        {available.map((service) => (
                            <Button key={service.id} type="button" variant="outline" size="sm" onClick={() => setOrder([...order, service.id])}>+ {service.name}</Button>
                        ))}
                    </div>
                </div>
            )}
            <label className="flex items-center gap-3 text-sm text-vs-text">
                <input type="checkbox" checked={showProfessionals} disabled={!canEdit} onChange={(event) => setShowProfessionals(event.target.checked)} className="size-4 accent-vs-blue" />
                Mostrar a equipe no site
            </label>
            {showProfessionals && (
                <div className="grid gap-2 sm:grid-cols-2">
                    {options.professionals.map((person) => (
                        <label key={person.id} className="flex items-center gap-3 rounded-md border border-vs-border/70 px-3 py-2 text-sm text-vs-text">
                            <input type="checkbox" checked={professionals.includes(person.id)} disabled={!canEdit} onChange={() => setProfessionals(professionals.includes(person.id) ? professionals.filter((value) => value !== person.id) : [...professionals, person.id])} className="size-4 accent-vs-blue" />
                            {person.name}
                        </label>
                    ))}
                </div>
            )}
            <p className="text-xs text-vs-muted">Salve a ordem e os textos antes de enviar fotos de serviços recém-adicionados.</p>
            <SaveBar canEdit={canEdit} processing={processing} />
        </StepForm>
    );
}

function ServicePhoto({ serviceId, name, photo, canEdit }: { serviceId: number; name: string; photo: SiteImage | null; canEdit: boolean }) {
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const inputId = `service-photo-${serviceId}`;

    const send = () => {
        if (!file) {
            return;
        }

        router.post(servicePhotoStore.url({ service: serviceId }), { image: file }, {
            preserveScroll: true,
            forceFormData: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (bag) => setError(bag.image ?? 'Não foi possível enviar a foto.'),
            onSuccess: () => {
                setError(null);
                setFile(null);
            },
        });
    };

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium text-vs-text">Foto do serviço (opcional)</span>
            {photo && <img src={previewMedia.url({ file: photo.file })} alt={`Foto atual de ${name}`} width={photo.width || undefined} height={photo.height || undefined} className="aspect-[4/3] w-full max-w-[220px] rounded-md border border-vs-border/70 object-cover" />}
            {canEdit && (
                <div className="flex flex-wrap items-center gap-2">
                    <label htmlFor={inputId} className="sr-only">{`Escolher foto de ${name}`}</label>
                    <Input id={inputId} type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => setFile(event.target.files?.[0] ?? null)} className="h-10 max-w-xs" aria-invalid={error ? true : undefined} aria-describedby={error ? `${inputId}-error` : undefined} />
                    <Button type="button" variant="outline" disabled={processing || !file} onClick={send}>{processing ? 'Enviando…' : 'Enviar foto'}</Button>
                    {photo && <Button type="button" variant="ghost" onClick={() => router.delete(servicePhotoDestroy.url({ service: serviceId }), { preserveScroll: true })}>Remover foto</Button>}
                </div>
            )}
            {error && <p id={`${inputId}-error`} className="text-sm text-vs-danger">{error}</p>}
        </div>
    );
}

function ContactStep({ draft, business, canEdit, onSaved }: StepProps) {
    const form = useForm({ section: 'contact', show_phone: draft.show_phone, show_whatsapp: draft.show_whatsapp, show_email: draft.show_email, show_address: draft.show_address, show_hours: draft.show_hours });
    const contacts = business.contacts;
    const rows: { field: 'show_phone' | 'show_whatsapp' | 'show_email' | 'show_address' | 'show_hours'; label: string; value: string | null }[] = [
        { field: 'show_phone', label: 'Telefone', value: contacts.phone },
        { field: 'show_whatsapp', label: 'WhatsApp', value: contacts.whatsapp },
        { field: 'show_email', label: 'E-mail', value: contacts.email },
        { field: 'show_address', label: 'Endereço e botão "Como chegar"', value: contacts.address },
        { field: 'show_hours', label: 'Horários de funcionamento', value: contacts.hours ? 'cadastrados' : null },
    ];

    return (
        <StepForm onSubmit={() => form.patch(websiteDraft.url(), { preserveScroll: true, onSuccess: onSaved })}>
            <p className="text-sm text-vs-muted">Os dados vêm de Configurações. Marque o que pode aparecer para qualquer visitante. O que não estiver marcado ou cadastrado fica fora do site.</p>
            <fieldset className="grid gap-2">
                <legend className="sr-only">Informações públicas</legend>
                {rows.map((row) => (
                    <label key={row.field} className={`flex items-start gap-3 rounded-md border border-vs-border/70 px-4 py-3 text-sm ${row.value ? '' : 'opacity-70'}`}>
                        <input type="checkbox" checked={Boolean(form.data[row.field]) && Boolean(row.value)} disabled={!canEdit || !row.value} onChange={(event) => form.setData(row.field, event.target.checked)} className="mt-1 size-4 accent-vs-blue" />
                        <span>
                            <span className="block font-medium text-vs-text">{row.label}</span>
                            <span className="block break-words text-vs-muted">{row.value ?? 'Não cadastrado em Configurações'}</span>
                        </span>
                    </label>
                ))}
            </fieldset>
            <p className="text-xs text-vs-muted">O endereço só aparece se você marcar. Sem ele, o site mostra apenas o município da sede.</p>
            <SaveBar canEdit={canEdit} processing={form.processing} />
        </StepForm>
    );
}

function ColorsStep({ draft, options, canEdit, onSaved }: StepProps) {
    const form = useForm({ section: 'colors', palette: draft.palette ?? '', accent_color: (draft.accent_color || '#2563EB').toUpperCase(), font: draft.font || 'sans' });
    const choose = (key: string, accent: string) => form.setData((data) => ({ ...data, palette: key, accent_color: accent }));

    return (
        <StepForm onSubmit={() => form.patch(websiteDraft.url(), { preserveScroll: true, onSuccess: onSaved })}>
            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium text-vs-text">Paleta</legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {options.palettes.map((palette) => (
                        <label key={palette.key} className={`flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm ${form.data.palette === palette.key ? 'border-vs-blue ring-2 ring-vs-blue/30' : 'border-vs-border/70'}`}>
                            <input type="radio" name="palette" value={palette.key} checked={form.data.palette === palette.key} disabled={!canEdit} onChange={() => choose(palette.key, palette.accent)} className="sr-only" />
                            <span className="flex overflow-hidden rounded-md border border-vs-border" aria-hidden="true">
                                <span className="size-6" style={{ backgroundColor: palette.accent }} />
                                <span className="size-6" style={{ backgroundColor: palette.tone }} />
                            </span>
                            <span className="text-vs-text">{palette.label}</span>
                            {palette.suggested && <span className="ml-auto rounded-full bg-muted px-2 py-0.5 text-xs text-vs-muted">Sugerida para o seu segmento</span>}
                        </label>
                    ))}
                </div>
            </fieldset>
            <div className="grid gap-4 md:grid-cols-2">
                <Field id="accent_color" label="Cor principal (botões e links)" error={form.errors.accent_color} hint="Se a cor ficar clara demais para leitura, o site escurece o tom automaticamente.">
                    <div className="flex items-center gap-2">
                        <input type="color" aria-label="Escolher cor principal" value={form.data.accent_color} disabled={!canEdit} onChange={(event) => form.setData('accent_color', event.target.value.toUpperCase())} className="h-10 w-14 cursor-pointer rounded-md border border-input bg-card" />
                        <Input id="accent_color" value={form.data.accent_color} maxLength={7} disabled={!canEdit} onChange={(event) => form.setData('accent_color', event.target.value.toUpperCase())} className="h-10 font-mono" />
                    </div>
                </Field>
                <Field id="font" label="Tipografia" error={form.errors.font}>
                    <select id="font" value={form.data.font} disabled={!canEdit} onChange={(event) => form.setData('font', event.target.value)} className={selectClass}>
                        <option value="sans">Sem serifa (moderna)</option>
                        <option value="serif">Com serifa (clássica)</option>
                    </select>
                </Field>
            </div>
            <SaveBar canEdit={canEdit} processing={form.processing} />
        </StepForm>
    );
}

function SeoStep({ draft, seoSuggestion, siteUrl, business, canEdit, onSaved }: StepProps) {
    const form = useForm({ section: 'seo', seo: { title: draft.seo.title ?? '', description: draft.seo.description ?? '', indexable: draft.seo.indexable } });
    const title = form.data.seo.title || seoSuggestion.title;
    const description = form.data.seo.description || seoSuggestion.description;

    return (
        <StepForm onSubmit={() => form.patch(websiteDraft.url(), { preserveScroll: true, onSuccess: onSaved })}>
            <p className="text-sm text-vs-muted">Sugerimos um título e uma descrição a partir do nome, dos serviços e da região. Campo vazio usa a sugestão ao publicar.</p>
            <Field id="seo-title" label={`Título no Google (${form.data.seo.title.length}/60)`} error={form.errors['seo.title']}>
                <div className="flex flex-wrap gap-2">
                    <Input id="seo-title" value={form.data.seo.title} maxLength={70} disabled={!canEdit} placeholder={seoSuggestion.title} onChange={(event) => form.setData('seo', { ...form.data.seo, title: event.target.value })} className="h-10 min-w-0 flex-1" />
                    {canEdit && <Button type="button" variant="outline" onClick={() => form.setData('seo', { ...form.data.seo, title: seoSuggestion.title })}>Usar sugestão</Button>}
                </div>
            </Field>
            <Field id="seo-description" label={`Descrição no Google (${form.data.seo.description.length}/155)`} error={form.errors['seo.description']}>
                <div className="grid gap-2">
                    <textarea id="seo-description" rows={3} maxLength={160} value={form.data.seo.description} disabled={!canEdit} placeholder={seoSuggestion.description} onChange={(event) => form.setData('seo', { ...form.data.seo, description: event.target.value })} className={textareaClass} />
                    {canEdit && <Button type="button" variant="outline" className="w-fit" onClick={() => form.setData('seo', { ...form.data.seo, description: seoSuggestion.description })}>Usar sugestão</Button>}
                </div>
            </Field>
            <div className="grid gap-1 rounded-md border border-vs-border/70 bg-card p-4" aria-label="Prévia do resultado de busca">
                <p className="text-xs text-vs-muted">Prévia do resultado de busca</p>
                <p className="truncate text-xs text-emerald-800 dark:text-emerald-300">{siteUrl ?? 'Endereço do site'}</p>
                <p className="text-lg leading-snug text-[#1a0dab] dark:text-[#8ab4f8]">{title.length > 60 ? `${title.slice(0, 59)}…` : title}</p>
                <p className="text-sm text-vs-text">{description.length > 155 ? `${description.slice(0, 154)}…` : description}</p>
            </div>
            <label className="flex items-center gap-3 text-sm text-vs-text">
                <input type="checkbox" checked={form.data.seo.indexable} disabled={!canEdit} onChange={(event) => form.setData('seo', { ...form.data.seo, indexable: event.target.checked })} className="size-4 accent-vs-blue" />
                Permitir que o Google e outros buscadores mostrem o site de {business.name}
            </label>
            <p className="text-xs text-vs-muted">Não garantimos posição nos buscadores. A prévia e os sites de teste nunca são indexados.</p>
            <SaveBar canEdit={canEdit} processing={form.processing} label="Salvar" />
        </StepForm>
    );
}

/**
 * Logo, main photo or "Quem somos" photo of the draft: shows the current one (served only to the company, through the
 * preview), sends a new one or removes it. The server validates type and size and re-encodes the file.
 */
export function ImageField({ kind, label, hint, image, canEdit }: { kind: 'logo' | 'hero' | 'about'; label: string; hint: string; image: SiteImage | null; canEdit: boolean }) {
    const form = useForm<{ image: File | null }>({ image: null });
    const inputId = `image-${kind}`;

    return (
        <div className="grid content-start gap-3">
            <p className="text-sm font-medium text-vs-text">{label}</p>
            <p className="text-xs text-vs-muted">{hint}</p>
            {image ? (
                <img
                    src={previewMedia.url({ file: image.file })}
                    alt={`${label} atual`}
                    width={image.width || undefined}
                    height={image.height || undefined}
                    className={`rounded-md border border-vs-border/70 bg-muted object-contain ${kind === 'logo' ? 'h-16 w-auto max-w-[200px]' : 'aspect-video w-full max-w-sm object-cover'}`}
                />
            ) : (
                <p className="text-sm text-vs-muted">Nenhuma imagem enviada.</p>
            )}
            {canEdit && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(imageStore.url({ kind }), { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset() });
                    }}
                    className="grid gap-2"
                >
                    <label htmlFor={inputId} className="sr-only">{`Escolher ${label.toLowerCase()}`}</label>
                    <Input id={inputId} type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => form.setData('image', event.target.files?.[0] ?? null)} className="h-10" aria-invalid={form.errors.image ? true : undefined} aria-describedby={form.errors.image ? `${inputId}-error` : undefined} />
                    {form.errors.image && <p id={`${inputId}-error`} className="text-sm text-vs-danger">{form.errors.image}</p>}
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing || form.data.image === null} variant="outline" className="h-10">{form.processing ? 'Enviando…' : 'Enviar imagem'}</Button>
                        {image && (
                            <ConfirmAction
                                trigger={<Button type="button" variant="ghost" className="h-10">Remover</Button>}
                                title={`Remover ${label.toLowerCase()}?`}
                                description="A imagem sai do rascunho. O site publicado só muda na próxima publicação."
                                confirmLabel="Remover"
                                destructive
                                onConfirm={() => router.delete(imageDestroy.url({ kind }), { preserveScroll: true })}
                            />
                        )}
                    </div>
                </form>
            )}
        </div>
    );
}
