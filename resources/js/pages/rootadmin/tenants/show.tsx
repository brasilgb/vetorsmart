import { Head, useForm } from '@inertiajs/react';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { status as tenantStatus } from '@/routes/rootadmin/tenants';
import type { Paginator } from '@/types';

type Props = {
    tenant: {
        id: number;
        name: string;
        slug: string;
        segment: string | null;
        owner: { name: string; email: string } | null;
        status: string;
        created_at: string | null;
        document: string | null;
        email: string | null;
        phone: string | null;
        timezone: string;
    };
    settings: {
        business_name: string;
        description: string | null;
        locale: string;
        service_modes: string[];
        confirmation_policy: string;
    } | null;
    operations: {
        active_users: number;
        primary_domain: { hostname: string; kind: string; status: string } | null;
        website: { name: string; status: string } | null;
        assistant_enabled: boolean;
    };
    history: { id: number; action: string; user: string | null; created_at: string | null }[];
    users: Paginator<{
        id: number;
        name: string | null;
        email: string | null;
        role: string;
        membership_status: string;
        user_status: string | null;
    }>;
};

const roleLabel: Record<string, string> = { owner: 'Proprietário', admin: 'Administrador', operator: 'Operador' };

const actionLabel: Record<string, string> = {
    'tenant.suspended': 'Empresa suspensa',
    'tenant.activated': 'Empresa ativada',
};

export default function TenantShow({ tenant, settings, operations, history, users }: Props) {
    const isActive = tenant.status === 'active';

    const form = useForm({
        status: isActive ? 'suspended' : 'active',
        confirm: false as boolean,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(tenantStatus.url({ tenant: tenant.id }), { preserveScroll: true });
    };

    return (
        <>
            <Head title={tenant.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-vs-text">{tenant.name}</h1>
                        <p className="text-sm text-vs-muted">
                            {tenant.segment ?? 'Sem segmento'} · cadastrada em {tenant.created_at ?? '—'}
                        </p>
                    </div>
                    <Badge variant={isActive ? 'default' : 'secondary'}>{isActive ? 'Ativa' : 'Suspensa'}</Badge>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Dados administrativos</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <Info label="Identificador (slug)" value={tenant.slug} />
                            <Info label="Responsável" value={tenant.owner ? `${tenant.owner.name} · ${tenant.owner.email}` : '—'} />
                            <Info label="Documento" value={tenant.document ?? 'Não informado'} />
                            <Info label="E-mail" value={tenant.email ?? 'Não informado'} />
                            <Info label="Telefone" value={tenant.phone ?? 'Não informado'} />
                            <Info label="Fuso horário" value={tenant.timezone} />
                        </CardContent>
                    </Card>

                    <Card className="border-vs-border/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-vs-text">Configurações (somente leitura)</CardTitle>
                            <CardDescription>Dados de atendimento que a empresa definiu. Não podem ser alterados pela administração.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            {settings === null ? (
                                <p className="text-vs-muted">A empresa ainda não configurou o atendimento.</p>
                            ) : (
                                <>
                                    <Info label="Nome público" value={settings.business_name} />
                                    <Info label="Descrição" value={settings.description ?? 'Não informada'} />
                                    <Info label="Idioma" value={settings.locale} />
                                    <Info label="Modos de atendimento" value={settings.service_modes.join(', ') || 'Não definidos'} />
                                    <Info label="Confirmação de agendamento" value={settings.confirmation_policy} />
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Operação</CardTitle>
                        <CardDescription>Situação atual. Os dados de atendimento da empresa não são exibidos nesta área.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm">
                        <Info label="Usuários ativos" value={String(operations.active_users)} />
                        <Info label="Limite contratado" value="Não definido (plano ainda não cadastrado)" />
                        <Info
                            label="Domínio principal"
                            value={
                                operations.primary_domain
                                    ? `${operations.primary_domain.hostname} · ${operations.primary_domain.status === 'verified' ? 'verificado' : 'pendente'}`
                                    : 'Não configurado'
                            }
                        />
                        <Info
                            label="Site"
                            value={operations.website ? `${operations.website.name} · ${operations.website.status === 'published' ? 'publicado' : 'não publicado'}` : 'Não criado'}
                        />
                        <Info label="Atendimento IA" value={operations.assistant_enabled ? 'Ativado pela empresa' : 'Desativado'} />
                    </CardContent>
                </Card>

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">{isActive ? 'Suspender empresa' : 'Ativar empresa'}</CardTitle>
                        <CardDescription>
                            {isActive
                                ? 'Ao suspender, nenhum usuário da empresa acessa os dados até a reativação. A ação fica registrada na auditoria.'
                                : 'Ao ativar, os usuários da empresa voltam a acessar a área da empresa. A ação fica registrada na auditoria.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-wrap items-center gap-4">
                            {isActive && (
                                <div className="flex items-center gap-2">
                                    <Checkbox id="confirm" checked={form.data.confirm} onCheckedChange={(checked) => form.setData('confirm', checked === true)} />
                                    <Label htmlFor="confirm" className="text-vs-text">Confirmo a suspensão desta empresa</Label>
                                </div>
                            )}
                            <Button
                                type="submit"
                                disabled={form.processing || (isActive && !form.data.confirm)}
                                variant={isActive ? 'destructive' : 'default'}
                                className={isActive ? '' : 'bg-vs-blue text-white hover:bg-vs-blue-hover'}
                            >
                                {isActive ? 'Suspender empresa' : 'Ativar empresa'}
                            </Button>
                            {form.errors.confirm && <p className="text-sm text-red-600">{form.errors.confirm}</p>}
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Usuários vinculados</CardTitle>
                        <CardDescription>Sem senhas ou dados de acesso. Alterações de permissão não são feitas por esta tela.</CardDescription>
                    </CardHeader>
                    <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Usuários vinculados à empresa">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">E-mail</th>
                                    <th className="px-4 py-3 font-medium">Perfil</th>
                                    <th className="px-4 py-3 font-medium">Vínculo</th>
                                    <th className="px-4 py-3 font-medium">Conta</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((row) => (
                                    <tr key={row.id} className="border-t border-vs-border/70">
                                        <td className="px-4 py-3 text-vs-text">{row.name}</td>
                                        <td className="px-4 py-3 text-vs-muted">{row.email}</td>
                                        <td className="px-4 py-3">{roleLabel[row.role] ?? row.role}</td>
                                        <td className="px-4 py-3">{row.membership_status === 'active' ? 'Ativo' : 'Inativo'}</td>
                                        <td className="px-4 py-3">{row.user_status === 'active' ? 'Ativa' : row.user_status === 'suspended' ? 'Suspensa' : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4 py-4">
                        <Pagination links={users.links} />
                    </div>
                </Card>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Histórico administrativo</CardTitle>
                        <CardDescription>Últimas ações administrativas sobre esta empresa. Não inclui conteúdo de atendimento.</CardDescription>
                    </CardHeader>
                    {history.length === 0 ? (
                        <p className="px-6 pb-6 text-sm text-vs-muted">Nenhuma ação administrativa registrada.</p>
                    ) : (
                        <ul className="grid gap-2 px-6 pb-6 text-sm">
                            {history.map((entry) => (
                                <li key={entry.id} className="flex flex-wrap justify-between gap-2 border-t border-vs-border/70 pt-2">
                                    <span className="text-vs-text">{actionLabel[entry.action] ?? entry.action}</span>
                                    <span className="text-vs-muted">
                                        {entry.user ?? 'Sistema'} · {entry.created_at ?? '—'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-wrap justify-between gap-2 border-b border-vs-border/60 pb-2 last:border-0">
            <span className="text-vs-muted">{label}</span>
            <span className="text-right text-vs-text">{value}</span>
        </div>
    );
}
