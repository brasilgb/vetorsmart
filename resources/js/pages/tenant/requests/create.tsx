import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as requestsIndex, store } from '@/routes/tenant/requests';

type CustomerOption = { id: number; name: string };

type Props = {
    filters: { q: string };
    customers: CustomerOption[];
    hasMoreCustomers: boolean;
    hasCustomers: boolean;
};

export default function RequestCreate({ filters, customers, hasMoreCustomers, hasCustomers }: Props) {
    const form = useForm({ customer_id: '', description: '', priority: 'normal' });
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<CustomerOption | null>(null);
    const firstRender = useRef(true);

    // The list is searched on the server; only the customer props are reloaded, after a short pause in typing.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = window.setTimeout(() => {
            router.reload({ only: ['customers', 'hasMoreCustomers', 'filters'], data: { q: search } });
        }, 300);

        return () => window.clearTimeout(timer);
    }, [search]);

    // The chosen customer stays selectable even when a new search no longer lists it.
    const options = selected !== null && !customers.some((customer) => customer.id === selected.id) ? [selected, ...customers] : customers;

    return (
        <>
            <Head title="Nova solicitação" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Nova solicitação" description="Registre o pedido do cliente. O serviço e a data são definidos na triagem e no agendamento." />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url());
                    }}
                    className="grid max-w-2xl gap-5"
                >
                    <Field
                        id="customer_search"
                        label="Buscar cliente"
                        hint={hasMoreCustomers ? 'Mostrando os 50 primeiros. Digite o nome, o e-mail ou o telefone para encontrar outro cliente.' : 'Nome, e-mail ou telefone.'}
                    >
                        <Input
                            id="customer_search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Ex.: Maria ou 11 98888"
                            autoComplete="off"
                            className="h-10"
                        />
                    </Field>

                    <Field id="customer_id" label="Cliente" error={form.errors.customer_id}>
                        <select
                            id="customer_id"
                            value={form.data.customer_id}
                            onChange={(event) => {
                                form.setData('customer_id', event.target.value);
                                setSelected(options.find((customer) => String(customer.id) === event.target.value) ?? null);
                            }}
                            className={selectClass}
                            required
                        >
                            <option value="">{options.length === 0 && search !== '' ? 'Nenhum cliente encontrado' : 'Selecione um cliente'}</option>
                            {options.map((customer) => (
                                <option key={customer.id} value={customer.id}>{customer.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field id="description" label="Descrição do pedido" error={form.errors.description}>
                        <textarea
                            id="description"
                            rows={4}
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            className="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none"
                            required
                        />
                    </Field>

                    <Field id="priority" label="Prioridade" error={form.errors.priority}>
                        <select id="priority" value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)} className={selectClass}>
                            <option value="low">Baixa</option>
                            <option value="normal">Normal</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </Field>

                    <Card className="flex flex-wrap items-center gap-3 border-vs-border/70 p-4 shadow-sm">
                        <Button type="submit" disabled={form.processing} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Registrar solicitação</Button>
                        <Link href={requestsIndex.url()} className="text-sm font-medium text-vs-muted hover:underline">Cancelar</Link>
                    </Card>
                </form>

                {!hasCustomers && (
                    <p className="text-sm text-vs-muted">Cadastre um cliente antes de registrar uma solicitação.</p>
                )}
            </div>
        </>
    );
}
