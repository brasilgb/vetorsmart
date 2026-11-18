import { Head, Link, router, useForm } from '@inertiajs/react';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { open, preferences as savePreferences, read, readAll } from '@/routes/tenant/notifications';
import type { Paginator } from '@/types';

type Row = {
    id: number;
    category: string;
    category_label: string;
    title: string;
    body: string | null;
    has_link: boolean;
    read: boolean;
    created_at: string;
};

type Preference = { key: string; label: string; email: boolean };

type Props = {
    notifications: Paginator<Row>;
    preferences: Preference[];
    customerEmails: boolean | null;
};

export default function NotificationsIndex({ notifications, preferences, customerEmails }: Props) {
    const hasUnread = notifications.data.some((item) => !item.read);
    const form = useForm<{ email: Record<string, boolean>; customer_emails?: boolean }>({
        email: Object.fromEntries(preferences.map((item) => [item.key, item.email])),
        ...(customerEmails === null ? {} : { customer_emails: customerEmails }),
    });

    return (
        <>
            <Head title="Notificações" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Notificações"
                    description="Avisos da sua empresa: novas solicitações, agendamentos, cancelamentos e conversas aguardando atendimento."
                    action={
                        hasUnread ? (
                            <Button type="button" variant="outline" className="h-10" onClick={() => router.post(readAll.url(), {}, { preserveScroll: true })}>
                                Marcar todas como lidas
                            </Button>
                        ) : undefined
                    }
                />

                {notifications.data.length === 0 ? (
                    <EmptyState>Nenhuma notificação por enquanto. Os avisos aparecem aqui assim que algo acontecer na sua empresa.</EmptyState>
                ) : (
                    <ul className="grid gap-3" aria-label="Notificações recentes">
                        {notifications.data.map((item) => (
                            <li key={item.id}>
                                <Card className={`border-vs-border/70 p-4 shadow-sm ${item.read ? '' : 'border-l-4 border-l-vs-blue'}`}>
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="grid min-w-0 gap-1">
                                            <p className="text-xs font-medium text-vs-muted">
                                                {item.category_label} · {item.created_at}
                                                {!item.read && <span className="ml-2 rounded-full bg-vs-blue px-2 py-0.5 text-[11px] font-semibold text-white">Nova</span>}
                                            </p>
                                            <p className="font-medium break-words text-vs-text">{item.title}</p>
                                            {item.body && <p className="text-sm break-words text-vs-muted">{item.body}</p>}
                                        </div>
                                        <div className="flex shrink-0 flex-wrap gap-2">
                                            {item.has_link && (
                                                <Button asChild size="sm" className="bg-vs-blue text-white hover:bg-vs-blue-hover">
                                                    <Link href={open.url({ notification: item.id })} method="post" as="button">
                                                        Abrir
                                                    </Link>
                                                </Button>
                                            )}
                                            {!item.read && (
                                                <Button type="button" size="sm" variant="outline" onClick={() => router.post(read.url({ notification: item.id }), {}, { preserveScroll: true })}>
                                                    Marcar como lida
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination links={notifications.links} />

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Preferências de e-mail</CardTitle>
                        <CardDescription>
                            Os avisos continuam aparecendo aqui no painel. Os avisos da administração da VetorSmart são essenciais e sempre chegam por e-mail. A plataforma não envia propaganda por este canal.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.patch(savePreferences.url(), { preserveScroll: true });
                            }}
                            className="grid gap-4"
                        >
                            <fieldset className="grid gap-3">
                                <legend className="mb-1 text-sm font-medium text-vs-text">Receber por e-mail</legend>
                                {preferences.map((item) => (
                                    <div key={item.key} className="flex items-center gap-3">
                                        <Checkbox
                                            id={`email-${item.key}`}
                                            checked={form.data.email[item.key]}
                                            onCheckedChange={(checked) => form.setData('email', { ...form.data.email, [item.key]: checked === true })}
                                        />
                                        <Label htmlFor={`email-${item.key}`} className="text-vs-text">{item.label}</Label>
                                    </div>
                                ))}
                            </fieldset>

                            {customerEmails !== null && (
                                <fieldset className="grid gap-2 border-t border-vs-border/70 pt-4">
                                    <legend className="mb-1 text-sm font-medium text-vs-text">E-mails aos clientes da empresa</legend>
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="customer-emails"
                                            checked={form.data.customer_emails === true}
                                            onCheckedChange={(checked) => form.setData('customer_emails', checked === true)}
                                        />
                                        <Label htmlFor="customer-emails" className="text-vs-text">Enviar confirmações e avisos de agendamento aos clientes que têm e-mail cadastrado</Label>
                                    </div>
                                    <p className="text-xs text-vs-muted">Vale para toda a empresa. Só proprietários e administradores podem alterar.</p>
                                </fieldset>
                            )}

                            <div>
                                <Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">
                                    Salvar preferências
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
