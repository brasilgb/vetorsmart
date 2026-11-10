import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { index as customersIndex, store, update } from '@/routes/tenant/customers';

type Customer = {
    id: number;
    name: string;
    person_type: string;
    document: string | null;
    email: string | null;
    phone: string | null;
    whatsapp: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    notes: string | null;
    status: string;
};

type Props = {
    customer: Customer | null;
};

/**
 * One form for create and edit. Only the name is required; everything else is optional.
 */
export default function CustomerForm({ customer }: Props) {
    const form = useForm({
        name: customer?.name ?? '',
        person_type: customer?.person_type ?? 'individual',
        document: customer?.document ?? '',
        email: customer?.email ?? '',
        phone: customer?.phone ?? '',
        whatsapp: customer?.whatsapp ?? '',
        address: customer?.address ?? '',
        city: customer?.city ?? '',
        state: customer?.state ?? '',
        notes: customer?.notes ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (customer) {
            form.patch(update.url({ customer: customer.id }));
        } else {
            form.post(store.url());
        }
    };

    return (
        <>
            <Head title={customer ? 'Editar cliente' : 'Novo cliente'} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={customer ? 'Editar cliente' : 'Novo cliente'} description="Preencha o que já souber. Só o nome é obrigatório." />

                <Card className="border-vs-border/70 p-6 shadow-sm">
                    <form onSubmit={submit} className="grid gap-5 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <Field id="name" label="Nome" error={form.errors.name}>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus className="h-10" />
                            </Field>
                        </div>

                        <Field id="person_type" label="Tipo" error={form.errors.person_type}>
                            <select id="person_type" value={form.data.person_type} onChange={(e) => form.setData('person_type', e.target.value)} className={selectClass}>
                                <option value="individual">Pessoa física</option>
                                <option value="company">Pessoa jurídica</option>
                            </select>
                        </Field>

                        <Field id="document" label="CPF ou CNPJ" error={form.errors.document} hint="Opcional. Apenas números são guardados.">
                            <Input id="document" value={form.data.document} onChange={(e) => form.setData('document', e.target.value)} className="h-10" />
                        </Field>

                        <Field id="phone" label="Telefone" error={form.errors.phone}>
                            <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="h-10" />
                        </Field>

                        <Field id="whatsapp" label="WhatsApp" error={form.errors.whatsapp}>
                            <Input id="whatsapp" value={form.data.whatsapp} onChange={(e) => form.setData('whatsapp', e.target.value)} className="h-10" />
                        </Field>

                        <div className="md:col-span-2">
                            <Field id="email" label="E-mail" error={form.errors.email}>
                                <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="h-10" />
                            </Field>
                        </div>

                        <div className="md:col-span-2">
                            <Field id="address" label="Endereço" error={form.errors.address}>
                                <Input id="address" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} className="h-10" />
                            </Field>
                        </div>

                        <Field id="city" label="Cidade" error={form.errors.city}>
                            <Input id="city" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} className="h-10" />
                        </Field>

                        <Field id="state" label="UF" error={form.errors.state}>
                            <Input id="state" maxLength={2} value={form.data.state} onChange={(e) => form.setData('state', e.target.value.toUpperCase())} className="h-10" />
                        </Field>

                        <div className="md:col-span-2">
                            <Field id="notes" label="Observações" error={form.errors.notes}>
                                <textarea id="notes" rows={3} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none" />
                            </Field>
                        </div>

                        {(form.errors as Record<string, string | undefined>).duplicate && (
                            <p className="md:col-span-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{(form.errors as Record<string, string | undefined>).duplicate}</p>
                        )}

                        <div className="flex flex-wrap gap-3 md:col-span-2">
                            <Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar</Button>
                            <Button asChild variant="outline" className="h-10"><Link href={customersIndex.url()}>Cancelar</Link></Button>
                        </div>
                    </form>
                </Card>
            </div>
        </>
    );
}
