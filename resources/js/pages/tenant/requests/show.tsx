import { Head, Link, router, useForm } from '@inertiajs/react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as requestsIndex, triage, schedule, start, complete, notPerformed, cancel } from '@/routes/tenant/requests';

type RequestDetail = {
    id: number;
    customer: string | null;
    customer_phone: string | null;
    service: string | null;
    service_id: number | null;
    professional_id: number | null;
    priority: string;
    priority_value: string;
    status: string;
    status_label: string;
    description: string;
    origin: string;
    closing_reason: string | null;
    created_at: string;
    open: boolean;
    can_triage: boolean;
    can_schedule: boolean;
    can_start: boolean;
    can_complete: boolean;
    can_close_unperformed: boolean;
    can_cancel: boolean;
};

type Props = {
    request: RequestDetail;
    events: { id: number; type: string; to_status: string | null; note: string | null; user: string | null; at: string }[];
    appointments: { id: number; starts_at: string; professional: string | null; status: string }[];
    services: { id: number; name: string; duration_minutes: number | null }[];
    professionals: { id: number; name: string; service_ids: number[] }[];
};

const priorities = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];

export default function RequestShow({ request, events, appointments, services, professionals }: Props) {
    const triageForm = useForm({ service_id: request.service_id ? String(request.service_id) : '', priority: request.priority_value });
    const scheduleForm = useForm<Record<string, string>>({ professional_id: '', date: '', time: '' });
    const reasonForm = useForm({ reason: '' });

    const chosenService = Number(triageForm.data.service_id || request.service_id || 0);
    const eligibleProfessionals = professionals.filter((professional) => professional.service_ids.includes(chosenService));
    const triageErrorsWithoutField = Object.entries(triageForm.errors)
        .filter(([key]) => key !== 'service_id' && key !== 'priority')
        .map(([, message]) => message);

    return (
        <>
            <Head title={`Solicitação #${request.id}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Solicitação #${request.id}`}
                    description={`Situação: ${request.status_label}`}
                    action={<Link href={requestsIndex.url()} className="text-sm font-medium text-vs-blue hover:underline">Voltar à lista</Link>}
                />

                <div className="grid gap-6 lg:grid-cols-[1fr_1fr]">
                    <Card className="grid gap-3 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Dados</h2>
                        <dl className="grid gap-2 text-sm">
                            <div><dt className="text-vs-muted">Cliente</dt><dd className="text-vs-text">{request.customer ?? '—'}{request.customer_phone ? ` · ${request.customer_phone}` : ''}</dd></div>
                            <div><dt className="text-vs-muted">Serviço</dt><dd className="text-vs-text">{request.service ?? 'A definir na triagem'}</dd></div>
                            <div><dt className="text-vs-muted">Prioridade</dt><dd className="text-vs-text">{request.priority}</dd></div>
                            <div><dt className="text-vs-muted">Origem</dt><dd className="text-vs-text">{request.origin}</dd></div>
                            <div><dt className="text-vs-muted">Descrição</dt><dd className="whitespace-pre-line text-vs-text">{request.description}</dd></div>
                            {request.closing_reason && <div><dt className="text-vs-muted">Motivo do encerramento</dt><dd className="text-vs-text">{request.closing_reason}</dd></div>}
                        </dl>
                    </Card>

                    <Card className="grid gap-3 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Atendimentos</h2>
                        {appointments.length === 0 ? (
                            <p className="text-sm text-vs-muted">Nenhum agendamento ainda.</p>
                        ) : (
                            <ul className="grid gap-2 text-sm">
                                {appointments.map((appointment) => (
                                    <li key={appointment.id} className="rounded-md border border-vs-border/70 p-3">
                                        {appointment.starts_at} · {appointment.professional ?? '—'} · <span className="font-medium">{appointment.status}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                {request.can_triage && (
                    <Card className="grid max-w-3xl gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Triagem</h2>
                        {triageErrorsWithoutField.length > 0 && (
                            <p role="alert" className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                A triagem não foi salva. {triageErrorsWithoutField.join(' ')}
                            </p>
                        )}
                        <form
                            className="grid gap-4 md:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                triageForm.patch(triage.url({ serviceRequest: request.id }), { preserveScroll: true });
                            }}
                        >
                            <Field id="triage_service" label="Serviço" error={triageForm.errors.service_id}>
                                <select id="triage_service" value={triageForm.data.service_id} onChange={(event) => triageForm.setData('service_id', event.target.value)} className={selectClass} required>
                                    <option value="">Selecione o serviço</option>
                                    {services.map((service) => (
                                        <option key={service.id} value={service.id}>{service.name}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field id="triage_priority" label="Prioridade" error={triageForm.errors.priority}>
                                <select id="triage_priority" value={triageForm.data.priority} onChange={(event) => triageForm.setData('priority', event.target.value)} className={selectClass}>
                                    {priorities.map((priority) => (
                                        <option key={priority.value} value={priority.value}>{priority.label}</option>
                                    ))}
                                </select>
                            </Field>
                            <div className="md:col-span-2">
                                <Button type="submit" disabled={triageForm.processing} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar triagem</Button>
                            </div>
                        </form>
                    </Card>
                )}

                {request.can_schedule && (
                    <Card className="grid max-w-3xl gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Agendamento</h2>
                        <p className="text-sm text-vs-muted">Os horários são validados no servidor, conforme a jornada, os bloqueios e os agendamentos do profissional.</p>
                        <form
                            className="grid gap-4 md:grid-cols-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                scheduleForm.transform((data) => ({ professional_id: data.professional_id, starts_at: `${data.date} ${data.time}` }));
                                scheduleForm.post(schedule.url({ serviceRequest: request.id }));
                            }}
                        >
                            <Field id="schedule_professional" label="Profissional" error={scheduleForm.errors.professional_id}>
                                <select id="schedule_professional" value={scheduleForm.data.professional_id} onChange={(event) => scheduleForm.setData('professional_id', event.target.value)} className={selectClass} required>
                                    <option value="">Selecione</option>
                                    {eligibleProfessionals.map((professional) => (
                                        <option key={professional.id} value={professional.id}>{professional.name}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field id="schedule_date" label="Data" error={scheduleForm.errors.starts_at}>
                                <Input id="schedule_date" type="date" value={scheduleForm.data.date} onChange={(event) => scheduleForm.setData('date', event.target.value)} required />
                            </Field>
                            <Field id="schedule_time" label="Horário">
                                <Input id="schedule_time" type="time" value={scheduleForm.data.time} onChange={(event) => scheduleForm.setData('time', event.target.value)} required />
                            </Field>
                            <div className="md:col-span-3">
                                <Button type="submit" disabled={scheduleForm.processing} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Agendar</Button>
                            </div>
                        </form>
                    </Card>
                )}

                {(request.can_start || request.can_complete) && (
                    <Card className="flex max-w-3xl flex-wrap gap-3 border-vs-border/70 p-5 shadow-sm">
                        {request.can_start && (
                            <Button type="button" onClick={() => router.post(start.url({ serviceRequest: request.id }))} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Iniciar atendimento</Button>
                        )}
                        {request.can_complete && (
                            <Button type="button" onClick={() => router.post(complete.url({ serviceRequest: request.id }))} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Concluir atendimento</Button>
                        )}
                    </Card>
                )}

                {(request.can_close_unperformed || request.can_cancel) && (
                    <Card className="grid max-w-3xl gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Encerrar sem conclusão</h2>
                        <Field id="closing_reason" label="Motivo" error={reasonForm.errors.reason} hint="Obrigatório para não realizar ou cancelar.">
                            <Input id="closing_reason" value={reasonForm.data.reason} onChange={(event) => reasonForm.setData('reason', event.target.value)} maxLength={500} />
                        </Field>
                        <div className="flex flex-wrap gap-3">
                            {request.can_close_unperformed && (
                                <Button type="button" variant="outline" disabled={reasonForm.processing || reasonForm.data.reason === ''} onClick={() => reasonForm.post(notPerformed.url({ serviceRequest: request.id }))}>Registrar como não realizado</Button>
                            )}
                            {request.can_cancel && (
                                <Button type="button" variant="outline" className="text-red-600" disabled={reasonForm.processing || reasonForm.data.reason === ''} onClick={() => reasonForm.post(cancel.url({ serviceRequest: request.id }))}>Cancelar solicitação</Button>
                            )}
                        </div>
                    </Card>
                )}

                <Card className="grid max-w-3xl gap-3 border-vs-border/70 p-5 shadow-sm">
                    <h2 className="text-base font-semibold text-vs-text">Histórico</h2>
                    <ol className="grid gap-2 text-sm">
                        {events.map((event) => (
                            <li key={event.id} className="border-l-2 border-vs-border pl-3">
                                <span className="font-medium text-vs-text">{event.to_status ?? event.type}</span>
                                <span className="text-vs-muted"> · {event.at}{event.user ? ` · ${event.user}` : ''}</span>
                                {event.note && <p className="text-vs-muted">{event.note}</p>}
                            </li>
                        ))}
                    </ol>
                </Card>
            </div>
        </>
    );
}
