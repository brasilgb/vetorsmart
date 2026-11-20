import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { Field } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { StatusBadge } from '@/components/vetor-smart/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SiteQuestions, type QuestionsProps, type SiteDraft } from '@/components/vetor-smart/website/site-questions';
import { portal as portalUpdate, preview, publish, unpublish } from '@/routes/tenant/website';
import { store as domainStore, verify, primary, destroy as domainDestroy } from '@/routes/tenant/website/domains';
import { update as subdomainUpdate } from '@/routes/tenant/website/subdomain';

type Domain = {
    id: number;
    hostname: string;
    kind: string;
    status: string;
    is_primary: boolean;
    verified_at: string | null;
    dns: { type: string; name: string; value: string } | null;
};

type Subdomain = {
    hostname: string;
    label: string;
    url: string;
    base_domain: string;
    serving: boolean;
    previous: string[];
    changes_left: number;
};

type Props = {
    website: {
        name: string;
        status: string;
        is_live: boolean;
        visible: boolean;
        published_at: string | null;
        updated_at: string | null;
        public_url: string | null;
        custom_url: string | null;
        subdomain: Subdomain | null;
        canonical_url: string | null;
        address_issue: string | null;
        version: number | null;
    };
    draft: SiteDraft;
    options: QuestionsProps['options'] & { colors: { value: string; label: string }[] };
    business: QuestionsProps['business'];
    location: QuestionsProps['location'];
    seoSuggestion: QuestionsProps['seoSuggestion'];
    domains: Domain[];
    canEdit: boolean;
    segment: string | null;
    serviceRegion: string | null;
    portal: { listed: boolean; issues: string[]; url: string };
};

const tabs = [
    { key: 'questions', label: 'Montar o site' },
    { key: 'overview', label: 'Visão geral' },
    { key: 'publish', label: 'Publicação' },
    { key: 'domains', label: 'Domínios' },
    { key: 'portal', label: 'Portal Regional' },
];

/**
 * Meu Site. The site is built by answering plain questions (SiteQuestions); each answer saves only its own fields to
 * the draft. Visitors see the site only after an explicit publication; the server enforces every permission.
 */
export default function WebsiteIndex({ website, draft, options, business, location, seoSuggestion, domains, canEdit, segment, portal }: Props) {
    const [tab, setTab] = useState('questions');

    const domain = useForm({ hostname: '' });
    const subdomain = useForm({ subdomain: website.subdomain?.label ?? '' });

    return (
        <>
            <Head title="Meu Site" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Meu Site"
                    description="Seu site profissional. Alterações ficam em rascunho até a publicação."
                    action={website.visible && website.canonical_url ? <a href={website.canonical_url} target="_blank" rel="noopener noreferrer" className="text-sm font-medium text-vs-link hover:underline">Abrir site publicado</a> : null}
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
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-vs-muted">Situação:</span>
                            <StatusBadge active={website.visible} activeLabel="No ar" inactiveLabel={website.is_live ? 'Publicado, fora do ar' : 'Não publicado'} />
                        </div>
                        {website.subdomain?.serving && <PublicAddress label="Endereço VetorSmart" url={website.subdomain.url} />}
                        <PublicAddress label="Endereço padrão" url={website.public_url} />
                        {website.custom_url && <PublicAddress label="Domínio próprio" url={website.custom_url} />}
                        {(website.custom_url || website.subdomain?.serving) && website.canonical_url && (
                            <p className="text-xs text-vs-muted">
                                Todos os endereços exibem o mesmo site. Para os buscadores, o endereço principal é <span className="break-all">{website.canonical_url}</span>.
                            </p>
                        )}
                        <SiteNotice website={website} />
                        <div><span className="text-vs-muted">Última publicação:</span> {website.published_at ?? '—'}{website.version ? ` (versão ${website.version})` : ''}</div>
                        <div><span className="text-vs-muted">Último rascunho:</span> {website.updated_at ?? '—'}</div>
                        <div><a href={preview.url()} target="_blank" rel="noopener noreferrer" className="font-medium text-vs-link hover:underline">Ver prévia do rascunho</a></div>
                    </Card>
                )}

                {tab === 'questions' && (
                    <SiteQuestions
                        draft={draft}
                        options={options}
                        business={business}
                        location={location}
                        seoSuggestion={seoSuggestion}
                        siteUrl={website.canonical_url ?? website.public_url}
                        segment={segment}
                        canEdit={canEdit}
                    />
                )}

                {tab === 'domains' && (
                    <div className="grid gap-6">
                        {website.subdomain && (
                            <Card className="grid gap-4 border-vs-border/70 p-6 shadow-sm">
                                <h2 className="text-base font-semibold text-vs-text">Endereço VetorSmart</h2>
                                <p className="text-sm text-vs-muted">
                                    {website.subdomain.serving
                                        ? 'Seu site também responde neste endereço, sem configuração de DNS.'
                                        : 'Este endereço está reservado para a sua empresa. Ele será ativado em breve; até lá, leva os visitantes ao endereço padrão.'}
                                </p>
                                <code className="w-fit max-w-full rounded bg-muted px-2 py-1 text-sm break-all text-vs-text">{website.subdomain.hostname}</code>
                                {website.subdomain.previous.length > 0 && (
                                    <p className="text-xs text-vs-muted">
                                        Endereços anteriores, que redirecionam para o atual: <span className="break-words">{website.subdomain.previous.join(', ')}</span>
                                    </p>
                                )}
                                {canEdit && website.subdomain.changes_left > 0 && (
                                    <form onSubmit={(e) => { e.preventDefault(); subdomain.patch(subdomainUpdate.url(), { preserveScroll: true }); }} className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                                        <Field id="subdomain" label="Novo endereço" error={subdomain.errors.subdomain} hint={`Letras minúsculas, números e hífen. Restam ${website.subdomain.changes_left} alteração(ões); o endereço anterior continua redirecionando.`}>
                                            <div className="flex items-center gap-2">
                                                <Input id="subdomain" value={subdomain.data.subdomain} onChange={(e) => subdomain.setData('subdomain', e.target.value.toLowerCase())} autoCapitalize="none" autoCorrect="off" spellCheck={false} className="h-10" />
                                                <span className="shrink-0 text-sm text-vs-muted">.{website.subdomain.base_domain}</span>
                                            </div>
                                        </Field>
                                        <Button type="submit" disabled={subdomain.processing || subdomain.data.subdomain === website.subdomain.label} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Alterar</Button>
                                    </form>
                                )}
                            </Card>
                        )}
                        <Card className="grid gap-4 border-vs-border/70 p-6 shadow-sm">
                            <h2 className="text-base font-semibold text-vs-text">Domínio próprio (opcional)</h2>
                            <p className="text-sm text-vs-muted">Seu site já tem um endereço padrão no VetorSmart, sem domínio nem configuração de DNS. Um domínio próprio verificado também exibe o site e passa a ser o endereço principal para os buscadores; o endereço padrão continua funcionando.</p>
                            {domains.length === 0 && <p className="text-sm text-vs-muted">Nenhum domínio próprio cadastrado.</p>}
                            {domains.map((item) => (
                                <div key={item.id} className="grid gap-2 rounded-md border border-vs-border/70 p-4 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium text-vs-text">{item.hostname}{item.is_primary && <span className="ml-2 text-xs text-vs-link">(principal)</span>}</span>
                                        <span className="text-vs-muted">Domínio próprio · {item.status === 'verified' ? 'Verificado' : 'Aguardando verificação'}</span>
                                    </div>
                                    {item.dns && (
                                        <div className="text-vs-muted">
                                            Publique um registro DNS <strong>{item.dns.type}</strong> em <code>{item.dns.name}</code> com o valor <code>{item.dns.value}</code>, depois verifique.
                                        </div>
                                    )}
                                    {canEdit && (
                                        <div className="flex flex-wrap gap-3">
                                            {item.status !== 'verified' && <button type="button" className="font-medium text-vs-link hover:underline" onClick={() => router.post(verify.url({ domain: item.id }), {}, { preserveScroll: true })}>Verificar</button>}
                                            {item.status === 'verified' && !item.is_primary && <button type="button" className="font-medium text-vs-link hover:underline" onClick={() => router.post(primary.url({ domain: item.id }), {}, { preserveScroll: true })}>Tornar principal</button>}
                                            <ConfirmAction
                                                trigger={<button type="button" className="font-medium text-vs-danger hover:underline">Remover</button>}
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
                                <h3 className="text-sm font-semibold text-vs-text">Adicionar domínio próprio</h3>
                                <form onSubmit={(e) => { e.preventDefault(); domain.post(domainStore.url(), { preserveScroll: true }); }} className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                                    <Field id="hostname" label="Domínio" error={domain.errors.hostname}>
                                        <Input id="hostname" value={domain.data.hostname} onChange={(e) => domain.setData('hostname', e.target.value)} placeholder="www.suaempresa.com.br" className="h-10" />
                                    </Field>
                                    <Button type="submit" disabled={domain.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Adicionar</Button>
                                </form>
                            </Card>
                        )}
                    </div>
                )}

                {tab === 'portal' && <PortalSection portal={portal} canEdit={canEdit} />}

                {tab === 'publish' && (
                    <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Publicação</h2>
                        <p className="text-sm text-vs-muted">Salve as alterações e veja a prévia antes de publicar. Publicar cria uma nova versão; retirar do ar mantém os dados e o histórico.</p>
                        <div className="flex flex-wrap gap-3">
                            <a href={preview.url()} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center rounded-md border border-vs-border px-4 text-sm font-medium text-vs-text hover:bg-muted">Prévia no computador</a>
                            <a href={preview.url({ query: { dispositivo: 'celular' } })} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center rounded-md border border-vs-border px-4 text-sm font-medium text-vs-text hover:bg-muted">Prévia no celular</a>
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

/**
 * A public address with a button that copies it. The standard address exists as soon as the company has a segment.
 */
function PublicAddress({ label, url }: { label: string; url: string | null }) {
    const [copied, setCopied] = useState(false);

    if (!url) {
        return (
            <div>
                <span className="text-vs-muted">{label}:</span> ainda não disponível
            </div>
        );
    }

    const copy = () => {
        navigator.clipboard
            ?.writeText(url)
            .then(() => {
                setCopied(true);
                window.setTimeout(() => setCopied(false), 2000);
            })
            .catch(() => setCopied(false));
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-vs-muted">{label}:</span>
            <code className="max-w-full rounded bg-muted px-2 py-1 text-xs break-all text-vs-text">{url}</code>
            <Button type="button" variant="outline" size="sm" onClick={copy}>
                {copied ? 'Link copiado' : 'Copiar link'}
            </Button>
            <span className="sr-only" aria-live="polite">
                {copied ? 'Link copiado para a área de transferência.' : ''}
            </span>
        </div>
    );
}

/**
 * Says, in plain words, why visitors cannot see the site yet.
 */
function SiteNotice({ website }: { website: Props['website'] }) {
    if (website.visible) {
        return null;
    }

    const message = website.address_issue ?? (website.is_live ? null : 'O site ainda não foi publicado. Visitantes que abrirem o endereço verão uma página não encontrada até a publicação, na aba Publicação.');

    if (!message) {
        return null;
    }

    return (
        <p role="status" className="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            {message}
        </p>
    );
}

/**
 * Consent to appear in the Regional Portal. Off by default; it takes effect at once and the portal shows only what the
 * site already published, never phones, e-mails or the draft. The issues explain why the company does not appear yet.
 */
function PortalSection({ portal, canEdit }: { portal: Props['portal']; canEdit: boolean }) {
    const [processing, setProcessing] = useState(false);
    const visible = portal.listed && portal.issues.length === 0;

    const change = (listed: boolean) => {
        router.patch(portalUpdate.url(), { listed }, { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
    };

    return (
        <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
            <h2 className="text-base font-semibold text-vs-text">Portal Regional</h2>
            <p className="text-sm text-vs-muted">
                O Portal Regional ajuda clientes da sua região a encontrar sua empresa pelo serviço e pela cidade. Quem encontrar sua empresa abre o seu site e envia a solicitação diretamente para você, com a origem "Portal Regional". Não há custo nem posições pagas.
            </p>
            <div className="grid gap-1 text-sm text-vs-text">
                <p className="font-medium">O que aparece no portal (apenas o que já está publicado no seu site):</p>
                <ul className="list-disc pl-5 text-vs-muted">
                    <li>Nome comercial, logotipo, segmento, cidade e UF</li>
                    <li>Descrição resumida, região atendida e serviços publicados</li>
                    <li>Links para o seu site e para o formulário de solicitação</li>
                </ul>
                <p className="text-vs-muted">Telefones, e-mails, rascunhos e dados internos nunca aparecem no portal.</p>
            </div>
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <span className="text-vs-muted">Situação:</span>
                <StatusBadge active={visible} activeLabel="Aparece no portal" inactiveLabel={portal.listed ? 'Participação ativa, ainda não aparece' : 'Não participa'} />
            </div>
            {portal.listed && portal.issues.length > 0 && (
                <div role="status" className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                    <p className="font-medium">Para aparecer no portal:</p>
                    <ul className="list-disc pl-5">
                        {portal.issues.map((issue) => <li key={issue}>{issue}</li>)}
                    </ul>
                </div>
            )}
            {canEdit && (
                <div className="flex flex-wrap gap-3">
                    {portal.listed ? (
                        <ConfirmAction
                            trigger={<Button type="button" variant="outline" className="h-10" disabled={processing}>Sair do portal</Button>}
                            title="Sair do Portal Regional?"
                            description="Sua empresa deixa de aparecer no portal imediatamente. O seu site continua no ar."
                            confirmLabel="Sair do portal"
                            onConfirm={() => change(false)}
                        />
                    ) : (
                        <Button type="button" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover" disabled={processing} onClick={() => change(true)}>Participar do portal</Button>
                    )}
                    <a href={portal.url} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 items-center text-sm font-medium text-vs-link hover:underline">Abrir o Portal Regional</a>
                </div>
            )}
        </Card>
    );
}
