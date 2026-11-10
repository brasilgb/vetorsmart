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
import { create, deactivate, edit, index as servicesIndex } from '@/routes/tenant/services';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    name: string;
    category: string | null;
    duration_minutes: number | null;
    price: string | null;
    price_type: string;
    active: boolean;
};

type Props = {
    filters: { q?: string; category_id?: string; active?: string; sort?: string; direction?: string };
    categories: { id: number; name: string }[];
    services: Paginator<Row>;
};

const priceLabel: Record<string, string> = { fixed: 'Valor fixo', starting_at: 'A partir de', on_request: 'Sob consulta' };

export default function ServicesIndex({ filters, categories, services }: Props) {
    const [term, setTerm] = useState(filters.q ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '');
    const [active, setActive] = useState(filters.active ?? '');
    const [sort, setSort] = useState(filters.sort ?? 'sort_order');

    const search = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(servicesIndex.url(), { q: term, category_id: categoryId, active, sort }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Serviços" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Serviços"
                    description="Nome e categoria bastam para começar. Duração e preço são opcionais."
                    action={<Button asChild className="bg-vs-blue text-white hover:bg-vs-blue-hover"><Link href={create.url()}>Novo serviço</Link></Button>}
                />

                <form onSubmit={search} className="grid gap-3 md:grid-cols-[1fr_200px_160px_180px_auto]">
                    <Input value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Buscar pelo nome" aria-label="Buscar serviço" className="h-10" />
                    <select value={categoryId} onChange={(e) => setCategoryId(e.target.value)} aria-label="Categoria" className={selectClass}>
                        <option value="">Todas as categorias</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>{category.name}</option>
                        ))}
                    </select>
                    <select value={active} onChange={(e) => setActive(e.target.value)} aria-label="Situação" className={selectClass}>
                        <option value="">Todos</option>
                        <option value="1">Ativos</option>
                        <option value="0">Inativos</option>
                    </select>
                    <select value={sort} onChange={(e) => setSort(e.target.value)} aria-label="Ordenar por" className={selectClass}>
                        <option value="sort_order">Ordem da lista</option>
                        <option value="name">Nome</option>
                        <option value="price">Preço</option>
                    </select>
                    <Button type="submit" variant="outline" className="h-10">Filtrar</Button>
                </form>

                {services.data.length === 0 ? (
                    <EmptyState>Nenhum serviço encontrado. Cadastre o primeiro serviço para começar.</EmptyState>
                ) : (
                    <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-left text-sm">
                                <thead className="bg-vs-surface text-vs-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Serviço</th>
                                        <th className="px-4 py-3 font-medium">Categoria</th>
                                        <th className="px-4 py-3 font-medium">Duração</th>
                                        <th className="px-4 py-3 font-medium">Preço</th>
                                        <th className="px-4 py-3 font-medium">Situação</th>
                                        <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {services.data.map((service) => (
                                        <tr key={service.id} className="border-t border-vs-border/70">
                                            <td className="px-4 py-3 font-medium text-vs-text">{service.name}</td>
                                            <td className="px-4 py-3 text-vs-muted">{service.category ?? '—'}</td>
                                            <td className="px-4 py-3 text-vs-muted">{service.duration_minutes ? `${service.duration_minutes} min` : '—'}</td>
                                            <td className="px-4 py-3 text-vs-muted">
                                                {service.price_type === 'on_request' || service.price === null
                                                    ? priceLabel[service.price_type] ?? '—'
                                                    : `${priceLabel[service.price_type]} R$ ${service.price}`}
                                            </td>
                                            <td className="px-4 py-3"><StatusBadge active={service.active} /></td>
                                            <td className="space-x-3 px-4 py-3 text-right whitespace-nowrap">
                                                <Link href={edit.url({ service: service.id })} className="font-medium text-vs-blue hover:underline">Editar</Link>
                                                {service.active && (
                                                    <ConfirmAction
                                                        trigger={<button type="button" className="font-medium text-red-600 hover:underline">Desativar</button>}
                                                        title="Desativar serviço?"
                                                        description="Ele deixa de aceitar novos agendamentos. O histórico é mantido."
                                                        confirmLabel="Desativar"
                                                        destructive
                                                        onConfirm={() => router.delete(deactivate.url({ service: service.id }), { preserveScroll: true })}
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

                <Pagination links={services.links} />
            </div>
        </>
    );
}
