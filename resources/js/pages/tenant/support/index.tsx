import { Head, Link, router, useForm } from '@inertiajs/react';
import { EmptyState } from '@/components/vetor-smart/empty-state';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index as supportIndex, show as supportShow, store as supportStore } from '@/routes/tenant/support';
import type { Paginator } from '@/types';

type Ticket = { id: number; subject: string; category_label: string; status: string; status_label: string; is_final: boolean; next_action: string | null; opened_at: string | null; last_activity_at: string | null };

/** Who has to act next, in the words of the company. */
export const nextActionLabel: Record<string, string> = { team: 'Com a equipe VetorSmart', company: 'Aguardando sua resposta' };

type Props = { filter: string; categories: { value: string; label: string }[]; tickets: Paginator<Ticket> };

export const statusTone: Record<string, string> = {
    open: 'border-vs-blue text-vs-blue dark:border-sky-400 dark:text-sky-300',
    in_progress: 'border-vs-blue text-vs-blue dark:border-sky-400 dark:text-sky-300',
    waiting: 'border-amber-500 text-amber-700 dark:text-amber-400',
    resolved: 'border-emerald-600 text-emerald-700 dark:text-emerald-400',
    closed: 'border-vs-border text-vs-muted',
};

const filters: [string, string][] = [['', 'Todos'], ['abertos', 'Em andamento'], ['finalizados', 'Finalizados']];

/** Central de Suporte of the company: open a request and follow the answers of the VetorSmart team. */
export default function SupportIndex({ filter, categories, tickets }: Props) {
    const form = useForm({ subject: '', category: '', description: '' });

    return (
        <>
            <Head title="Suporte" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Suporte" description="Fale com a equipe VetorSmart: abra um chamado e acompanhe as respostas por aqui." />

                <Card className="border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">Novo chamado</CardTitle>
                        <CardDescription>Descreva o que aconteceu e, se possível, onde no painel. A equipe responde nesta página.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={(event) => { event.preventDefault(); form.post(supportStore.url()); }} className="grid gap-4 md:grid-cols-2">
                            <Field id="subject" label="Assunto" error={form.errors.subject}>
                                <Input id="subject" value={form.data.subject} onChange={(event) => form.setData('subject', event.target.value)} maxLength={160} className="h-10" />
                            </Field>
                            <Field id="category" label="Categoria" error={form.errors.category}>
                                <select id="category" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} className={selectClass}>
                                    <option value="">Escolha uma categoria</option>
                                    {categories.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                </select>
                            </Field>
                            <div className="md:col-span-2">
                                <Field id="description" label="Descrição" error={form.errors.description}>
                                    <textarea id="description" rows={5} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} maxLength={5000} className="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-vs-text" />
                                </Field>
                            </div>
                            <div><Button type="submit" disabled={form.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Abrir chamado</Button></div>
                        </form>
                    </CardContent>
                </Card>

                <section aria-labelledby="meus-chamados" className="grid gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="meus-chamados" className="text-lg font-semibold text-vs-text">Chamados da empresa</h2>
                        <div role="group" aria-label="Filtrar chamados" className="flex flex-wrap gap-2">
                            {filters.map(([value, label]) => (
                                <Button key={value} type="button" size="sm" variant={filter === value ? 'default' : 'outline'} aria-pressed={filter === value} onClick={() => router.get(supportIndex.url(), value === '' ? {} : { situacao: value }, { preserveState: true, replace: true })}>
                                    {label}
                                </Button>
                            ))}
                        </div>
                    </div>

                    {tickets.data.length === 0 ? (
                        <EmptyState>Nenhum chamado por aqui.</EmptyState>
                    ) : (
                        <ul className="grid gap-2">
                            {tickets.data.map((ticket) => (
                                <li key={ticket.id}>
                                    <Card className="border-vs-border/70 p-4 shadow-sm">
                                        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                            <div className="grid gap-1 text-sm">
                                                <Link href={supportShow.url({ ticket: ticket.id })} className="font-medium text-vs-link hover:underline">#{ticket.id} · {ticket.subject}</Link>
                                                <p className="text-vs-muted">{ticket.category_label} · aberto em {ticket.opened_at} · atualizado em {ticket.last_activity_at}</p>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {ticket.next_action && (
                                                    <span className={ticket.next_action === 'company' ? 'text-xs font-medium text-amber-700 dark:text-amber-400' : 'text-xs text-vs-muted'}>{nextActionLabel[ticket.next_action]}</span>
                                                )}
                                                <Badge variant="outline" className={statusTone[ticket.status] ?? ''}>{ticket.status_label}</Badge>
                                            </div>
                                        </div>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pagination links={tickets.links} />
                </section>
            </div>
        </>
    );
}
