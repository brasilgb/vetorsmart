import { emailValidationProps } from '@/lib/email-validation';
import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

const fieldClass = 'h-11 border-vs-border';

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Redefinir senha" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-5"
            >
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
                                autoComplete="email"
                                value={email}
                                className={fieldClass}
                                readOnly
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password" className="text-vs-text">
                                Nova senha
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                autoFocus
                                passwordrules={passwordRules}
                                aria-describedby="password-hint"
                                className={fieldClass}
                            />
                            <p id="password-hint" className="text-xs text-vs-muted">
                                Mínimo de 8 caracteres. Pode conter apenas números.
                            </p>
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation" className="text-vs-text">
                                Confirmar nova senha
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                className={fieldClass}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <Button
                            type="submit"
                            className="h-11 w-full bg-vs-blue text-white hover:bg-vs-blue-hover"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Redefinir senha
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Redefinir senha',
    description: 'Crie uma nova senha para acessar sua conta.',
};
