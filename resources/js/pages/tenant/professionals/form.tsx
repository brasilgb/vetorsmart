import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { index as professionalsIndex, store, update } from '@/routes/tenant/professionals';

type Window = { weekday: number; start_time: string; end_time: string };

type Professional = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    notes: string | null;
    active: boolean;
    service_ids: number[];
    windows: Window[];
};

type Props = {
    professional: Professional | null;
    services: { id: number; name: string }[];
};

const weekdays = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

/**
 * Identification, the services this person performs, and the working windows.
 * Overlapping windows on the same day are refused by the server.
 */
export default function ProfessionalForm({ professional, services }: Props) {
    const form = useForm({
        name: professional?.name ?? '',
        email: professional?.email ?? '',
        phone: professional?.phone ?? '',
        notes: professional?.notes ?? '',
        active: professional?.active ?? true,
        service_ids: professional?.service_ids ?? ([] as number[]),
        windows: professional?.windows ?? ([] as Window[]),
    });

    const toggleService = (id: number) => {
        form.setData('service_ids', form.data.service_ids.includes(id) ? form.data.service_ids.filter((s) => s !== id) : [...form.data.service_ids, id]);
    };

    const addWindow = () => {
        form.setData('windows', [...form.data.windows, { weekday: 1, start_time: '08:00', end_time: '17:00' }]);
    };

    const updateWindow = (index: number, patch: Partial<Window>) => {
        form.setData('windows', form.data.windows.map((window, i) => (i === index ? { ...window, ...patch } : window)));
    };

    const removeWindow = (index: number) => {
        form.setData('windows', form.data.windows.filter((_, i) => i !== index));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (professional) {
            form.patch(update.url({ professional: professional.id }));
        } else {
            form.post(store.url());
        }
    };

    return (
        <>
            <Head title={professional ? 'Editar profissional' : 'Novo profissional'} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={professional ? 'Editar profissional' : 'Novo profissional'} description="Dados, serviços que executa e horários de trabalho." />

                <form onSubmit={submit} className="grid gap-6">
                    <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm md:grid-cols-2">
                        <div className="md:col-span-2">
                            <Field id="name" label="Nome" error={form.errors.name}>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus className="h-10" />
                            </Field>
                        </div>
                        <Field id="phone" label="Telefone" error={form.errors.phone}>
                            <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="h-10" />
                        </Field>
                        <Field id="email" label="E-mail" error={form.errors.email}>
                            <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} onInvalid={(e) => e.currentTarget.setCustomValidity('Informe um e-mail válido, como nome@empresa.com.br.')} onInput={(e) => e.currentTarget.setCustomValidity('')} className="h-10" />
                        </Field>
                        <div className="md:col-span-2">
                            <Field id="notes" label="Especialidades e observações" error={form.errors.notes}>
                                <textarea id="notes" rows={3} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none" />
                            </Field>
                        </div>
                        <label className="flex items-center gap-3 text-sm text-vs-text md:col-span-2">
                            <input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} className="size-4 accent-vs-blue" />
                            Profissional ativo (recebe novos agendamentos)
                        </label>
                    </Card>

                    <Card className="grid gap-4 border-vs-border/70 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Serviços que executa</h2>
                        {services.length === 0 ? (
                            <p className="text-sm text-vs-muted">Nenhum serviço ativo cadastrado ainda.</p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {services.map((service) => (
                                    <label key={service.id} className="flex items-center gap-3 rounded-md border border-vs-border/70 px-3 py-2 text-sm text-vs-text">
                                        <input type="checkbox" checked={form.data.service_ids.includes(service.id)} onChange={() => toggleService(service.id)} className="size-4 accent-vs-blue" />
                                        {service.name}
                                    </label>
                                ))}
                            </div>
                        )}
                        {form.errors.service_ids && <p className="text-sm text-red-600">{form.errors.service_ids}</p>}
                    </Card>

                    <Card className="grid gap-4 border-vs-border/70 p-6 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-base font-semibold text-vs-text">Horários de trabalho</h2>
                            <Button type="button" variant="outline" onClick={addWindow} className="h-9">Adicionar janela</Button>
                        </div>
                        {form.data.windows.length === 0 && <p className="text-sm text-vs-muted">Sem horários definidos.</p>}
                        {form.data.windows.map((window, index) => (
                            <div key={index} className="grid gap-3 sm:grid-cols-[1fr_140px_140px_auto] sm:items-end">
                                <Field id={`weekday-${index}`} label="Dia">
                                    <select id={`weekday-${index}`} value={window.weekday} onChange={(e) => updateWindow(index, { weekday: Number(e.target.value) })} className={selectClass}>
                                        {weekdays.map((name, day) => (
                                            <option key={day} value={day}>{name}</option>
                                        ))}
                                    </select>
                                </Field>
                                <Field id={`start-${index}`} label="Início">
                                    <Input id={`start-${index}`} type="time" value={window.start_time} onChange={(e) => updateWindow(index, { start_time: e.target.value })} className="h-10" />
                                </Field>
                                <Field id={`end-${index}`} label="Término">
                                    <Input id={`end-${index}`} type="time" value={window.end_time} onChange={(e) => updateWindow(index, { end_time: e.target.value })} className="h-10" />
                                </Field>
                                <Button type="button" variant="ghost" onClick={() => removeWindow(index)} className="h-10 text-red-600">Remover</Button>
                            </div>
                        ))}
                        {(form.errors as Record<string, string | undefined>).availability && <p className="text-sm text-red-600">{(form.errors as Record<string, string | undefined>).availability}</p>}
                    </Card>

                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar</Button>
                        <Button asChild variant="outline" className="h-10"><Link href={professionalsIndex.url()}>Cancelar</Link></Button>
                    </div>
                </form>
            </div>
        </>
    );
}
