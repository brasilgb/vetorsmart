import { Form, Head } from '@inertiajs/react';
import AuthShell from '@/components/vetor-smart/auth-shell';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';

type Props = {
    status?: string;
};

/**
 * Universal sign-in for RootAdmin, owners, admins and operators. The server decides
 * the destination by profile, so the same form serves every role.
 */
export default function Login({ status }: Props) {
    return (
        <AuthShell subtitle="Atendimento inteligente para empresas de serviços.">
            <Head title="Entrar" />

            {status && (
                <p className="mb-4 rounded-md bg-vs-surface px-3 py-2 text-sm text-vs-muted">
                    {status}
                </p>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
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
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder="voce@empresa.com.br"
                                className="h-11 border-vs-border"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password" className="text-vs-text">
                                Senha
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                placeholder="Sua senha"
                                className="h-11 border-vs-border"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember" className="text-vs-muted">
                                Lembrar acesso
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="login-button"
                            className="h-11 w-full bg-vs-blue text-white hover:bg-vs-blue-hover"
                        >
                            {processing && <Spinner />}
                            Entrar
                        </Button>

                        <p className="text-center text-sm text-vs-muted">
                            Ainda não tem conta?{' '}
                            <TextLink href={register()} className="font-medium text-vs-blue">
                                Criar minha empresa
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthShell>
    );
}
