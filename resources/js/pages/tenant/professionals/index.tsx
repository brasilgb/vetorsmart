import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { StatusBadge } from '@/components/vetor-smart/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { activate, create, deactivate, edit, index as professionalsIndex } from '@/routes/tenant/professionals';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    active: boolean;
    services: number;
};

type Props = {
    filters: { q?: string; active?: string };
    professionals: Paginator<Row>;
};

export default function ProfessionalsIndex({ filters, professionals }: Props) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [active, setActive] = useState(filters.active ?? '');

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(professionalsIndex.url(), { q: term, active }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Profissionais" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Profissionais"
                    description="Quem executa os serviços. Não precisa de acesso ao sistema."
                    action={<Button asChild className="bg-vs-blue text-white hover:bg-vs-blue-hover"><Link href={create.url()}>Novo profissional</Link></Button>}
                />

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_200px_auto]">
                    <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Buscar por nome, e-mail ou telefone" aria-label="Buscar profissional" className="h-10" />
                    <select value={active} onChange={(e) => setActive(e.target.value)} aria-label="Situação" className={selectClass}>
                        <option value="">Todos</option>
                        <option value="1">Ativos</option>
                        <option value="0">Inativos</option>
                    </select>
                    <Button type="submit" variant="outline" className="h-10">Filtrar</Button>
                </form>

                {professionals.data.length === 0 ? (
                    <EmptyState>Nenhum profissional encontrado. Cadastre quem executa os serviços.</EmptyState>
                ) : (
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Nome</th>
                                        <th className="px-4 py-3 font-medium">Contato</th>
                                        <th className="px-4 py-3 font-medium">Serviços</th>
                                        <th className="px-4 py-3 font-medium">Situação</th>
                                        <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {professionals.data.map((professional) => (
                                        <tr key={professional.id} className="border-t border-vs-border/70">
                                            <td className="px-4 py-3 font-medium text-vs-text">{professional.name}</td>
                                            <td className="px-4 py-3 text-vs-muted">{professional.phone ?? professional.email ?? '—'}</td>
                                            <td className="px-4 py-3 text-vs-muted">{professional.services}</td>
                                            <td className="px-4 py-3"><StatusBadge active={professional.active} /></td>
                                            <td className="space-x-3 px-4 py-3 text-right whitespace-nowrap">
                                                <Link href={edit.url({ professional: professional.id })} className="font-medium text-vs-blue hover:underline">Editar</Link>
                                                {professional.active ? (
                                                    <ConfirmAction
                                                        trigger={<button type="button" className="font-medium text-red-600 hover:underline">Desativar</button>}
                                                        title="Desativar profissional?"
                                                        description="Ele deixa de receber novos agendamentos. Vínculos e histórico são mantidos."
                                                        confirmLabel="Desativar"
                                                        destructive
                                                        onConfirm={() => router.delete(deactivate.url({ professional: professional.id }), { preserveScroll: true })}
                                                    />
                                                ) : (
                                                    <button type="button" onClick={() => router.post(activate.url({ professional: professional.id }), {}, { preserveScroll: true })} className="font-medium text-vs-blue hover:underline">
                                                        Reativar
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                <Pagination links={professionals.links} />
            </div>
        </>
    );
}
