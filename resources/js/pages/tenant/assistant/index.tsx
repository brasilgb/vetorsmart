import type { FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as assistantIndex, update, assume, returnMethod, close, reply, identify } from '@/routes/tenant/assistant';

type Settings = {
    enabled: boolean;
    display_name: string;
    greeting: string;
    presentation: string;
    service_region: string;
    business_hours_text: string;
    commercial_info: string;
    guidance: string;
    handoff_conditions: string;
    retention_days: number;
};

type Row = {
    id: number;
    customer: string | null;
    service: string | null;
    status: string;
    status_label: string;
    state_label: string;
    handled_by: string | null;
    handled_by_me: boolean;
    handoff_reason: string | null;
    summary: string | null;
    identified: boolean;
    request_id: number | null;
    last_activity: string;
};

type Message = { id: number; role: string; role_label: string; body: string; at: string };

type Selected = Row & { messages: Message[]; can_reply: boolean };

type Props = {
    settings: Settings;
    canManage: boolean;
    publicUrl: string | null;
    pendingIdentification: Row[];
    conversations: Row[];
    services: { id: number; name: string }[];
    selected: Selected | null;
    filters: { conversation: number | null };
};

export default function AssistantIndex({ settings, canManage, publicUrl, pendingIdentification, conversations, services, selected }: Props) {
    const form = useForm<Settings>(settings);
    const reply_ = useForm({ body: '' });
    const identifyForm = useForm({ name: '', phone: '', service_id: '' });

    const disabled = !canManage;

    const submitSettings = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, enabled: Boolean(data.enabled) }));
        form.patch(update.url(), { preserveScroll: true });
    };

    const submitReply = (event: FormEvent) => {
        event.preventDefault();

        if (!selected) {
            return;
        }

        reply_.post(reply.url({ conversation: selected.id }), {
            preserveScroll: true,
            onSuccess: () => reply_.reset('body'),
        });
    };

    const submitIdentify = (event: FormEvent) => {
        event.preventDefault();

        if (!selected) {
            return;
        }

        identifyForm.post(identify.url({ conversation: selected.id }), {
            preserveScroll: true,
            onSuccess: () => identifyForm.reset(),
        });
    };

    const act = (url: string) => router.post(url, {}, { preserveScroll: true });

    const renderConversationList = (items: Row[]) => (
        <ul className="divide-y divide-vs-border/70">
            {items.map((item) => (
                <li key={item.id} className="space-y-2 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Link href={assistantIndex.url({ query: { conversation: item.id } })} className="font-medium text-vs-blue hover:underline">
                            Conversa #{item.id} — {item.customer ?? 'Não identificado'}
                        </Link>
                        <span className="text-xs text-vs-muted">{item.last_activity}</span>
                    </div>
                    <p className="text-sm text-vs-muted">
                        {item.status_label} · {item.state_label}{item.service ? ` · ${item.service}` : ''}{item.handled_by ? ` · com ${item.handled_by}` : ''}
                    </p>
                    {item.handoff_reason && <p className="text-xs text-vs-muted">Motivo: {item.handoff_reason}</p>}
                    {item.request_id && <p className="text-xs text-vs-muted">Solicitação #{item.request_id}</p>}
                    <div className="flex flex-wrap gap-2">
                        {item.status === 'awaiting_human' && (
                            <Button type="button" onClick={() => act(assume.url({ conversation: item.id }))} className="h-8 bg-vs-blue text-white hover:bg-vs-blue-hover">Assumir</Button>
                        )}
                        {item.status === 'human_assumed' && item.handled_by_me && (
                            <Button type="button" variant="outline" onClick={() => act(returnMethod.url({ conversation: item.id }))} className="h-8">Devolver à IA</Button>
                        )}
                        {item.status !== 'closed' && (item.handled_by_me || item.status === 'awaiting_human') && (
                            <Button type="button" variant="outline" onClick={() => act(close.url({ conversation: item.id }))} className="h-8">Encerrar</Button>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );

    return (
        <>
            <Head title="Atendimento IA" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Atendimento IA"
                    description="Recepcionista virtual do site da empresa e caixa de entrada dos atendentes."
                />

                <Card className="space-y-2 border-vs-border/70 p-4 shadow-sm">
                    <p className="text-sm text-vs-text">
                        Endereço do atendimento: {publicUrl ? <a className="font-medium text-vs-blue hover:underline" href={publicUrl} target="_blank" rel="noreferrer">{publicUrl}</a> : 'disponível após a empresa ter um domínio verificado e o site publicado.'}
                    </p>
                    <p className="text-xs text-vs-muted">O visitante só agenda por horários reais do cadastro. Desativar a recepcionista mantém os contatos da empresa visíveis na página.</p>
                </Card>

                <Card className="border-vs-border/70 p-4 shadow-sm">
                    <form onSubmit={submitSettings} className="grid gap-4 md:grid-cols-2">
                        <div className="md:col-span-2 flex items-center gap-3">
                            <input id="enabled" type="checkbox" className="size-4 accent-vs-blue" checked={form.data.enabled} disabled={disabled} onChange={(e) => form.setData('enabled', e.target.checked)} />
                            <label htmlFor="enabled" className="text-sm text-vs-text">Recepcionista virtual ativa no site</label>
                        </div>

                        <Field id="display_name" label="Nome exibido no atendimento" error={form.errors.display_name}>
                            <Input id="display_name" value={form.data.display_name} disabled={disabled} onChange={(e) => form.setData('display_name', e.target.value)} className="h-10" />
                        </Field>

                        <Field id="retention_days" label="Dias de retenção das conversas" error={form.errors.retention_days}>
                            <select id="retention_days" value={form.data.retention_days} disabled={disabled} onChange={(e) => form.setData('retention_days', Number(e.target.value))} className={selectClass}>
                                {[7, 30, 90, 180, 365].map((days) => (
                                    <option key={days} value={days}>{days} dias</option>
                                ))}
                            </select>
                        </Field>

                        <Field id="greeting" label="Saudação inicial" error={form.errors.greeting}>
                            <textarea id="greeting" rows={2} value={form.data.greeting} disabled={disabled} onChange={(e) => form.setData('greeting', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                        </Field>

                        <Field id="presentation" label="Apresentação da empresa" error={form.errors.presentation}>
                            <textarea id="presentation" rows={2} value={form.data.presentation} disabled={disabled} onChange={(e) => form.setData('presentation', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                        </Field>

                        <Field id="service_region" label="Região de atendimento" error={form.errors.service_region}>
                            <Input id="service_region" value={form.data.service_region} disabled={disabled} onChange={(e) => form.setData('service_region', e.target.value)} className="h-10" />
                        </Field>

                        <Field id="business_hours_text" label="Horário comercial" error={form.errors.business_hours_text}>
                            <Input id="business_hours_text" value={form.data.business_hours_text} disabled={disabled} onChange={(e) => form.setData('business_hours_text', e.target.value)} className="h-10" />
                        </Field>

                        <Field id="commercial_info" label="Informações comerciais adicionais" error={form.errors.commercial_info}>
                            <textarea id="commercial_info" rows={2} value={form.data.commercial_info} disabled={disabled} onChange={(e) => form.setData('commercial_info', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                        </Field>

                        <Field id="guidance" label="Orientações específicas" error={form.errors.guidance}>
                            <textarea id="guidance" rows={2} value={form.data.guidance} disabled={disabled} onChange={(e) => form.setData('guidance', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                        </Field>

                        <Field id="handoff_conditions" label="Condições para encaminhar a um humano" error={form.errors.handoff_conditions}>
                            <textarea id="handoff_conditions" rows={2} value={form.data.handoff_conditions} disabled={disabled} onChange={(e) => form.setData('handoff_conditions', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                        </Field>

                        {canManage && (
                            <div className="md:col-span-2">
                                <Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar recepcionista</Button>
                            </div>
                        )}
                        {!canManage && <p className="md:col-span-2 text-xs text-vs-muted">Somente leitura: apenas proprietários e administradores alteram a recepcionista.</p>}
                    </form>
                </Card>

                <section aria-labelledby="inbox-title" className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                    <h2 id="inbox-title" className="sr-only">Caixa de entrada do atendimento</h2>

                    <div className="space-y-4">
                        {pendingIdentification.length > 0 && (
                            <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                                <div className="space-y-1 border-b border-vs-border/70 p-4">
                                    <h3 className="font-medium text-vs-text">Pendentes de identificação ({pendingIdentification.length})</h3>
                                    <p className="text-xs text-vs-muted">Atendimentos encaminhados antes de o visitante informar nome e telefone. Identifique o cliente para enviar a solicitação à triagem.</p>
                                </div>
                                {renderConversationList(pendingIdentification)}
                            </Card>
                        )}

                        {conversations.length === 0 && pendingIdentification.length === 0 && (
                            <EmptyState>Nenhuma conversa ainda. Quando um visitante iniciar o atendimento pelo site, ela aparece aqui.</EmptyState>
                        )}

                        {conversations.length > 0 && (
                            <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                                {renderConversationList(conversations)}
                            </Card>
                        )}
                    </div>

                    {selected && (
                        <Card className="space-y-4 border-vs-border/70 p-4 shadow-sm">
                            <div>
                                <h3 className="font-medium text-vs-text">Conversa #{selected.id}</h3>
                                {selected.summary && <p className="mt-1 text-xs text-vs-muted">Resumo: {selected.summary}</p>}
                            </div>

                            <ol className="max-h-[420px] space-y-2 overflow-y-auto" aria-label="Mensagens">
                                {selected.messages.map((message) => (
                                    <li key={message.id} className="rounded-md bg-vs-surface p-3 text-sm">
                                        <span className="block text-xs text-vs-muted">{message.role_label} · {message.at}</span>
                                        <span className="whitespace-pre-line break-words text-vs-text">{message.body}</span>
                                    </li>
                                ))}
                            </ol>

                            {selected.can_reply ? (
                                <form onSubmit={submitReply} className="flex gap-2">
                                    <Input aria-label="Mensagem para o cliente" value={reply_.data.body} onChange={(e) => reply_.setData('body', e.target.value)} className="h-10" />
                                    <Button type="submit" disabled={reply_.processing || reply_.data.body.trim() === ''} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Enviar</Button>
                                </form>
                            ) : (
                                <p className="text-xs text-vs-muted">Assuma a conversa para responder ao cliente. Enquanto um atendente conduz, a recepcionista virtual não responde.</p>
                            )}
                            {reply_.errors.body && <p className="text-xs text-red-700">{reply_.errors.body}</p>}

                            {!selected.identified && selected.status !== 'closed' && (
                                <form onSubmit={submitIdentify} className="space-y-3 rounded-md border border-vs-border/70 p-3">
                                    <div>
                                        <p className="text-sm font-medium text-vs-text">Identificar cliente e enviar à triagem</p>
                                        <p className="text-xs text-vs-muted">O histórico da conversa fica preservado. A solicitação aparece na triagem com o resumo do atendimento.</p>
                                    </div>
                                    <Field id="identify_name" label="Nome do cliente" error={identifyForm.errors.name}>
                                        <Input id="identify_name" value={identifyForm.data.name} onChange={(e) => identifyForm.setData('name', e.target.value)} className="h-10" />
                                    </Field>
                                    <Field id="identify_phone" label="Telefone com DDD" error={identifyForm.errors.phone}>
                                        <Input id="identify_phone" value={identifyForm.data.phone} onChange={(e) => identifyForm.setData('phone', e.target.value)} className="h-10" />
                                    </Field>
                                    <Field id="identify_service" label="Serviço (opcional)" error={identifyForm.errors.service_id}>
                                        <select id="identify_service" value={identifyForm.data.service_id} onChange={(e) => identifyForm.setData('service_id', e.target.value)} className={selectClass}>
                                            <option value="">Manter o serviço da conversa ou definir na triagem</option>
                                            {services.map((service) => (
                                                <option key={service.id} value={service.id}>{service.name}</option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Button type="submit" disabled={identifyForm.processing || identifyForm.data.name.trim() === '' || identifyForm.data.phone.trim() === ''} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Identificar e enviar à triagem</Button>
                                </form>
                            )}

                            {selected.request_id && <p className="text-xs text-vs-muted">Solicitação #{selected.request_id} registrada na triagem.</p>}
                        </Card>
                    )}
                </section>
            </div>
        </>
    );
}
