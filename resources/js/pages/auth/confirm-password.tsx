import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar senha" />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password" className="text-vs-text">
                                Senha
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Senha"
                                autoComplete="current-password"
                                autoFocus
                                className="h-11 border-vs-border"
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="h-11 w-full bg-vs-blue text-white hover:bg-vs-blue-hover"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                Confirmar senha
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Confirmar senha',
    description:
        'Esta é uma área segura da aplicação. Confirme sua senha para continuar.',
};
