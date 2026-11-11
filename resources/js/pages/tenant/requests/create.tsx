import { Head, Link, useForm } from '@inertiajs/react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { index as requestsIndex, store } from '@/routes/tenant/requests';

type Props = {
    customers: { id: number; name: string }[];
};

export default function RequestCreate({ customers }: Props) {
    const form = useForm({ customer_id: '', description: '', priority: 'normal' });

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
                    <Field id="customer_id" label="Cliente" error={form.errors.customer_id}>
                        <select id="customer_id" value={form.data.customer_id} onChange={(event) => form.setData('customer_id', event.target.value)} className={selectClass} required>
                            <option value="">Selecione um cliente</option>
                            {customers.map((customer) => (
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
                            className="w-full rounded-md border border-vs-border bg-white px-3 py-2 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none"
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

                {customers.length === 0 && (
                    <p className="text-sm text-vs-muted">Cadastre um cliente antes de registrar uma solicitação.</p>
                )}
            </div>
        </>
    );
}
