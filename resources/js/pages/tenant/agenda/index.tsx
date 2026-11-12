import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index as agendaIndex, cancel as cancelAppointment, reschedule, slots as slotsRoute } from '@/routes/tenant/agenda';
import { destroy as destroyBlock, store as storeBlock } from '@/routes/tenant/agenda/blocks';
import { schedule, show as requestShow } from '@/routes/tenant/requests';

type Appointment = {
    id: number;
    request_id: number;
    date: string;
    time: string;
    ends: string;
    professional: string | null;
    customer: string | null;
    service: string | null;
    status: string;
    status_label: string;
    can_change: boolean;
    starts_at: string;
};

type Block = { id: number; date: string; time: string; ends: string; professional: string | null; reason: string | null };

type Props = {
    view: 'day' | 'week' | 'month';
    date: string;
    range: { from: string; to: string };
    appointments: Appointment[];
    blocks: Block[];
    professionals: { id: number; name: string }[];
    openRequests: { id: number; label: string; service_id: number }[];
};

const viewLabels = { day: 'Dia', week: 'Semana', month: 'Mês' } as const;

function shiftDate(date: string, view: Props['view'], direction: 1 | -1): string {
    const value = new Date(`${date}T00:00:00`);

    if (view === 'day') {
        value.setDate(value.getDate() + direction);
    } else if (view === 'week') {
        value.setDate(value.getDate() + 7 * direction);
    } else {
        value.setMonth(value.getMonth() + direction);
    }

    return value.toISOString().slice(0, 10);
}

function daysBetween(from: string, to: string): string[] {
    const days: string[] = [];
    const cursor = new Date(`${from}T00:00:00`);
    const end = new Date(`${to}T00:00:00`);

    while (cursor <= end) {
        days.push(cursor.toISOString().slice(0, 10));
        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
}

export default function AgendaIndex({ view, date, range, appointments, blocks, professionals, openRequests }: Props) {
    const [slots, setSlots] = useState<string[]>([]);
    const [slotsMessage, setSlotsMessage] = useState<string | null>(null);

    const booking = useForm<Record<string, string>>({ request_id: '', professional_id: '', date: date, time: '' });
    const blocking = useForm<Record<string, string>>({ professional_id: '', date: date, start: '', end: '', reason: '' });

    const navigate = (nextView: Props['view'], nextDate: string) => {
        router.get(agendaIndex.url(), { view: nextView, date: nextDate }, { preserveState: true, replace: true });
    };

    const loadSlots = async (requestId: string, professionalId: string, day: string) => {
        setSlots([]);
        setSlotsMessage(null);

        if (!requestId || !professionalId || !day) {
            return;
        }

        const query = new URLSearchParams({ request_id: requestId, professional_id: professionalId, date: day });
        const response = await fetch(`${slotsRoute.url()}?${query.toString()}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (!response.ok) {
            setSlotsMessage('Não foi possível consultar os horários.');
            return;
        }

        const payload: { slots: string[] } = await response.json();
        setSlots(payload.slots);
        setSlotsMessage(payload.slots.length === 0 ? 'Sem horários livres neste dia.' : null);
    };

    const days = daysBetween(range.from, range.to);
    const appointmentsByDay = (day: string) => appointments.filter((appointment) => appointment.date === day);
    const blocksByDay = (day: string) => blocks.filter((block) => block.date === day);

    return (
        <>
            <Head title="Agenda" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Agenda" description="Visualize atendimentos e bloqueios, agende horários livres e remarque ou cancele quando necessário." />

                <div className="flex flex-wrap items-center gap-3">
                    <div role="group" aria-label="Visualização" className="inline-flex rounded-md border border-vs-border bg-white p-1">
                        {(Object.keys(viewLabels) as (keyof typeof viewLabels)[]).map((option) => (
                            <button
                                key={option}
                                type="button"
                                aria-pressed={view === option}
                                onClick={() => navigate(option, date)}
                                className={`rounded px-3 py-1.5 text-sm font-medium ${view === option ? 'bg-vs-blue text-white' : 'text-vs-muted hover:text-vs-text'}`}
                            >
                                {viewLabels[option]}
                            </button>
                        ))}
                    </div>
                    <Button type="button" variant="outline" onClick={() => navigate(view, shiftDate(date, view, -1))}>Anterior</Button>
                    <Button type="button" variant="outline" onClick={() => navigate(view, new Date().toISOString().slice(0, 10))}>Hoje</Button>
                    <Button type="button" variant="outline" onClick={() => navigate(view, shiftDate(date, view, 1))}>Próximo</Button>
                    <p className="text-sm text-vs-muted">{range.from} a {range.to}</p>
                </div>

                <div className={`grid gap-4 ${view === 'day' ? 'grid-cols-1' : 'sm:grid-cols-2 xl:grid-cols-4'}`}>
                    {days.map((day) => (
                        <Card key={day} className="grid content-start gap-2 border-vs-border/70 p-4 shadow-sm">
                            <h2 className="text-sm font-semibold text-vs-text">{day}</h2>
                            {appointmentsByDay(day).length === 0 && blocksByDay(day).length === 0 && (
                                <p className="text-sm text-vs-muted">Sem compromissos.</p>
                            )}
                            {appointmentsByDay(day).map((appointment) => (
                                <div key={appointment.id} className="rounded-md border border-vs-border/70 p-3 text-sm">
                                    <p className="font-medium text-vs-text">{appointment.time}–{appointment.ends} · {appointment.service ?? 'Serviço'}</p>
                                    <p className="text-vs-muted">{appointment.customer ?? '—'} · {appointment.professional ?? '—'}</p>
                                    <p className="text-vs-muted">Situação: {appointment.status_label}</p>
                                    <div className="mt-2 flex flex-wrap gap-3 text-sm">
                                        <Link href={requestShow.url({ serviceRequest: appointment.request_id })} className="font-medium text-vs-blue hover:underline">Abrir solicitação</Link>
                                        {appointment.can_change && (
                                            <>
                                                <details>
                                                    <summary className="cursor-pointer font-medium text-vs-blue hover:underline">Remarcar</summary>
                                                    <RescheduleForm appointmentId={appointment.id} current={appointment.starts_at} />
                                                </details>
                                                <CancelAppointment appointmentId={appointment.id} />
                                            </>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {blocksByDay(day).map((block) => (
                                <div key={`block-${block.id}`} className="rounded-md bg-vs-surface p-3 text-sm text-vs-muted">
                                    Bloqueio {block.time}–{block.ends} · {block.professional ?? '—'}{block.reason ? ` · ${block.reason}` : ''}
                                    <div className="mt-1">
                                        <ConfirmAction
                                            trigger={<button type="button" className="font-medium text-red-600 hover:underline">Remover bloqueio</button>}
                                            title="Remover bloqueio?"
                                            description="O período volta a aceitar agendamentos."
                                            confirmLabel="Remover"
                                            destructive
                                            onConfirm={() => router.delete(destroyBlock.url({ block: block.id }), { preserveScroll: true })}
                                        />
                                    </div>
                                </div>
                            ))}
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="grid content-start gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Novo agendamento</h2>
                        {openRequests.length === 0 ? (
                            <p className="text-sm text-vs-muted">Nenhuma solicitação triada aguardando horário. Triagem as solicitações para agendá-las aqui.</p>
                        ) : (
                            <form
                                className="grid gap-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    booking.transform((data) => ({ professional_id: data.professional_id, starts_at: `${data.date} ${data.time}` }));
                                    booking.post(schedule.url({ serviceRequest: Number(booking.data.request_id) }));
                                }}
                            >
                                <Field id="booking_request" label="Solicitação">
                                    <select id="booking_request" value={booking.data.request_id} onChange={(event) => { booking.setData('request_id', event.target.value); loadSlots(event.target.value, booking.data.professional_id, booking.data.date); }} className={selectClass} required>
                                        <option value="">Selecione</option>
                                        {openRequests.map((item) => (
                                            <option key={item.id} value={item.id}>{item.label}</option>
                                        ))}
                                    </select>
                                </Field>
                                <Field id="booking_professional" label="Profissional" error={booking.errors.professional_id}>
                                    <select id="booking_professional" value={booking.data.professional_id} onChange={(event) => { booking.setData('professional_id', event.target.value); loadSlots(booking.data.request_id, event.target.value, booking.data.date); }} className={selectClass} required>
                                        <option value="">Selecione</option>
                                        {professionals.map((professional) => (
                                            <option key={professional.id} value={professional.id}>{professional.name}</option>
                                        ))}
                                    </select>
                                </Field>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field id="booking_date" label="Data">
                                        <Input id="booking_date" type="date" value={booking.data.date} onChange={(event) => { booking.setData('date', event.target.value); loadSlots(booking.data.request_id, booking.data.professional_id, event.target.value); }} required />
                                    </Field>
                                    <Field id="booking_time" label="Horário" error={booking.errors.starts_at}>
                                        <Input id="booking_time" type="time" value={booking.data.time} onChange={(event) => booking.setData('time', event.target.value)} required />
                                    </Field>
                                </div>
                                <div className="grid gap-2">
                                    <p className="text-sm font-medium text-vs-text">Horários livres</p>
                                    {slotsMessage && <p className="text-sm text-vs-muted">{slotsMessage}</p>}
                                    <div className="flex flex-wrap gap-2">
                                        {slots.map((slot) => (
                                            <button
                                                key={slot}
                                                type="button"
                                                onClick={() => booking.setData('time', slot)}
                                                aria-pressed={booking.data.time === slot}
                                                className={`rounded-md border px-3 py-1.5 text-sm ${booking.data.time === slot ? 'border-vs-blue bg-vs-blue text-white' : 'border-vs-border bg-white text-vs-text'}`}
                                            >
                                                {slot}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <Button type="submit" disabled={booking.processing} className="bg-vs-blue text-white hover:bg-vs-blue-hover">Agendar</Button>
                                </div>
                            </form>
                        )}
                    </Card>

                    <Card className="grid content-start gap-4 border-vs-border/70 p-5 shadow-sm">
                        <h2 className="text-base font-semibold text-vs-text">Bloquear horário</h2>
                        <form
                            className="grid gap-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                blocking.transform((data) => ({
                                    professional_id: data.professional_id,
                                    starts_at: `${data.date} ${data.start}`,
                                    ends_at: `${data.date} ${data.end}`,
                                    reason: data.reason,
                                }));
                                blocking.post(storeBlock.url());
                            }}
                        >
                            <Field id="block_professional" label="Profissional" error={blocking.errors.professional_id}>
                                <select id="block_professional" value={blocking.data.professional_id} onChange={(event) => blocking.setData('professional_id', event.target.value)} className={selectClass} required>
                                    <option value="">Selecione</option>
                                    {professionals.map((professional) => (
                                        <option key={professional.id} value={professional.id}>{professional.name}</option>
                                    ))}
                                </select>
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field id="block_date" label="Data">
                                    <Input id="block_date" type="date" value={blocking.data.date} onChange={(event) => blocking.setData('date', event.target.value)} required />
                                </Field>
                                <Field id="block_start" label="Início" error={blocking.errors.starts_at}>
                                    <Input id="block_start" type="time" value={blocking.data.start} onChange={(event) => blocking.setData('start', event.target.value)} required />
                                </Field>
                                <Field id="block_end" label="Fim" error={blocking.errors.ends_at}>
                                    <Input id="block_end" type="time" value={blocking.data.end} onChange={(event) => blocking.setData('end', event.target.value)} required />
                                </Field>
                            </div>
                            <Field id="block_reason" label="Motivo (opcional)" error={blocking.errors.reason}>
                                <Input id="block_reason" value={blocking.data.reason} onChange={(event) => blocking.setData('reason', event.target.value)} maxLength={120} />
                            </Field>
                            <div>
                                <Button type="submit" disabled={blocking.processing} variant="outline">Bloquear</Button>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </>
    );
}

/**
 * A refused reschedule (occupied slot, outside working hours) is answered with a JSON validation error, so the
 * message is shown next to the field and the operator sees why the visit did not move. A saved change reloads the agenda.
 */
function RescheduleForm({ appointmentId, current }: { appointmentId: number; current: string }) {
    const [startsAt, setStartsAt] = useState(current.replace(' ', 'T'));
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setError(null);
        setSending(true);

        try {
            const response = await fetch(reschedule.url({ appointment: appointmentId }), {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ starts_at: startsAt.replace('T', ' ') }),
            });

            if (response.ok) {
                router.reload();

                return;
            }

            if (response.status === 422) {
                const data = (await response.json()) as { errors?: Record<string, string[]>; message?: string };
                setError(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'Não foi possível remarcar.');

                return;
            }

            setError(`Não foi possível remarcar agora (resposta ${response.status}). Tente novamente.`);
        } finally {
            setSending(false);
        }
    };

    return (
        <form className="mt-2 flex flex-wrap items-end gap-2" onSubmit={submit}>
            <Input
                type="datetime-local"
                value={startsAt}
                onChange={(event) => setStartsAt(event.target.value)}
                aria-label="Nova data e horário"
                aria-invalid={error !== null}
                aria-describedby={error ? `reschedule-error-${appointmentId}` : undefined}
                required
                className="h-9 w-auto"
            />
            <Button type="submit" size="sm" variant="outline" disabled={sending}>Confirmar</Button>
            {error && (
                <p id={`reschedule-error-${appointmentId}`} role="alert" className="w-full text-sm text-red-600">
                    {error}
                </p>
            )}
        </form>
    );
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Cancellation needs a written reason. The dialog does not submit until the reason is filled,
 * and the server refuses an empty one too, so no cancellation happens silently.
 */
function CancelAppointment({ appointmentId }: { appointmentId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<Record<string, string>>({ reason: '' });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button type="button" className="font-medium text-red-600 hover:underline">Cancelar</button>
            </DialogTrigger>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(cancelAppointment.url({ appointment: appointmentId }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                setOpen(false);
                                form.reset();
                            },
                        });
                    }}
                    className="grid gap-4"
                >
                    <DialogHeader>
                        <DialogTitle>Cancelar agendamento?</DialogTitle>
                        <DialogDescription>A solicitação volta para a triagem e pode ser agendada novamente. O motivo fica registrado no histórico.</DialogDescription>
                    </DialogHeader>
                    <Field id={`cancel-reason-${appointmentId}`} label="Motivo do cancelamento" error={form.errors.reason}>
                        <textarea
                            id={`cancel-reason-${appointmentId}`}
                            rows={3}
                            maxLength={500}
                            required
                            value={form.data.reason}
                            onChange={(event) => form.setData('reason', event.target.value)}
                            aria-invalid={form.errors.reason ? true : undefined}
                            className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none"
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>Voltar</Button>
                        <Button type="submit" disabled={form.processing || form.data.reason.trim() === ''} className="bg-red-600 text-white hover:bg-red-700">Cancelar agendamento</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
