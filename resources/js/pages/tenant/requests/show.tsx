import { Head, Link, router, useForm } from '@inertiajs/react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as requestsIndex, triage, schedule, start, complete, notPerformed, cancel, assign, amount } from '@/routes/tenant/requests';
import { show as customerShow } from '@/routes/tenant/customers';
import { index as agendaIndex } from '@/routes/tenant/agenda';
import { TraceabilityRows, type Traceability } from '@/components/vetor-smart/traceability';

type RequestDetail = Traceability & {
    id: number;
    customer: string | null;
    customer_id: number | null;
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
    origin_value: string;
    discovery: string | null;
    municipality: string | null;
    closing_reason: string | null;
    created_at: string;
    open: boolean;
    can_triage: boolean;
    can_schedule: boolean;
    can_start: boolean;
    can_complete: boolean;
    can_close_unperformed: boolean;
    can_cancel: boolean;
    assignee: string | null;
    assigned_user_id: number | null;
    can_assign: boolean;
    realized_amount: string | null;
    realized_amount_input: string;
    realized_amount_recorded_at: string | null;
    can_record_amount: boolean;
};

type Props = {
    request: RequestDetail;
    events: { id: number; type: string; to_status: string | null; note: string | null; user: string | null; at: string }[];
    appointments: { id: number; starts_at: string; date: string; professional: string | null; status: string }[];
    services: { id: number; name: string; duration_minutes: number | null }[];
    professionals: { id: number; name: string; service_ids: number[] }[];
    assignees: { id: number; name: string }[];
};

/** Names of history entries that do not change the status. */
const eventLabels: Record<string, string> = {
    assigned: 'Responsável alterado',
    realized_amount: 'Valor realizado',
};

const priorities = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];

export default function RequestShow({ request, events, appointments, services, professionals, assignees }: Props) {
    const triageForm = useForm({ service_id: request.service_id ? String(request.service_id) : '', priority: request.priority_value });
    const scheduleForm = useForm<Record<string, string>>({ professional_id: '', date: '', time: '' });
    const reasonForm = useForm({ reason: '' });
    const assignForm = useForm({ assigned_user_id: request.assigned_user_id ? String(request.assigned_user_id) : '' });
    const completeForm = useForm({ realized_amount: '' });
    const amountForm = useForm({ realized_amount: request.realized_amount_input });

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
                    action={<Link href={requestsIndex.url()} className="text-sm font-medium text-vs-link hover:underline">Voltar à lista</Link>}
                />

                <div className="grid gap-6 lg:grid-cols-[1fr_1fr]">
                    <Card className="grid gap-3 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Dados</h2>
                        <dl className="grid gap-2 text-sm">
                            <div><dt className="text-vs-muted">Cliente</dt><dd className="text-vs-text">{request.customer && request.customer_id ? <Link href={customerShow.url({ customer: request.customer_id })} className="font-medium text-vs-link hover:underline">{request.customer}</Link> : (request.customer ?? '—')}{request.customer_phone ? ` · ${request.customer_phone}` : ''}</dd></div>
                            <div><dt className="text-vs-muted">Serviço</dt><dd className="text-vs-text">{request.service ?? 'A definir na triagem'}</dd></div>
                            <div><dt className="text-vs-muted">Prioridade</dt><dd className="text-vs-text">{request.priority}</dd></div>
                            <div><dt className="text-vs-muted">Responsável</dt><dd className="text-vs-text">{request.assignee ?? 'Sem responsável'}</dd></div>
                            <TraceabilityRows value={request} />
                            {request.municipality && (
                                <div>
                                    <dt className="text-vs-muted">Município do atendimento</dt>
                                    <dd className="break-words text-vs-text">{request.municipality}</dd>
                                </div>
                            )}
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
                                        {' · '}
                                        <Link href={agendaIndex.url({ query: { view: 'day', date: appointment.date } })} className="font-medium text-vs-link hover:underline">Ver na agenda</Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                {request.can_triage && (
                    <Card id="triagem" className="grid max-w-3xl scroll-mt-20 gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Triagem</h2>
                        {triageErrorsWithoutField.length > 0 && (
                            <p role="alert" className="rounded-md border border-vs-danger/30 bg-vs-danger-soft px-3 py-2 text-sm text-vs-danger">
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
                    <Card id="agendamento" className="grid max-w-3xl scroll-mt-20 gap-4 border-vs-border/70 p-5 shadow-sm">
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

                {request.can_assign && (
                    <Card className="grid max-w-3xl gap-3 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Responsável pelo atendimento</h2>
                        <p className="text-sm text-vs-muted">Pessoa da equipe que acompanha esta solicitação. É diferente do profissional do agendamento.</p>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                assignForm.patch(assign.url({ serviceRequest: request.id }), { preserveScroll: true });
                            }}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <Field id="assigned_user_id" label="Responsável" error={assignForm.errors.assigned_user_id}>
                                <select id="assigned_user_id" value={assignForm.data.assigned_user_id} onChange={(event) => assignForm.setData('assigned_user_id', event.target.value)} className={`${selectClass} min-w-[240px]`}>
                                    <option value="">Sem responsável</option>
                                    {assignees.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
                                </select>
                            </Field>
                            <Button type="submit" disabled={assignForm.processing || assignForm.data.assigned_user_id === (request.assigned_user_id ? String(request.assigned_user_id) : '')} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">
                                {request.assigned_user_id ? 'Transferir' : 'Definir responsável'}
                            </Button>
                        </form>
                    </Card>
                )}

                {(request.can_start || request.can_complete) && (
                    <Card className="grid max-w-3xl gap-4 border-vs-border/70 p-5 shadow-sm">
                        {request.can_start && (
                            <div><Button type="button" onClick={() => router.post(start.url({ serviceRequest: request.id }))} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Iniciar atendimento</Button></div>
                        )}
                        {request.can_complete && (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    completeForm.post(complete.url({ serviceRequest: request.id }));
                                }}
                                className="grid gap-3"
                            >
                                <h2 className="text-base font-semibold text-vs-text">Concluir atendimento</h2>
                                <Field id="complete_amount" label="Valor realizado (opcional)" error={completeForm.errors.realized_amount} hint="Valor que a empresa recebeu por este serviço, por exemplo 1.250,00. Deixe em branco se ainda não souber: você pode informar depois.">
                                    <Input id="complete_amount" inputMode="decimal" autoComplete="off" value={completeForm.data.realized_amount} onChange={(event) => completeForm.setData('realized_amount', event.target.value)} maxLength={20} className="max-w-[220px]" />
                                </Field>
                                <div><Button type="submit" disabled={completeForm.processing} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Concluir atendimento</Button></div>
                            </form>
                        )}
                    </Card>
                )}

                {request.status === 'completed' && (
                    <Card className="grid max-w-3xl gap-3 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Valor realizado</h2>
                        <p className="text-sm text-vs-text">
                            {request.realized_amount ?? 'Não informado'}
                            {request.realized_amount_recorded_at && <span className="text-vs-muted"> · registrado em {request.realized_amount_recorded_at}</span>}
                        </p>
                        <p className="text-xs text-vs-muted">Valor declarado pela empresa para acompanhar o crescimento. Não é comprovante de pagamento.</p>
                        {request.can_record_amount && (
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    amountForm.patch(amount.url({ serviceRequest: request.id }), { preserveScroll: true });
                                }}
                                className="flex flex-wrap items-end gap-3"
                            >
                                <Field id="realized_amount" label={request.realized_amount ? 'Corrigir valor (R$)' : 'Informar valor (R$)'} error={amountForm.errors.realized_amount} hint={request.realized_amount ? 'A correção fica registrada no histórico. Deixe em branco para marcar como não informado.' : undefined}>
                                    <Input id="realized_amount" inputMode="decimal" autoComplete="off" value={amountForm.data.realized_amount} onChange={(event) => amountForm.setData('realized_amount', event.target.value)} maxLength={20} className="max-w-[220px]" />
                                </Field>
                                <Button type="submit" variant="outline" disabled={amountForm.processing || amountForm.data.realized_amount === request.realized_amount_input} className="h-10">Salvar valor</Button>
                            </form>
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
                                <ConfirmAction
                                    trigger={<Button type="button" variant="outline" disabled={reasonForm.processing || reasonForm.data.reason === ''}>Registrar como não realizado</Button>}
                                    title="Registrar como não realizado?"
                                    description="A solicitação será encerrada com o motivo informado."
                                    confirmLabel="Registrar"
                                    destructive
                                    onConfirm={() => reasonForm.post(notPerformed.url({ serviceRequest: request.id }))}
                                />
                            )}
                            {request.can_cancel && (
                                <ConfirmAction
                                    trigger={<Button type="button" variant="outline" className="text-vs-danger" disabled={reasonForm.processing || reasonForm.data.reason === ''}>Cancelar solicitação</Button>}
                                    title="Cancelar solicitação?"
                                    description="A solicitação será cancelada com o motivo informado. Agendamentos ativos dela também são afetados."
                                    confirmLabel="Cancelar solicitação"
                                    destructive
                                    onConfirm={() => reasonForm.post(cancel.url({ serviceRequest: request.id }))}
                                />
                            )}
                        </div>
                    </Card>
                )}

                <Card className="grid max-w-3xl gap-3 border-vs-border/70 p-5 shadow-sm">
                    <h2 className="text-base font-semibold text-vs-text">Histórico</h2>
                    <ol className="grid gap-2 text-sm">
                        {events.map((event) => (
                            <li key={event.id} className="border-l-2 border-vs-border pl-3">
                                <span className="font-medium text-vs-text">{event.to_status ?? eventLabels[event.type] ?? event.type}</span>
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
