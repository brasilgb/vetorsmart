import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

/**
 * Shown while the e-mail of the account is not confirmed. The private areas stay closed until the link is opened.
 */
export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Confirmar e-mail" />

            {status === 'verification-link-sent' && (
                <p role="status" className="mb-4 rounded-md bg-vs-surface px-3 py-2 text-center text-sm text-vs-text">
                    Enviamos um novo link de confirmação para o seu e-mail.
                </p>
            )}

            <Form {...send.form()} className="flex flex-col gap-5">
                {({ processing }) => (
                    <Button
                        className="h-11 w-full bg-vs-blue text-white hover:bg-vs-blue-hover"
                        disabled={processing}
                        data-test="resend-verification-button"
                    >
                        {processing && <Spinner />}
                        Reenviar e-mail de confirmação
                    </Button>
                )}
            </Form>

            <p className="mt-6 text-center text-sm text-vs-muted">
                <TextLink href={logout()} as="button" className="font-medium text-vs-blue">
                    Sair
                </TextLink>
            </p>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Confirme seu e-mail',
    description: 'Enviamos um link para o e-mail cadastrado. Abra o link para liberar o acesso ao painel.',
};
