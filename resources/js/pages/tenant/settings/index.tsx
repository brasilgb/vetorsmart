import { Head, useForm } from '@inertiajs/react';
import { Field, selectClass } from '@/components/vetor-smart/field';
import { PageHeader } from '@/components/vetor-smart/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/tenant/settings';

type DayHours = { open: string; close: string; breaks: { start: string; end: string }[] } | null;

type Hours = Record<string, DayHours> | null;

type Settings = {
    business_name: string;
    description: string | null;
    segment_id: number | null;
    phone: string | null;
    timezone: string;
    whatsapp: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    locale: string;
    brand_color: string;
    business_hours: Hours;
    service_modes: string[];
    welcome_message: string | null;
    confirmation_policy: string;
    currency: string;
    date_format: string;
};

type Props = {
    settings: Settings;
    segments: { id: number; name: string }[];
    canEdit: boolean;
};

const brandColors = [
    { value: '#0F172A', label: 'Azul escuro' },
    { value: '#1E3A5F', label: 'Azul petróleo' },
    { value: '#2563EB', label: 'Azul tecnológico' },
    { value: '#06B6D4', label: 'Cyan' },
];

const weekdays = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const modes = [
    { value: 'in_person', label: 'Presencial' },
    { value: 'remote', label: 'Remoto' },
    { value: 'customer_location', label: 'No local do cliente' },
];

/**
 * Company settings in four sections. Each section saves only its own fields. The
 * server enforces permissions too; the read-only mode just reflects them.
 */
export default function SettingsIndex({ settings, segments, canEdit }: Props) {
    const company = useForm({
        business_name: settings.business_name,
        description: settings.description ?? '',
        segment_id: settings.segment_id ? String(settings.segment_id) : '',
        phone: settings.phone ?? '',
        whatsapp: settings.whatsapp ?? '',
        address: settings.address ?? '',
        city: settings.city ?? '',
        state: settings.state ?? '',
    });

    const identity = useForm({ brand_color: settings.brand_color });

    const days = Array.from({ length: 7 }, (_, weekday) => {
        const day = settings.business_hours?.[String(weekday)] ?? null;

        return { open: day !== null, from: day?.open ?? '08:00', to: day?.close ?? '18:00' };
    });

    const hours = useForm<{ business_hours: Record<string, DayHours> }>({
        business_hours: Object.fromEntries(days.map((day, weekday) => [weekday, day.open ? { open: day.from, close: day.to, breaks: [] } : null])) as Record<string, DayHours>,
    });

    const preferences = useForm({
        timezone: settings.timezone,
        locale: settings.locale,
        currency: settings.currency,
        date_format: settings.date_format,
        service_modes: settings.service_modes,
        confirmation_policy: settings.confirmation_policy,
        welcome_message: settings.welcome_message ?? '',
    });

    const toggleMode = (value: string) => {
        preferences.setData('service_modes', preferences.data.service_modes.includes(value) ? preferences.data.service_modes.filter((m) => m !== value) : [...preferences.data.service_modes, value]);
    };

    const setDay = (weekday: number, patch: { open?: boolean; from?: string; to?: string }) => {
        const current = days[weekday];
        const next = { ...current, ...patch };

        hours.setData('business_hours', {
            ...hours.data.business_hours,
            [weekday]: next.open ? { open: next.from, close: next.to, breaks: [] } : null,
        });
    };

    const disabled = !canEdit;

    return (
        <>
            <Head title="Configurações" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Configurações da empresa" description={canEdit ? 'Cada seção é salva separadamente.' : 'Somente leitura: apenas proprietários e administradores podem alterar.'} />

                <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                    <h2 className="text-base font-semibold text-vs-text">Dados da empresa</h2>
                    <form onSubmit={(e) => { e.preventDefault(); company.patch(update.url()); }} className="grid gap-5 md:grid-cols-2">
                        <Field id="business_name" label="Nome público" error={company.errors.business_name}>
                            <Input id="business_name" value={company.data.business_name} disabled={disabled} onChange={(e) => company.setData('business_name', e.target.value)} className="h-10" />
                        </Field>
                        <Field id="segment_id" label="Segmento" error={company.errors.segment_id}>
                            <select id="segment_id" value={company.data.segment_id} disabled={disabled} onChange={(e) => company.setData('segment_id', e.target.value)} className={selectClass}>
                                <option value="">Sem segmento</option>
                                {segments.map((segment) => (
                                    <option key={segment.id} value={segment.id}>{segment.name}</option>
                                ))}
                            </select>
                        </Field>
                        <div className="md:col-span-2">
                            <Field id="description" label="Descrição da atividade" error={company.errors.description}>
                                <textarea id="description" rows={2} value={company.data.description} disabled={disabled} onChange={(e) => company.setData('description', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                            </Field>
                        </div>
                        <Field id="phone" label="Telefone de atendimento" error={company.errors.phone}>
                            <Input id="phone" value={company.data.phone} disabled={disabled} onChange={(e) => company.setData('phone', e.target.value)} className="h-10" />
                        </Field>
                        <Field id="whatsapp" label="WhatsApp" error={company.errors.whatsapp}>
                            <Input id="whatsapp" value={company.data.whatsapp} disabled={disabled} onChange={(e) => company.setData('whatsapp', e.target.value)} className="h-10" />
                        </Field>
                        <div className="md:col-span-2">
                            <Field id="address" label="Endereço" error={company.errors.address}>
                                <Input id="address" value={company.data.address} disabled={disabled} onChange={(e) => company.setData('address', e.target.value)} className="h-10" />
                            </Field>
                        </div>
                        <Field id="city" label="Cidade" error={company.errors.city}>
                            <Input id="city" value={company.data.city} disabled={disabled} onChange={(e) => company.setData('city', e.target.value)} className="h-10" />
                        </Field>
                        <Field id="state" label="UF" error={company.errors.state}>
                            <Input id="state" maxLength={2} value={company.data.state} disabled={disabled} onChange={(e) => company.setData('state', e.target.value.toUpperCase())} className="h-10" />
                        </Field>
                        {canEdit && <div className="md:col-span-2"><Button type="submit" disabled={company.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar dados da empresa</Button></div>}
                    </form>
                </Card>

                <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                    <h2 className="text-base font-semibold text-vs-text">Identidade visual</h2>
                    <p className="text-sm text-vs-muted">Escolha uma cor da paleta VetorSmart. Logotipo próprio ainda não está disponível.</p>
                    <form onSubmit={(e) => { e.preventDefault(); identity.patch(update.url()); }} className="grid gap-4">
                        <div className="flex flex-wrap gap-3" role="radiogroup" aria-label="Cor da marca">
                            {brandColors.map((color) => (
                                <label key={color.value} className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm ${identity.data.brand_color === color.value ? 'border-vs-blue ring-2 ring-vs-blue/30' : 'border-vs-border'}`}>
                                    <input type="radio" name="brand_color" value={color.value} checked={identity.data.brand_color === color.value} disabled={disabled} onChange={() => identity.setData('brand_color', color.value)} className="sr-only" />
                                    <span className="size-5 rounded-full border border-vs-border" style={{ backgroundColor: color.value }} aria-hidden="true" />
                                    {color.label}
                                </label>
                            ))}
                        </div>
                        {canEdit && <div><Button type="submit" disabled={identity.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar identidade</Button></div>}
                    </form>
                </Card>

                <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                    <h2 className="text-base font-semibold text-vs-text">Horários de funcionamento</h2>
                    <form onSubmit={(e) => { e.preventDefault(); hours.patch(update.url()); }} className="grid gap-3">
                        {weekdays.map((name, weekday) => (
                            <div key={weekday} className="grid items-center gap-3 sm:grid-cols-[160px_1fr_auto_1fr]">
                                <label className="flex items-center gap-2 text-sm text-vs-text">
                                    <input type="checkbox" checked={days[weekday].open} disabled={disabled} onChange={(e) => setDay(weekday, { open: e.target.checked })} className="size-4 accent-vs-blue" />
                                    {name}
                                </label>
                                <Input aria-label={`Abertura ${name}`} type="time" value={days[weekday].from} disabled={disabled || !days[weekday].open} onChange={(e) => setDay(weekday, { from: e.target.value })} className="h-9" />
                                <span className="text-center text-sm text-vs-muted">às</span>
                                <Input aria-label={`Fechamento ${name}`} type="time" value={days[weekday].to} disabled={disabled || !days[weekday].open} onChange={(e) => setDay(weekday, { to: e.target.value })} className="h-9" />
                            </div>
                        ))}
                        {(hours.errors as Record<string, string | undefined>).business_hours && <p className="text-sm text-red-600">{hours.errors.business_hours}</p>}
                        {canEdit && <div><Button type="submit" disabled={hours.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar horários</Button></div>}
                    </form>
                </Card>

                <Card className="grid gap-5 border-vs-border/70 p-6 shadow-sm">
                    <h2 className="text-base font-semibold text-vs-text">Preferências</h2>
                    <form onSubmit={(e) => { e.preventDefault(); preferences.patch(update.url()); }} className="grid gap-5 md:grid-cols-2">
                        <Field id="timezone" label="Fuso horário" error={preferences.errors.timezone}>
                            <Input id="timezone" value={preferences.data.timezone} disabled={disabled} onChange={(e) => preferences.setData('timezone', e.target.value)} className="h-10" />
                        </Field>
                        <Field id="currency" label="Moeda" error={(preferences.errors as Record<string, string | undefined>)["settings.currency"]}>
                            <select id="currency" value={preferences.data.currency} disabled={disabled} onChange={(e) => preferences.setData('currency', e.target.value)} className={selectClass}>
                                <option value="BRL">Real (BRL)</option>
                            </select>
                        </Field>
                        <Field id="date_format" label="Formato de data" error={(preferences.errors as Record<string, string | undefined>)["settings.date_format"]}>
                            <select id="date_format" value={preferences.data.date_format} disabled={disabled} onChange={(e) => preferences.setData('date_format', e.target.value)} className={selectClass}>
                                <option value="d/m/Y">dd/mm/aaaa</option>
                                <option value="Y-m-d">aaaa-mm-dd</option>
                            </select>
                        </Field>
                        <Field id="confirmation_policy" label="Confirmação de agendamento" error={preferences.errors.confirmation_policy}>
                            <select id="confirmation_policy" value={preferences.data.confirmation_policy} disabled={disabled} onChange={(e) => preferences.setData('confirmation_policy', e.target.value)} className={selectClass}>
                                <option value="none">Sem confirmação</option>
                                <option value="manual">Confirmação pela equipe</option>
                                <option value="customer_confirmation">Cliente confirma</option>
                            </select>
                        </Field>
                        <div className="md:col-span-2">
                            <p className="mb-2 text-sm font-medium text-vs-text">Modos de atendimento</p>
                            <div className="flex flex-wrap gap-4">
                                {modes.map((mode) => (
                                    <label key={mode.value} className="flex items-center gap-2 text-sm text-vs-text">
                                        <input type="checkbox" checked={preferences.data.service_modes.includes(mode.value)} disabled={disabled} onChange={() => toggleMode(mode.value)} className="size-4 accent-vs-blue" />
                                        {mode.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div className="md:col-span-2">
                            <Field id="welcome_message" label="Mensagem de boas-vindas" error={preferences.errors.welcome_message}>
                                <textarea id="welcome_message" rows={2} value={preferences.data.welcome_message} disabled={disabled} onChange={(e) => preferences.setData('welcome_message', e.target.value)} className="w-full rounded-md border border-vs-border bg-white p-3 text-sm text-vs-text disabled:opacity-60" />
                            </Field>
                        </div>
                        {canEdit && <div className="md:col-span-2"><Button type="submit" disabled={preferences.processing} className="h-10 bg-vs-blue text-white hover:bg-vs-blue-hover">Salvar preferências</Button></div>}
                    </form>
                </Card>
            </div>
        </>
    );
}
