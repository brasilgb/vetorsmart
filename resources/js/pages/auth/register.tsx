import { emailValidationProps } from '@/lib/email-validation';
import { Form, Head } from '@inertiajs/react';
import AuthShell from '@/components/vetor-smart/auth-shell';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Segment = {
    id: number;
    name: string;
};

type Props = {
    passwordRules: string;
    segments: Segment[];
};

const fieldClass = 'h-10 border-vs-border';

/**
 * Company and owner in one screen. Only what the account needs is requested: no
 * document, address, phone or payment data. Those can be completed later.
 */
export default function Register({ passwordRules, segments }: Props) {
    return (
        <AuthShell subtitle="Crie sua empresa em poucos segundos.">
            <Head title="Criar minha empresa" />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="company_name" className="text-vs-text">
                                Nome da empresa
                            </Label>
                            <Input
                                id="company_name"
                                name="company_name"
                                type="text"
                                required
                                autoFocus
                                autoComplete="organization"
                                placeholder="Ex.: Clima Total"
                                className={fieldClass}
                            />
                            <InputError message={errors.company_name} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="segment_id" className="text-vs-text">
                                Segmento de atuação
                            </Label>
                            <select
                                id="segment_id"
                                name="segment_id"
                                required
                                defaultValue=""
                                className="h-10 w-full rounded-md border border-vs-border bg-white px-3 text-sm text-vs-text focus-visible:ring-[3px] focus-visible:ring-vs-blue/30 focus-visible:outline-none"
                            >
                                <option value="" disabled>
                                    Selecione um segmento
                                </option>
                                {segments.map((segment) => (
                                    <option key={segment.id} value={segment.id}>
                                        {segment.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.segment_id} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="name" className="text-vs-text">
                                Seu nome
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                required
                                autoComplete="name"
                                placeholder="Nome do responsável"
                                className={fieldClass}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="email" className="text-vs-text">
                                E-mail
                            </Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                {...emailValidationProps}
                                required
                                autoComplete="email"
                                placeholder="voce@empresa.com.br"
                                className={fieldClass}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="password" className="text-vs-text">
                                    Senha
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    aria-describedby="password-hint"
                                    className={fieldClass}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password_confirmation" className="text-vs-text">
                                    Confirmar senha
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
                        </div>

                        <p id="password-hint" className="-mt-2 text-xs text-vs-muted">
                            Senha com no mínimo 8 caracteres. Pode conter apenas números.
                        </p>

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="register-user-button"
                            className="mt-1 h-11 w-full bg-vs-blue text-white hover:bg-vs-blue-hover"
                        >
                            {processing && <Spinner />}
                            Criar minha empresa
                        </Button>

                        <p className="text-center text-sm text-vs-muted">
                            Já tem uma conta?{' '}
                            <TextLink href={login()} className="font-medium text-vs-blue">
                                Entrar
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthShell>
    );
}
