import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { draft as websiteDraft, publish, unpublish } from '@/routes/tenant/website';
import { store as domainStore, verify, primary, destroy as domainDestroy } from '@/routes/tenant/website/domains';

type Draft = {
    headline: string | null;
    about: string | null;
    differentials: string[];
    accent_color: string;
    font: string;
    service_ids: number[];
    professional_ids: number[];
    show_professionals: boolean;
    show_hours: boolean;
    seo: { title: string | null; description: string | null; indexable: boolean };
};

type Domain = {
    id: number;
    hostname: string;
    kind: string;
    status: string;
    is_primary: boolean;
    verified_at: string | null;
    dns: { type: string; name: string; value: string } | null;
};

type Props = {
    website: { name: string; status: string; is_live: boolean; published_at: string | null; updated_at: string | null; public_url: string | null; version: number | null };
    draft: Draft;
    options: { services: { id: number; name: string }[]; professionals: { id: number; name: string }[]; colors: { value: string; label: string }[] };
    domains: Domain[];
    canEdit: boolean;
    segment: string | null;
};

const tabs = [
    { key: 'overview', label: 'Visão geral' },
    { key: 'appearance', label: 'Aparência' },
    { key: 'content', label: 'Conteúdo' },
    { key: 'services', label: 'Serviços' },
    { key: 'domains', label: 'Domínios' },
    { key: 'seo', label: 'SEO' },
    { key: 'publish', label: 'Publicação' },
];

/**
 * Meu Site. Each section saves only its own fields to the draft. Visitors see the
 * site only after an explicit publication; the server enforces every permission.
 */
export default function WebsiteIndex({ website, draft, options, domains, canEdit, segment }: Props) {
    const [tab, setTab] = useState('overview');
    const disabled = !canEdit;

    const appearance = useForm({ section: 'appearance', accent_color: draft.accent_color, font: draft.font });
    const content = useForm({ section: 'content', headline: draft.headline ?? '', about: draft.about ?? '', differentials: draft.differentials, show_hours: draft.show_hours });
    const services = useForm({ section: 'services', service_ids: draft.service_ids, show_professionals: draft.show_professionals, professional_ids: draft.professional_ids });
    const seo = useForm({ section: 'seo', seo: { title: draft.seo.title ?? '', description: draft.seo.description ?? '', indexable: draft.seo.indexable } });
    const domain = useForm({ kind: 'subdomain', hostname: '' });

    const toggle = (list: number[], id: number) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]);

    return (
        <>
            <Head title="Meu Site" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Meu Site"
                    description="Seu site profissional. Alterações ficam em rascunho até a publicação."
                    action={website.is_live && website.public_url ? <a href={website.public_url} target="_blank" rel="noopener noreferrer" className="text-sm font-medium text-vs-blue hover:underline">Abrir site publicado</a> : null}
                />

                <nav aria-label="Seções do Meu Site" className="flex flex-wrap gap-2 border-b border-vs-border/70 pb-3">
                    {tabs.map((item) => (
                        <button key={item.key} type="button" onClick={() => setTab(item.key)} className={`rounded-md px-3 py-2 text-sm font-medium ${tab === item.key ? 'bg-vs-blue text-white' : 'text-vs-muted hover:bg-muted hover:text-foreground'}`} aria-current={tab === item.key ? 'page' : undefined}>
                            {item.label}
                        </button>
                    ))}
                </nav>

                {tab === 'overview' && (
                    <Card className="grid gap-3 border-vs-border/70 p-6 shadow-sm text-sm">
                        <div><span className="text-vs-muted">Nome:</span> {website.name}</div>
                        <div><span className="text-vs-muted">Modelo:</span> {segment ? `Modelo base · ${segment}` : 'Modelo base'}</div>
                        <div><span className="text-vs-muted">Situação:</span> {website.is_live ? 'No ar' : 'Não publicado'}</div>
                        <div><span className="text-vs-muted">Endereço público:</span> {website.public_url ?? 'Disponível após verificar um domínio'}</div>
                        <div><span className="text-vs-muted">Última publicação:</span> {website.published_at ?? '—'}{website.version ? ` (versão ${website.version})` : ''}</div>
                        <div><span className="text-vs-muted">Último rascunho:</span> {website.updated_at ?? '—'}</div>
                        <div><Link href="/meu-site/previa" className="font-medium text-vs-blue hover:underline">Ver prévia do rascunho</Link></div>
                    </Card>
                )}

                {tab === 'appearance' && (
                    <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Aparência</h2>
                        <form onSubmit={(e) => { e.preventDefault(); appearance.patch(websiteDraft.url(), { preserveScroll: true }); }} className="grid gap-5 md:grid-cols-2">
                            <div className="md:col-span-2 flex flex-wrap gap-3" role="radiogroup" aria-label="Cor principal">
                                {options.colors.map((color) => (
                                    <label key={color.value} className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm ${appearance.data.accent_color === color.value ? 'border-vs-blue ring-2 ring-vs-blue/30' : 'border-vs-border'}`}>
                                        <input type="radio" name="accent" value={color.value} checked={appearance.data.accent_color === color.value} disabled={disabled} onChange={() => appearance.setData('accent_color', color.value)} className="sr-only" />
                                        <span className="size-5 rounded-full border border-vs-border" style={{ backgroundColor: color.value }} aria-hidden="true" />
                                        {color.label}
                                    </label>
                                ))}
                            </div>
                            <Field id="font" label="Tipografia" error={appearance.errors.font}>
                                <select id="font" value={appearance.data.font} disabled={disabled} onChange={(e) => appearance.setData('font', e.target.value)} className={selectClass}>
                                    <option value="sans">Sem serifa (moderna)</option>
                                    <option value="serif">Com serifa (clássica)</option>
                                </select>
                            </Field>
                            <p className="md:col-span-2 text-xs text-vs-muted">Logotipo próprio do site ainda não está disponível. O site usa a identidade escolhida acima.</p>
                            {canEdit && <div className="md:col-span-2"><Button type="submit" disabled={appearance.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar aparência</Button></div>}
                        </form>
                    </Card>
                )}

                {tab === 'content' && (
                    <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Conteúdo</h2>
                        <form onSubmit={(e) => { e.preventDefault(); content.patch(websiteDraft.url(), { preserveScroll: true }); }} className="grid gap-5">
                            <Field id="headline" label="Título principal" error={content.errors.headline}>
                                <Input id="headline" value={content.data.headline} disabled={disabled} onChange={(e) => content.setData('headline', e.target.value)} className="h-10" />
                            </Field>
                            <Field id="about" label="Texto institucional" error={content.errors.about}>
                                <textarea id="about" rows={4} value={content.data.about} disabled={disabled} onChange={(e) => content.setData('about', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                            </Field>
                            <div className="grid gap-2">
                                <p className="text-sm font-medium text-vs-text">Diferenciais (até 6)</p>
                                {content.data.differentials.map((item, index) => (
                                    <div key={index} className="flex gap-2">
                                        <Input aria-label={`Diferencial ${index + 1}`} value={item} disabled={disabled} onChange={(e) => content.setData('differentials', content.data.differentials.map((x, i) => (i === index ? e.target.value : x)))} className="h-10" />
                                        {!disabled && <Button type="button" variant="ghost" onClick={() => content.setData('differentials', content.data.differentials.filter((_, i) => i !== index))}>Remover</Button>}
                                    </div>
                                ))}
                                {!disabled && content.data.differentials.length < 6 && (
                                    <Button type="button" variant="outline" className="w-fit" onClick={() => content.setData('differentials', [...content.data.differentials, ''])}>Adicionar diferencial</Button>
                                )}
                            </div>
                            <label className="flex items-center gap-3 text-sm text-vs-text">
                                <input type="checkbox" checked={content.data.show_hours} disabled={disabled} onChange={(e) => content.setData('show_hours', e.target.checked)} className="size-4 accent-vs-blue" />
                                Mostrar horários de funcionamento (cadastrados em Configurações)
                            </label>
                            <p className="text-xs text-vs-muted">Telefone, WhatsApp, e-mail e endereço vêm de Configurações, sem duplicação.</p>
                            {canEdit && <div><Button type="submit" disabled={content.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar conteúdo</Button></div>}
                        </form>
                    </Card>
                )}

                {tab === 'services' && (
                    <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Serviços publicados</h2>
                        <p className="text-sm text-vs-muted">Os serviços são cadastrados em Serviços. Aqui você escolhe quais aparecem no site.</p>
                        {options.services.length === 0 ? <p className="text-sm text-vs-muted">Nenhum serviço ativo cadastrado.</p> : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {options.services.map((service) => (
                                    <label key={service.id} className="flex items-center gap-3 rounded-md border border-vs-border/70 px-3 py-2 text-sm text-vs-text">
                                        <input type="checkbox" checked={services.data.service_ids.includes(service.id)} disabled={disabled} onChange={() => services.setData('service_ids', toggle(services.data.service_ids, service.id))} className="size-4 accent-vs-blue" />
                                        {service.name}
                                    </label>
                                ))}
                            </div>
                        )}
                        <label className="flex items-center gap-3 text-sm text-vs-text">
                            <input type="checkbox" checked={services.data.show_professionals} disabled={disabled} onChange={(e) => services.setData('show_professionals', e.target.checked)} className="size-4 accent-vs-blue" />
                            Mostrar profissionais no site
                        </label>
                        {services.data.show_professionals && (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {options.professionals.map((person) => (
                                    <label key={person.id} className="flex items-center gap-3 rounded-md border border-vs-border/70 px-3 py-2 text-sm text-vs-text">
                                        <input type="checkbox" checked={services.data.professional_ids.includes(person.id)} disabled={disabled} onChange={() => services.setData('professional_ids', toggle(services.data.professional_ids, person.id))} className="size-4 accent-vs-blue" />
                                        {person.name}
                                    </label>
                                ))}
                            </div>
                        )}
                        {canEdit && <div><Button type="button" disabled={services.processing} onClick={() => services.patch(websiteDraft.url(), { preserveScroll: true })} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar serviços</Button></div>}
                    </Card>
                )}

                {tab === 'domains' && (
                    <div className="grid gap-6">
                        <Card className="grid gap-4 border-vs-border/70 p-6 shadow-sm">
                            <h2 className="text-base font-semibold text-vs-text">Domínios</h2>
                            {domains.length === 0 && <p className="text-sm text-vs-muted">Nenhum endereço criado ainda.</p>}
                            {domains.map((item) => (
                                <div key={item.id} className="grid gap-2 rounded-md border border-vs-border/70 p-4 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium text-vs-text">{item.hostname}{item.is_primary && <span className="ml-2 text-xs text-vs-blue">(principal)</span>}</span>
                                        <span className="text-vs-muted">{item.kind === 'subdomain' ? 'Endereço da plataforma' : 'Domínio próprio'} · {item.status === 'verified' ? 'Verificado' : 'Aguardando verificação'}</span>
                                    </div>
                                    {item.dns && (
                                        <div className="text-vs-muted">
                                            Publique um registro DNS <strong>{item.dns.type}</strong> em <code>{item.dns.name}</code> com o valor <code>{item.dns.value}</code>, depois verifique.
                                        </div>
                                    )}
                                    {canEdit && (
                                        <div className="flex flex-wrap gap-3">
                                            {item.status !== 'verified' && <button type="button" className="font-medium text-vs-blue hover:underline" onClick={() => router.post(verify.url({ domain: item.id }), {}, { preserveScroll: true })}>Verificar</button>}
                                            {item.status === 'verified' && !item.is_primary && <button type="button" className="font-medium text-vs-blue hover:underline" onClick={() => router.post(primary.url({ domain: item.id }), {}, { preserveScroll: true })}>Tornar principal</button>}
                                            <ConfirmAction
                                                trigger={<button type="button" className="font-medium text-red-600 hover:underline">Remover</button>}
                                                title="Remover este endereço?"
                                                description="Visitantes deixarão de acessar o site por este endereço."
                                                confirmLabel="Remover"
                                                destructive
                                                onConfirm={() => router.delete(domainDestroy.url({ domain: item.id }), { preserveScroll: true })}
                                            />
                                        </div>
                                    )}
                                </div>
                            ))}
                        </Card>
                        {canEdit && (
                            <Card className="grid gap-4 border-vs-border/70 p-6 shadow-sm">
                                <h3 className="text-sm font-semibold text-vs-text">Adicionar endereço</h3>
                                <form onSubmit={(e) => { e.preventDefault(); domain.post(domainStore.url(), { preserveScroll: true }); }} className="grid gap-4 md:grid-cols-[180px_1fr_auto] md:items-end">
                                    <Field id="kind" label="Tipo" error={domain.errors.kind}>
                                        <select id="kind" value={domain.data.kind} onChange={(e) => domain.setData('kind', e.target.value)} className={selectClass}>
                                            <option value="subdomain">Endereço da plataforma</option>
                                            <option value="custom">Domínio próprio</option>
                                        </select>
                                    </Field>
                                    {domain.data.kind === 'custom' && (
                                        <Field id="hostname" label="Domínio" error={domain.errors.hostname}>
                                            <Input id="hostname" value={domain.data.hostname} onChange={(e) => domain.setData('hostname', e.target.value)} placeholder="www.suaempresa.com.br" className="h-10" />
                                        </Field>
                                    )}
                                    <Button type="submit" disabled={domain.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Adicionar</Button>
                                </form>
                            </Card>
                        )}
                    </div>
                )}

                {tab === 'seo' && (
                    <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">SEO</h2>
                        <form onSubmit={(e) => { e.preventDefault(); seo.patch(websiteDraft.url(), { preserveScroll: true }); }} className="grid gap-5">
                            <Field id="seo-title" label="Título da página" error={seo.errors['seo.title']}>
                                <Input id="seo-title" value={seo.data.seo.title} disabled={disabled} onChange={(e) => seo.setData('seo', { ...seo.data.seo, title: e.target.value })} className="h-10" />
                            </Field>
                            <Field id="seo-description" label="Descrição" error={seo.errors['seo.description']} hint="Até 160 caracteres.">
                                <textarea id="seo-description" rows={2} value={seo.data.seo.description} disabled={disabled} onChange={(e) => seo.setData('seo', { ...seo.data.seo, description: e.target.value })} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                            </Field>
                            <label className="flex items-center gap-3 text-sm text-vs-text">
                                <input type="checkbox" checked={seo.data.seo.indexable} disabled={disabled} onChange={(e) => seo.setData('seo', { ...seo.data.seo, indexable: e.target.checked })} className="size-4 accent-vs-blue" />
                                Permitir que buscadores indexem o site
                            </label>
                            <p className="text-xs text-vs-muted">Não garantimos posição em buscadores. Prévias nunca são indexadas.</p>
                            {canEdit && <div><Button type="submit" disabled={seo.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar SEO</Button></div>}
                        </form>
                    </Card>
                )}

                {tab === 'publish' && (
                    <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Publicação</h2>
                        <p className="text-sm text-vs-muted">Salve as alterações e veja a prévia antes de publicar. Publicar cria uma nova versão; retirar do ar mantém os dados e o histórico.</p>
                        <div className="flex flex-wrap gap-3">
                            <Link href="/meu-site/previa" className="inline-flex h-10 items-center rounded-md border border-vs-border px-4 text-sm font-medium text-vs-text hover:bg-muted">Visualizar prévia</Link>
                            {canEdit && (
                                <Button type="button" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover" onClick={() => router.post(publish.url(), {}, { preserveScroll: true })}>Publicar</Button>
                            )}
                            {canEdit && website.is_live && (
                                <ConfirmAction
                                    trigger={<Button type="button" variant="outline" className="h-10">Retirar do ar</Button>}
                                    title="Retirar o site do ar?"
                                    description="Visitantes deixarão de ver o site. Os dados e o histórico de versões são mantidos."
                                    confirmLabel="Retirar do ar"
                                    destructive
                                    onConfirm={() => router.post(unpublish.url(), {}, { preserveScroll: true })}
                                />
                            )}
                        </div>
                    </Card>
                )}
            </div>
        </>
    );
}
