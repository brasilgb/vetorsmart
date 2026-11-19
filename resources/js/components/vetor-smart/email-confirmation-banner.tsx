import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { send } from '@/routes/verification';

/**
 * Reminder for accounts created before e-mail confirmation (legacy access). The panel stays open; the RootAdmin,
 * billing and team management wait for the confirmation.
 */
export function EmailConfirmationBanner() {
    const { auth } = usePage().props;
    const [sent, setSent] = useState(false);

    if (!auth?.emailConfirmationPending) {
        return null;
    }

    return (
        <div role="status" className="mx-4 mt-4 flex flex-col gap-3 rounded-md border border-amber-500/60 bg-amber-50 px-4 py-3 text-sm text-amber-900 md:mx-6 md:flex-row md:items-center md:justify-between dark:bg-amber-950/40 dark:text-amber-100">
            <p>
                Confirme seu e-mail ({auth.user.email}). O painel continua liberado, mas a gestão da equipe, a assinatura e a administração da
                plataforma exigem a confirmação.
            </p>
            <Form {...send.form()} options={{ preserveScroll: true }} onSuccess={() => setSent(true)}>
                {({ processing }) => (
                    <Button type="submit" size="sm" variant="outline" disabled={processing} className="shrink-0 border-amber-600 bg-transparent text-amber-900 hover:bg-amber-100 dark:text-amber-100 dark:hover:bg-amber-900/40">
                        {sent ? 'Link enviado: verifique a caixa de entrada' : 'Enviar link de confirmação'}
                    </Button>
                )}
            </Form>
        </div>
    );
}
