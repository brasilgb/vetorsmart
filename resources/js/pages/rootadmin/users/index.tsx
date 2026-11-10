import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as usersIndex } from '@/routes/rootadmin/users';
import { show as tenantShow } from '@/routes/rootadmin/tenants';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    name: string | null;
    email: string | null;
    user_status: string | null;
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

/** Read-only: listing users and their profiles does not change any permission. */
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
                    <p className="text-sm text-vs-muted">Usuários vinculados às empresas. Senhas e dados de acesso não são exibidos.</p>
                </div>

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por nome ou e-mail" aria-label="Buscar usuário" className="h-10" />
                    <select value={role} onChange={(event) => setRole(event.target.value)} aria-label="Perfil" className="h-10 rounded-md border border-vs-border bg-white px-3 text-sm">
                        <option value="">Todos os perfis</option>
                        <option value="owner">Proprietário</option>
                        <option value="admin">Administrador</option>
                        <option value="operator">Operador</option>
                    </select>
                    <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Vínculo" className="h-10 rounded-md border border-vs-border bg-white px-3 text-sm">
                        <option value="">Todos os vínculos</option>
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                    <Button type="submit" className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Filtrar</Button>
                </form>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">E-mail</th>
                                    <th className="px-4 py-3 font-medium">Empresa</th>
                                    <th className="px-4 py-3 font-medium">Perfil</th>
                                    <th className="px-4 py-3 font-medium">Vínculo</th>
                                    <th className="px-4 py-3 font-medium">Conta</th>
                                </tr>
                            </thead>
                            <tbody>
                                {memberships.data.length === 0 && (
                                    <tr><td colSpan={6} className="px-4 py-6 text-center text-vs-muted">Nenhum usuário encontrado.</td></tr>
                                )}
                                {memberships.data.map((row) => (
                                    <tr key={row.id} className="border-t border-vs-border/70">
                                        <td className="px-4 py-3 text-vs-text">{row.name}</td>
                                        <td className="px-4 py-3 text-vs-muted">{row.email}</td>
                                        <td className="px-4 py-3">
                                            <Link href={tenantShow.url({ tenant: row.tenant_id })} className="text-vs-blue hover:underline">{row.tenant ?? '—'}</Link>
                                        </td>
                                        <td className="px-4 py-3">{roleLabel[row.role] ?? row.role}</td>
                                        <td className="px-4 py-3">{row.membership_status === 'active' ? 'Ativo' : 'Inativo'}</td>
                                        <td className="px-4 py-3">{row.user_status === 'active' ? 'Ativa' : row.user_status === 'suspended' ? 'Suspensa' : '—'}</td>
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
