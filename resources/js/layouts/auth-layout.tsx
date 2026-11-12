import AuthShell from '@/components/vetor-smart/auth-shell';

/**
 * Layout of the secondary authentication screens (password recovery, reset and
 * confirmation). Same frame as login and registration, with the page title in the card.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthShell subtitle="Atendimento inteligente para empresas de serviços.">
            {(title || description) && (
                <div className="mb-6 grid gap-1 text-center">
                    {title && (
                        <h2 className="text-xl font-semibold text-vs-text">
                            {title}
                        </h2>
                    )}
                    {description && (
                        <p className="text-sm text-vs-muted">{description}</p>
                    )}
                </div>
            )}

            {children}
        </AuthShell>
    );
}
