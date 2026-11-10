import { Head, router, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as segmentsStore, update as segmentsUpdate } from '@/routes/rootadmin/segments';

type Segment = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    active: boolean;
    tenants: number;
};

type Props = {
    segments: Segment[];
};

export default function SegmentsIndex({ segments }: Props) {
    const create = useForm({ name: '', description: '', active: true });

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        create.post(segmentsStore.url(), { preserveScroll: true, onSuccess: () => create.reset() });
    };

    return (
        <>
            <Head title="Segmentos" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-vs-text">Segmentos</h1>
                    <p className="text-sm text-vs-muted">
                        Segmentos não são excluídos. Desative os que não devem mais ser escolhidos no cadastro.
                    </p>
                </div>

                <Card className="border-vs-border/70 p-6 shadow-sm">
                    <form onSubmit={submitCreate} className="grid gap-4 md:grid-cols-[1fr_2fr_auto] md:items-end">
                        <div className="grid gap-1.5">
                            <Label htmlFor="new-name" className="text-vs-text">Nome</Label>
                            <Input id="new-name" value={create.data.name} onChange={(event) => create.setData('name', event.target.value)} required className="h-10" />
                            {create.errors.name && <p className="text-sm text-red-600">{create.errors.name}</p>}
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="new-description" className="text-vs-text">Descrição</Label>
                            <Input id="new-description" value={create.data.description} onChange={(event) => create.setData('description', event.target.value)} className="h-10" />
                        </div>
                        <Button type="submit" disabled={create.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">
                            Cadastrar segmento
                        </Button>
                    </form>
                </Card>

                <div className="grid gap-4">
                    {segments.map((segment) => (
                        <SegmentRow key={segment.id} segment={segment} />
                    ))}
                </div>
            </div>
        </>
    );
}

function SegmentRow({ segment }: { segment: Segment }) {
    const form = useForm({
        name: segment.name,
        description: segment.description ?? '',
        active: segment.active,
    });

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(segmentsUpdate.url({ segment: segment.id }), { preserveScroll: true });
    };

    const toggle = () => {
        router.patch(
            segmentsUpdate.url({ segment: segment.id }),
            { name: form.data.name, description: form.data.description, active: !form.data.active },
            { preserveScroll: true },
        );
    };

    return (
        <Card className="border-vs-border/70 p-6 shadow-sm">
            <CardHeader className="p-0 pb-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle className="text-vs-text">{segment.name}</CardTitle>
                    <div className="flex items-center gap-3">
                        <CardDescription>{segment.tenants} empresa(s)</CardDescription>
                        <Badge variant={segment.active ? 'default' : 'secondary'}>{segment.active ? 'Ativo' : 'Inativo'}</Badge>
                    </div>
                </div>
            </CardHeader>
            <form onSubmit={save} className="grid gap-4 md:grid-cols-[1fr_2fr_auto_auto] md:items-end">
                <div className="grid gap-1.5">
                    <Label htmlFor={`name-${segment.id}`} className="text-vs-text">Nome</Label>
                    <Input id={`name-${segment.id}`} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required className="h-10" />
                    {form.errors.name && <p className="text-sm text-red-600">{form.errors.name}</p>}
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`description-${segment.id}`} className="text-vs-text">Descrição</Label>
                    <Input id={`description-${segment.id}`} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="h-10" />
                </div>
                <Button type="submit" variant="outline" disabled={form.processing} className="h-10">Salvar</Button>
                <Button type="button" variant="outline" onClick={toggle} disabled={form.processing} className="h-10">
                    {segment.active ? 'Desativar' : 'Ativar'}
                </Button>
            </form>
        </Card>
    );
}
