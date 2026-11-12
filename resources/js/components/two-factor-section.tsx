import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirm, disable, enable, qrCode, recoveryCodes, regenerateRecoveryCodes, secretKey } from '@/routes/two-factor';

type Props = {
    confirmed: boolean;
    pending: boolean;
    required: boolean;
};

type Setup = { qr: string | null; secret: string | null };

/**
 * Enrollment, confirmation, recovery codes and revocation of the TOTP factor.
 * Secrets are fetched on demand from the server and are never stored in the page props.
 */
export default function TwoFactorSection({ confirmed, pending, required }: Props) {
    const [setup, setSetup] = useState<Setup>({ qr: null, secret: null });
    const [codes, setCodes] = useState<string[] | null>(null);
    const [loadingSetup, setLoadingSetup] = useState(false);
    const [confirming, setConfirming] = useState(false);

    const confirmForm = useForm({ code: '' });

    const loadSetup = async () => {
        setLoadingSetup(true);

        try {
            const [qrResponse, secretResponse] = await Promise.all([
                fetch(qrCode.url(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
                fetch(secretKey.url(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
            ]);

            const qrData = (await qrResponse.json()) as { svg: string };
            const secretData = (await secretResponse.json()) as { secretKey: string };

            setSetup({
                qr: `data:image/svg+xml;base64,${btoa(qrData.svg)}`,
                secret: secretData.secretKey,
            });
        } finally {
            setLoadingSetup(false);
        }
    };

    const loadRecoveryCodes = async () => {
        const response = await fetch(recoveryCodes.url(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        setCodes((await response.json()) as string[]);
    };

    const regenerate = () => {
        router.post(regenerateRecoveryCodes.url(), {}, {
            preserveScroll: true,
            onSuccess: () => loadRecoveryCodes(),
        });
    };

    /**
     * Fortify reports a wrong code in a named error bag, which Inertia does not share with the page. A JSON request
     * receives the same validation error as `errors.code`, so the message can be shown next to the field.
     */
    const submitConfirmation = async (event: React.FormEvent) => {
        event.preventDefault();
        confirmForm.clearErrors();
        setConfirming(true);

        try {
            const response = await fetch(confirm.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ code: confirmForm.data.code }),
            });

            if (response.ok) {
                confirmForm.reset();
                setSetup({ qr: null, secret: null });
                router.reload();

                return;
            }

            if (response.status === 422) {
                const data = (await response.json()) as { errors?: { code?: string[] } };
                confirmForm.setError('code', data.errors?.code?.[0] ?? 'O código informado é inválido.');

                return;
            }

            confirmForm.setError('code', 'Não foi possível confirmar agora. Tente novamente.');
        } finally {
            setConfirming(false);
        }
    };

    const bagError = confirmForm.errors.code;

    return (
        <section aria-labelledby="two-factor-heading" className="space-y-4 rounded-lg border border-vs-border/70 p-4">
            {required && !confirmed && (
                <p role="status" className="rounded-md bg-vs-surface px-3 py-2 text-sm font-medium text-vs-text">
                    Configure a autenticação em dois fatores para acessar a administração da plataforma.
                </p>
            )}
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="two-factor-heading" className="text-base font-semibold text-vs-text">
                        Autenticação em dois fatores
                    </h2>
                    <p className="text-sm text-vs-muted">
                        {required
                            ? 'Obrigatória para administradores da plataforma. Sem ela, o acesso à administração permanece bloqueado.'
                            : 'Recomendada. Exige um código do aplicativo autenticador ao entrar.'}
                    </p>
                </div>
                <Badge variant={confirmed ? 'default' : 'secondary'}>{confirmed ? 'Ativa' : pending ? 'Aguardando confirmação' : 'Inativa'}</Badge>
            </div>

            {!confirmed && !pending && (
                <Button type="button" className="bg-vs-blue text-white hover:bg-vs-blue-hover" onClick={() => router.post(enable.url(), {}, { preserveScroll: true })}>
                    Configurar aplicativo autenticador
                </Button>
            )}

            {pending && (
                <div className="space-y-4">
                    {setup.qr === null ? (
                        <Button type="button" variant="outline" onClick={loadSetup} disabled={loadingSetup}>
                            {loadingSetup ? 'Carregando…' : 'Exibir QR Code e chave'}
                        </Button>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-[auto_1fr] sm:items-start">
                            <img src={setup.qr} alt="QR Code para cadastrar no aplicativo autenticador" width={180} height={180} className="rounded-md bg-white p-2" />
                            <div className="grid gap-2 text-sm">
                                <p className="text-vs-text">Leia o QR Code com o aplicativo autenticador. Se preferir, digite a chave manualmente:</p>
                                <code className="break-all rounded-md bg-vs-surface px-3 py-2 font-mono text-vs-text">{setup.secret}</code>
                            </div>
                        </div>
                    )}

                    <form onSubmit={submitConfirmation} className="grid max-w-sm gap-2">
                        <Label htmlFor="two-factor-code" className="text-vs-text">
                            Código de 6 dígitos do aplicativo
                        </Label>
                        <Input
                            id="two-factor-code"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            autoComplete="one-time-code"
                            value={confirmForm.data.code}
                            onChange={(event) => confirmForm.setData('code', event.target.value.replace(/\D/g, ''))}
                            aria-invalid={Boolean(bagError)}
                            aria-describedby={bagError ? 'two-factor-code-error' : undefined}
                        />
                        <InputError id="two-factor-code-error" message={bagError} />
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={confirming || confirmForm.data.code.length !== 6}>
                                Confirmar e ativar
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => router.delete(disable.url(), { preserveScroll: true })}
                            >
                                Cancelar configuração
                            </Button>
                        </div>
                    </form>
                </div>
            )}

            {confirmed && (
                <div className="space-y-4">
                    <div className="space-y-2">
                        <h3 className="text-sm font-medium text-vs-text">Códigos de recuperação</h3>
                        <p className="text-sm text-vs-muted">
                            Use-os somente se perder o aplicativo. Cada código funciona uma única vez. Guarde-os em local seguro.
                        </p>
                        {codes === null ? (
                            <Button type="button" variant="outline" onClick={loadRecoveryCodes}>
                                Exibir códigos
                            </Button>
                        ) : (
                            <ul className="grid grid-cols-1 gap-1 rounded-md bg-vs-surface p-3 font-mono text-sm text-vs-text sm:grid-cols-2">
                                {codes.map((code) => (
                                    <li key={code}>{code}</li>
                                ))}
                            </ul>
                        )}
                        <Button type="button" variant="outline" onClick={regenerate}>
                            Gerar novos códigos
                        </Button>
                    </div>

                    <div className="space-y-2 border-t border-vs-border/70 pt-4">
                        <h3 className="text-sm font-medium text-vs-text">Desativar</h3>
                        <p className="text-sm text-vs-muted">
                            {required
                                ? 'Ao desativar, o acesso à administração fica bloqueado até nova configuração.'
                                : 'Ao desativar, o login volta a exigir apenas a senha.'}
                        </p>
                        <Button type="button" variant="destructive" onClick={() => router.delete(disable.url(), { preserveScroll: true })}>
                            Desativar autenticação em dois fatores
                        </Button>
                    </div>
                </div>
            )}
        </section>
    );
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
