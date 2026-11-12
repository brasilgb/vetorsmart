import { emailValidationProps } from '@/lib/email-validation';
import { Head, router, useForm } from '@inertiajs/react';
import { ConfirmAction } from '@/components/vetor-smart/confirm-action';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Pagination } from '@/components/vetor-smart/pagination';
import { StatusBadge } from '@/components/vetor-smart/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { store, update, activate, deactivate } from '@/routes/tenant/team';
import type { Paginator } from '@/types';

type Member = {
    id: number;
    name: string | null;
    email: string | null;
    role: string;
    status: string;
    is_self: boolean;
};

type Props = {
    memberships: Paginator<Member>;
    roles: { value: string; label: string }[];
};

const roleLabel: Record<string, string> = { owner: 'Proprietário', admin: 'Administrador', operator: 'Operador' };

/**
 * Members of the active company. The server decides every permission; the screen
 * only offers the actions the current user may take.
 */
export default function TeamIndex({ memberships, roles }: Props) {
    const invite = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'operator' });

    const submitInvite = (event: React.FormEvent) => {
        event.preventDefault();
        invite.post(store.url(), { preserveScroll: true, onSuccess: () => invite.reset('name', 'email', 'password', 'password_confirmation') });
    };

    return (
        <>
            <Head title="Usuários e permissões" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Usuários e permissões" description="Quem acessa a empresa e o que cada perfil pode fazer." />

                <Card className="grid gap-4 border-vs-border/70 p-6 shadow-sm">
                    <h2 className="text-base font-semibold text-vs-text">Adicionar usuário</h2>
                    <form onSubmit={submitInvite} className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Field id="invite-name" label="Nome" error={invite.errors.name}>
                            <Input id="invite-name" value={invite.data.name} onChange={(e) => invite.setData('name', e.target.value)} required className="h-10" />
                        </Field>
                        <Field id="invite-email" label="E-mail" error={invite.errors.email}>
                            <Input id="invite-email" type="email" {...emailValidationProps} value={invite.data.email} onChange={(e) => invite.setData('email', e.target.value)} required className="h-10" />
                        </Field>
                        <Field id="invite-role" label="Perfil" error={invite.errors.role}>
                            <select id="invite-role" value={invite.data.role} onChange={(e) => invite.setData('role', e.target.value)} className={selectClass}>
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>{role.label}</option>
                                ))}
                            </select>
                        </Field>
                        <Field id="invite-password" label="Senha inicial" error={invite.errors.password} hint="Entregue ao usuário por um canal seguro. Não há envio de e-mail.">
                            <Input id="invite-password" type="password" value={invite.data.password} onChange={(e) => invite.setData('password', e.target.value)} required autoComplete="new-password" className="h-10" />
                        </Field>
                        <Field id="invite-password-confirmation" label="Confirmar senha">
                            <Input id="invite-password-confirmation" type="password" value={invite.data.password_confirmation} onChange={(e) => invite.setData('password_confirmation', e.target.value)} required autoComplete="new-password" className="h-10" />
                        </Field>
                        <div className="flex items-end">
                            <Button type="submit" disabled={invite.processing} className="h-10 w-full bg-vs-blue text-white hover:bg-vs-blue-hover">Adicionar</Button>
                        </div>
                    </form>
                </Card>

                <Card className="overflow-hidden border-vs-border/70 shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-vs-surface text-vs-muted">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">E-mail</th>
                                    <th className="px-4 py-3 font-medium">Perfil</th>
                                    <th className="px-4 py-3 font-medium">Acesso</th>
                                    <th className="px-4 py-3 font-medium"><span className="sr-only">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                {memberships.data.map((member) => (
                                    <tr key={member.id} className="border-t border-vs-border/70">
                                        <td className="px-4 py-3 font-medium text-vs-text">{member.name}{member.is_self && <span className="ml-2 text-xs text-vs-muted">(você)</span>}</td>
                                        <td className="px-4 py-3 text-vs-muted">{member.email}</td>
                                        <td className="px-4 py-3">
                                            <select
                                                aria-label={`Perfil de ${member.name}`}
                                                value={member.role}
                                                disabled={member.is_self}
                                                onChange={(e) => router.patch(update.url({ membership: member.id }), { role: e.target.value }, { preserveScroll: true })}
                                                className="h-9 rounded-md border border-vs-border bg-white px-2 text-sm text-vs-text disabled:opacity-60"
                                            >
                                                {roles.map((role) => (
                                                    <option key={role.value} value={role.value}>{roleLabel[role.value] ?? role.label}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-4 py-3"><StatusBadge active={member.status === 'active'} activeLabel="Ativo" inactiveLabel="Suspenso" /></td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            {member.status === 'active' ? (
                                                <ConfirmAction
                                                    trigger={<button type="button" className="font-medium text-red-600 hover:underline" disabled={member.is_self}>Suspender acesso</button>}
                                                    title="Suspender acesso?"
                                                    description="A pessoa deixa de acessar esta empresa até ser reativada. A conta dela não é apagada."
                                                    confirmLabel="Suspender"
                                                    destructive
                                                    onConfirm={() => router.delete(deactivate.url({ membership: member.id }), { preserveScroll: true })}
                                                />
                                            ) : (
                                                <button type="button" onClick={() => router.post(activate.url({ membership: member.id }), {}, { preserveScroll: true })} className="font-medium text-vs-blue hover:underline">
                                                    Reativar acesso
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Pagination links={memberships.links} />
            </div>
        </>
    );
}
