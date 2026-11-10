import { useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Props = {
    trigger: ReactNode;
    title: string;
    description: string;
    confirmLabel: string;
    onConfirm: () => void;
    destructive?: boolean;
    disabled?: boolean;
};

/**
 * Modal confirmation for every critical action (archive, suspend, remove access).
 * Nothing happens until the operator confirms inside the dialog.
 */
export function ConfirmAction({ trigger, title, description, confirmLabel, onConfirm, destructive = false, disabled = false }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild disabled={disabled}>
                {trigger}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        className={destructive ? '' : 'bg-vs-blue text-white hover:bg-vs-blue-hover'}
                        onClick={() => {
                            onConfirm();
                            setOpen(false);
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
