import { Head } from '@inertiajs/react';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Shown to operators while the company's subscription blocks the panel. Owners and admins go to Minha Assinatura.
 */
export default function SubscriptionBlocked({ status }: { status: string }) {
    return (
        <>
            <Head title="Acesso suspenso" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Card className="max-w-2xl border-vs-border/70 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-vs-text">O painel da empresa está temporariamente bloqueado</CardTitle>
                        <CardDescription>
                            Situação da assinatura: {status}. Fale com o proprietário ou com o administrador da empresa para regularizar o pagamento.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </>
    );
}
