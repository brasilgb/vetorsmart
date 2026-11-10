import { Badge } from '@/components/ui/badge';

export function StatusBadge({ active, activeLabel = 'Ativo', inactiveLabel = 'Inativo' }: { active: boolean; activeLabel?: string; inactiveLabel?: string }) {
    return <Badge variant={active ? 'default' : 'secondary'}>{active ? activeLabel : inactiveLabel}</Badge>;
}
