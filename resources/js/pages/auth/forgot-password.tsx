import { emailValidationProps } from '@/lib/email-validation';
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Recuperar senha" />

            {status && (
                <p role="status" className="mb-4 rounded-md bg-vs-surface px-3 py-2 text-center text-sm text-vs-text">
                    {status}
                </p>
            )}

            <Form {...email.form()} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email" className="text-vs-text">
                                E-mail
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                {...emailValidationProps}
                                name="email"
                                required
                                autoComplete="email"
                                autoFocus
                                placeholder="voce@empresa.com.br"
                                className="h-11 border-vs-border"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            className="h-11 w-full bg-vs-blue text-white hover:bg-vs-blue-hover"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            Enviar link de redefinição
                        </Button>
                    </>
                )}
            </Form>

            <p className="mt-6 text-center text-sm text-vs-muted">
                Lembrou a senha?{' '}
                <TextLink href={login()} className="font-medium text-vs-blue">
                    Voltar para o login
                </TextLink>
            </p>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Recuperar senha',
    description: 'Informe seu e-mail para receber o link de redefinição de senha.',
};
