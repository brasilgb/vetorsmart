import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthShell from '@/components/vetor-smart/auth-shell';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/two-factor/login';

/**
 * Second step of the sign-in. The code comes from the authenticator app, or a recovery code
 * when the device is lost. Each recovery code works once.
 */
export default function TwoFactorChallenge() {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);

    const form = useForm({
        code: '',
        recovery_code: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => (useRecoveryCode ? { recovery_code: data.recovery_code } : { code: data.code }));

        form.post(store.url(), {
            onFinish: () => form.setData({ code: '', recovery_code: '' }),
        });
    };

    const toggleMode = () => {
        form.clearErrors();
        setUseRecoveryCode((current) => !current);
    };

    return (
        <AuthShell subtitle="Confirme sua identidade para continuar.">
            <Head title="Verificação em dois fatores" />

            <div className="mb-6 grid gap-1 text-center">
                <h1 className="text-xl font-semibold text-vs-text">Verificação em dois fatores</h1>
                <p className="text-sm text-vs-muted">
                    {useRecoveryCode
                        ? 'Informe um dos códigos de recuperação guardados. Cada código funciona uma única vez.'
                        : 'Informe o código de 6 dígitos exibido no seu aplicativo autenticador.'}
                </p>
            </div>

            <form onSubmit={submit} className="grid gap-5">
                {useRecoveryCode ? (
                    <div className="grid gap-2">
                        <Label htmlFor="recovery_code" className="text-vs-text">
                            Código de recuperação
                        </Label>
                        <Input
                            id="recovery_code"
                            name="recovery_code"
                            type="text"
                            autoComplete="one-time-code"
                            autoFocus
                            value={form.data.recovery_code}
                            onChange={(event) => form.setData('recovery_code', event.target.value)}
                            aria-invalid={Boolean(form.errors.recovery_code)}
                            aria-describedby={form.errors.recovery_code ? 'recovery_code-error' : undefined}
                        />
                        <InputError id="recovery_code-error" message={form.errors.recovery_code} />
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="code" className="text-vs-text">
                            Código do aplicativo
                        </Label>
                        <Input
                            id="code"
                            name="code"
                            type="text"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            autoComplete="one-time-code"
                            autoFocus
                            value={form.data.code}
                            onChange={(event) => form.setData('code', event.target.value.replace(/\D/g, ''))}
                            aria-invalid={Boolean(form.errors.code)}
                            aria-describedby={form.errors.code ? 'code-error' : undefined}
                        />
                        <InputError id="code-error" message={form.errors.code} />
                    </div>
                )}

                <Button type="submit" className="w-full bg-vs-blue text-white hover:bg-vs-blue-hover" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Verificar e entrar
                </Button>
            </form>

            <div className="mt-6 text-center text-sm text-vs-muted">
                <button type="button" onClick={toggleMode} className="font-medium text-vs-blue underline-offset-4 hover:underline">
                    {useRecoveryCode ? 'Usar o código do aplicativo' : 'Não tenho o aplicativo: usar código de recuperação'}
                </button>
            </div>
        </AuthShell>
    );
}
