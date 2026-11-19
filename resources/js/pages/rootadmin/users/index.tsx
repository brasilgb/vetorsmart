import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as usersIndex, reactivate, resendVerification, suspend } from '@/routes/rootadmin/users';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    user_id: number;
    name: string | null;
    email: string | null;
    user_status: string | null;
    email_state: 'verified' | 'legacy' | 'pending' | null;
    is_self: boolean;
    tenant: string | null;
    tenant_id: number;
    role: string;
    membership_status: string;
};

type Props = {
    filters: { q?: string; tenant_id?: string; role?: string; status?: string };
    memberships: Paginator<Row>;
};

const roleLabel: Record<string, string> = { owner: 'Proprietário', admin: 'Administrador', operator: 'Operador' };

const emailState: Record<string, { label: string; tone: string }> = {
    verified: { label: 'Confirmado', tone: 'border-emerald-600 text-emerald-700 dark:text-emerald-400' },
    legacy: { label: 'Legado, sem confirmação', tone: 'border-amber-500 text-amber-700 dark:text-amber-400' },
    pending: { label: 'Aguardando confirmação', tone: 'border-vs-border text-vs-muted' },
};

function SuspendAccount({ row }: { row: Row }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    if (!open) {
        return <Button type="button" size="sm" variant="outline" className="text-vs-danger" onClick={() => setOpen(true)}>Suspender conta</Button>;
    }

    return (
        <form onSubmit={(event) => { event.preventDefault(); form.post(suspend.url({ user: row.user_id }), { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="grid w-64 gap-2">
            <label htmlFor={`reason-${row.id}`} className="text-xs font-medium text-vs-text">Motivo (registrado na auditoria). Vale para todas as empresas.</label>
            <Input id={`reason-${row.id}`} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className="h-9" />
            <InputError message={form.errors.reason} />
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={form.processing} className="bg-vs-danger text-white hover:bg-vs-danger/90">Suspender</Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>Voltar</Button>
            </div>
        </form>
    );
}

/** Users linked to companies, with the account and e-mail confirmation state, and the platform account actions. */
export default function UsersIndex({ filters, memberships }: Props) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [role, setRole] = useState(filters.role ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(usersIndex.url(), { q: term, role, status, tenant_id: filters.tenant_id ?? '' }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Usuários" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Usuários</h1>
                    <p className="text-sm text-vs-muted">
                        Usuários vinculados às empresas, com a situação da conta e da confirmação de e-mail. Senhas e dados de acesso não são exibidos;
                        perfis e vínculos continuam sendo geridos por cada empresa.
                    </p>
                </div>

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por nome ou e-mail" aria-label="Buscar usuário" className="h-10" />
                    <select value={role} onChange={(event) => setRole(event.target.value)} aria-label="Perfil" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                        <option value="">Todos os perfis</option>
                        <option value="owner">Proprietário</option>
                        <option value="admin">Administrador</option>
                        <option value="operator">Operador</option>
                    </select>
                    <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Vínculo" className="h-10 rounded-md border border-input bg-card px-3 text-sm text-vs-text">
                        <option value="">Todos os vínculos</option>
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                </form>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Tabela de usuários">
                        <table className="w-full min-w-[980px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">E-mail</th>
                                    <th className="px-4 py-3 font-medium">Empresa</th>
                                    <th className="px-4 py-3 font-medium">Perfil</th>
                                    <th className="px-4 py-3 font-medium">Vínculo</th>
                                    <th className="px-4 py-3 font-medium">Conta</th>
                                    <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                {memberships.data.length === 0 && (
                                    <tr><td colSpan={7} className="px-4 py-6 text-center text-vs-muted">Nenhum usuário encontrado.</td></tr>
                                )}
                                {memberships.data.map((row) => (
                                    <tr key={row.id} className="border-t border-vs-border/70 align-top">
                                        <td className="px-4 py-3 text-vs-text">{row.name}</td>
                                        <td className="px-4 py-3">
                                            <span className="block text-vs-muted">{row.email}</span>
                                            {row.email_state && (
                                                <Badge variant="outline" className={`mt-1 ${emailState[row.email_state].tone}`}>{emailState[row.email_state].label}</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link href={tenantShow.url({ tenant: row.tenant_id })} className="text-vs-link hover:underline">{row.tenant ?? '—'}</Link>
                                        </td>
                                        <td className="px-4 py-3 text-vs-text">{roleLabel[row.role] ?? row.role}</td>
                                        <td className="px-4 py-3 text-vs-text">{row.membership_status === 'active' ? 'Ativo' : 'Inativo'}</td>
                                        <td className="px-4 py-3 text-vs-text">{row.user_status === 'active' ? 'Ativa' : row.user_status === 'suspended' ? 'Suspensa' : '—'}</td>
                                        <td className="px-4 py-3">
                                            {!row.is_self && (
                                                <div className="flex flex-wrap justify-end gap-2">
                                                    {row.email_state !== 'verified' && row.email_state !== null && (
                                                        <Button type="button" size="sm" variant="outline" onClick={() => router.post(resendVerification.url({ user: row.user_id }), {}, { preserveScroll: true })}>
                                                            Reenviar confirmação
                                                        </Button>
                                                    )}
                                                    {row.user_status === 'suspended' ? (
                                                        <Button type="button" size="sm" variant="outline" onClick={() => router.post(reactivate.url({ user: row.user_id }), {}, { preserveScroll: true })}>
                                                            Reativar conta
                                                        </Button>
                                                    ) : (
                                                        <SuspendAccount row={row} />
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Pagination links={memberships.links} />
            </div>
        </>
    );
}
