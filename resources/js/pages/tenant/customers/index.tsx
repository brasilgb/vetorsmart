import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { StatusBadge } from '@/components/vetor-smart/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { selectClass } from '@/components/vetor-smart/field';
import { archive, create, edit, index as customersIndex } from '@/routes/tenant/customers';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    city: string | null;
    status: string;
};

type Props = {
    filters: { q?: string; status?: string };
    customers: Paginator<Row>;
};

export default function CustomersIndex({ filters, customers }: Props) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(customersIndex.url(), { q: term, status }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Clientes" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Clientes"
                    description="Cadastro rápido: só o nome é obrigatório. Os demais dados podem ser completados depois."
                    action={<Button asChild className="bg-vs-blue text-white hover:bg-vs-blue-hover"><Link href={create.url()}>Novo cliente</Link></Button>}
                />

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_200px_auto]">
                    <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Buscar por nome, e-mail ou telefone" aria-label="Buscar cliente" className="h-10" />
                    <select value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Situação" className={selectClass}>
                        <option value="">Todas as situações</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Arquivados</option>
                    </select>
                    <Button type="submit" variant="outline" className="h-10">Filtrar</Button>
                </form>

                {customers.data.length === 0 ? (
                    <EmptyState>Nenhum cliente encontrado. Use “Novo cliente” para começar.</EmptyState>
                ) : (
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Nome</th>
                                        <th className="px-4 py-3 font-medium">Telefone</th>
                                        <th className="px-4 py-3 font-medium">E-mail</th>
                                        <th className="px-4 py-3 font-medium">Cidade</th>
                                        <th className="px-4 py-3 font-medium">Situação</th>
                                        <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {customers.data.map((customer) => (
                                        <tr key={customer.id} className="border-t border-vs-border/70">
                                            <td className="px-4 py-3 font-medium text-vs-text">{customer.name}</td>
                                            <td className="px-4 py-3 text-vs-muted">{customer.phone ?? '—'}</td>
                                            <td className="px-4 py-3 text-vs-muted">{customer.email ?? '—'}</td>
                                            <td className="px-4 py-3 text-vs-muted">{customer.city ?? '—'}</td>
                                            <td className="px-4 py-3"><StatusBadge active={customer.status === 'active'} /></td>
                                            <td className="space-x-3 px-4 py-3 text-right whitespace-nowrap">
                                                <Link href={edit.url({ customer: customer.id })} className="font-medium text-vs-blue hover:underline">Editar</Link>
                                                {customer.status === 'active' && (
                                                    <ConfirmAction
                                                        trigger={<button type="button" className="font-medium text-red-600 hover:underline">Arquivar</button>}
                                                        title="Arquivar cliente?"
                                                        description="O cliente deixa de aparecer nas buscas padrão, mas o histórico é mantido."
                                                        confirmLabel="Arquivar"
                                                        destructive
                                                        onConfirm={() => router.delete(archive.url({ customer: customer.id }), { preserveScroll: true })}
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                <Pagination links={customers.links} />
            </div>
        </>
    );
}
