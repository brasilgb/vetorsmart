import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { index as servicesIndex, store, update } from '@/routes/tenant/services';

type Service = {
    id: number;
    name: string;
    description: string | null;
    service_category_id: number | null;
    duration_minutes: number | null;
    price: string | null;
    price_type: string;
    requires_evaluation: boolean;
    active: boolean;
    sort_order: number;
};

type Props = {
    service: Service | null;
    categories: { id: number; name: string }[];
};

/**
 * Price rules mirror the server: a fixed or starting-at price needs an amount;
 * "on request" must have no amount.
 */
export default function ServiceForm({ service, categories }: Props) {
    const form = useForm({
        name: service?.name ?? '',
        description: service?.description ?? '',
        service_category_id: service?.service_category_id ? String(service.service_category_id) : '',
        duration_minutes: service?.duration_minutes ? String(service.duration_minutes) : '',
        price_type: service?.price_type ?? 'on_request',
        price: service?.price ?? '',
        requires_evaluation: service?.requires_evaluation ?? false,
        active: service?.active ?? true,
    });

    const needsAmount = form.data.price_type !== 'on_request';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (service) {
            form.patch(update.url({ service: service.id }));
        } else {
            form.post(store.url());
        }
    };

    return (
        <>
            <Head title={service ? 'Editar serviço' : 'Novo serviço'} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={service ? 'Editar serviço' : 'Novo serviço'} description="Informe o nome. Os demais campos são opcionais." />

                <Card className="border-vs-border/70 p-6 shadow-sm">
                    <form onSubmit={submit} className="grid gap-5 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <Field id="name" label="Nome do serviço" error={form.errors.name}>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus className="h-10" />
                            </Field>
                        </div>

                        <Field id="service_category_id" label="Categoria" error={form.errors.service_category_id}>
                            <select id="service_category_id" value={form.data.service_category_id} onChange={(e) => form.setData('service_category_id', e.target.value)} className={selectClass}>
                                <option value="">Sem categoria</option>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>{category.name}</option>
                                ))}
                            </select>
                        </Field>

                        <Field id="duration_minutes" label="Duração estimada (minutos)" error={form.errors.duration_minutes}>
                            <Input id="duration_minutes" type="number" min={1} max={1440} value={form.data.duration_minutes} onChange={(e) => form.setData('duration_minutes', e.target.value)} className="h-10" />
                        </Field>

                        <Field id="price_type" label="Tipo de preço" error={form.errors.price_type}>
                            <select id="price_type" value={form.data.price_type} onChange={(e) => { form.setData('price_type', e.target.value); if (e.target.value === 'on_request') form.setData('price', ''); }} className={selectClass}>
                                <option value="on_request">Sob consulta</option>
                                <option value="fixed">Valor fixo</option>
                                <option value="starting_at">A partir de</option>
                            </select>
                        </Field>

                        <Field id="price" label="Valor (R$)" error={form.errors.price} hint={needsAmount ? 'Obrigatório para este tipo de preço.' : 'Não informe valor para preço sob consulta.'}>
                            <Input id="price" type="number" min={0} step="0.01" value={form.data.price} disabled={!needsAmount} onChange={(e) => form.setData('price', e.target.value)} className="h-10" />
                        </Field>

                        <div className="md:col-span-2">
                            <Field id="description" label="Descrição" error={form.errors.description}>
                                <textarea id="description" rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none" />
                            </Field>
                        </div>

                        <label className="flex items-center gap-3 text-sm text-vs-text">
                            <input type="checkbox" checked={form.data.requires_evaluation} onChange={(e) => form.setData('requires_evaluation', e.target.checked)} className="size-4 accent-vs-blue" />
                            Exige avaliação antes de informar preço ou agendar
                        </label>

                        <label className="flex items-center gap-3 text-sm text-vs-text">
                            <input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} className="size-4 accent-vs-blue" />
                            Serviço ativo
                        </label>

                        <div className="flex flex-wrap gap-3 md:col-span-2">
                            <Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar</Button>
                            <Button asChild variant="outline" className="h-10"><Link href={servicesIndex.url()}>Cancelar</Link></Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}
