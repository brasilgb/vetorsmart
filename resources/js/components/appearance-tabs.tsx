import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

/**
 * Theme picker. Uses the theme tokens, so every option stays legible in light and dark mode,
 * and exposes the choice as a radio group to assistive technology.
 */
export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Claro' },
        { value: 'dark', icon: Moon, label: 'Escuro' },
        { value: 'system', icon: Monitor, label: 'Sistema' },
    ];

    return (
        <div
            role="radiogroup"
            aria-label="Tema"
            className={cn(
                'inline-flex flex-wrap gap-1 rounded-lg border border-vs-border bg-vs-surface p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => {
                const isSelected = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={isSelected}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'flex items-center rounded-md px-3.5 py-1.5 text-sm transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            isSelected
                                ? 'bg-card font-medium text-vs-text shadow-xs ring-1 ring-vs-border'
                                : 'text-vs-muted hover:bg-card/60 hover:text-vs-text',
                        )}
                    >
                        <Icon aria-hidden="true" className="-ml-1 h-4 w-4" />
                        <span className="ml-1.5">{label}</span>
                    </button>
                );
            })}
        </div>
    );
}
