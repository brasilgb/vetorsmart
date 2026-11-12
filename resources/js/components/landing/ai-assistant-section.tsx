import { Check, CheckCheck, EllipsisVertical, Headset, Phone, SendHorizontal, Smile, Zap } from 'lucide-react';
import { landingContainer, landingSectionSpacing } from './landing-layout';
import { StatusBadge } from './section-shell';

const benefits = [
    { title: 'Atendimento imediato, a qualquer hora', text: 'Mesmo à noite ou no fim de semana, o cliente é acolhido e orientado na hora.' },
    { title: 'Triagem antes de chegar até você', text: 'Coleta o tipo de serviço, o endereço e a disponibilidade do cliente antes de passar o contato adiante.' },
    { title: 'Os contatos são do seu negócio', text: 'Cada conversa fica registrada na sua carteira de clientes, sem intermediários.' },
];

type Message = { from: 'visitor' | 'assistant'; text: string; time: string };

/** Fictional conversation for the illustration. The company name is invented and labelled as an example. */
const conversation: Message[] = [
    { from: 'visitor', text: 'Olá! Preciso de manutenção preventiva em 2 aparelhos de ar-condicionado na minha empresa.', time: '10:14' },
    { from: 'assistant', text: 'Olá! Podemos ajudar, sim. Qual a marca e a capacidade dos aparelhos, e qual o melhor dia para a visita da equipe técnica?', time: '10:14' },
    { from: 'visitor', text: 'São 2 aparelhos de 12.000 BTUs. Prefiro quinta-feira pela manhã.', time: '10:15' },
    { from: 'assistant', text: 'Anotado! Vou registrar seu pedido para quinta-feira de manhã e o responsável confirma o horário com você.', time: '10:15' },
];

/**
 * Highlight of the virtual receptionist, from the Stitch landing. The capability is still in
 * development, so the section carries that status and the chat is captioned as an illustration.
 */
export function AiAssistantSection() {
    return (
        <section id="atendimento-ia" aria-labelledby="atendimento-ia-title" className={`bg-vs-canvas ${landingSectionSpacing} scroll-mt-20`}>
            <div className={`${landingContainer} grid items-center gap-12 lg:grid-cols-2`}>
                <div className="flex flex-col gap-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex w-fit items-center gap-1.5 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold text-vs-ink-cyan">
                            <Zap aria-hidden="true" className="size-3.5" />
                            Atendimento com Inteligência Artificial
                        </span>
                        <StatusBadge status="development" />
                    </div>

                    <h2 id="atendimento-ia-title" className="vs-title-section text-balance text-vs-text">
                        Uma recepcionista que não dorme e não deixa nenhum cliente sem resposta.
                    </h2>
                    <p className="text-lg leading-relaxed text-vs-muted">
                        Quantos orçamentos já se perderam porque você estava em cima de uma escada ou atendendo outro cliente? A recepcionista virtual do VetorSmart responde com base nos serviços e horários que você cadastrou.
                    </p>

                    <ul className="mt-2 grid gap-4">
                        {benefits.map((benefit) => (
                            <li key={benefit.title} className="flex items-start gap-3">
                                <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-vs-tint-green text-vs-ink-green">
                                    <Check aria-hidden="true" className="size-4" />
                                </span>
                                <span>
                                    <strong className="block text-sm font-semibold text-vs-text">{benefit.title}</strong>
                                    <span className="text-sm leading-relaxed text-vs-muted">{benefit.text}</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>

                <figure>
                    <div aria-hidden="true" className="overflow-hidden rounded-2xl border border-vs-line bg-white shadow-xl">
                        <div className="flex items-center justify-between bg-vs-blue-hover px-4 py-3 text-white">
                            <div className="flex items-center gap-3">
                                <span className="relative flex size-10 items-center justify-center rounded-full bg-white/20">
                                    <Headset className="size-5" />
                                    <span className="absolute bottom-0 right-0 size-3 rounded-full border-2 border-vs-blue-hover bg-emerald-400" />
                                </span>
                                <span>
                                    <span className="block text-sm font-bold leading-tight">ClimaTech Refrigeração</span>
                                    <span className="block text-[0.6875rem] text-blue-100">Atendente virtual VetorSmart</span>
                                </span>
                            </div>
                            <span className="flex items-center gap-2 text-white/80">
                                <Phone className="size-5" />
                                <EllipsisVertical className="size-5" />
                            </span>
                        </div>

                        <div className="flex min-h-[22rem] flex-col gap-3 bg-vs-canvas-blue p-5 sm:p-6">
                            <span className="mx-auto my-1 rounded bg-slate-200/80 px-2.5 py-0.5 text-[0.6875rem] font-medium text-vs-muted">Hoje</span>
                            {conversation.map((message, index) => (
                                <div
                                    key={index}
                                    className={
                                        message.from === 'visitor'
                                            ? 'max-w-[82%] self-end rounded-2xl rounded-tr-sm border border-vs-line bg-white p-3.5 text-sm text-vs-text'
                                            : 'max-w-[86%] self-start rounded-2xl rounded-tl-sm border border-blue-100 bg-blue-50 p-3.5 text-sm leading-relaxed text-vs-text'
                                    }
                                >
                                    <p>{message.text}</p>
                                    <span className="mt-1 block text-right text-[0.625rem] text-vs-muted">
                                        {message.time}
                                        {message.from === 'assistant' && ' • Recepcionista virtual'}
                                    </span>
                                </div>
                            ))}
                            <div className="mt-2 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                                    <CheckCheck className="size-4" />
                                </span>
                                <span>
                                    <span className="block text-xs font-bold text-emerald-900">Pedido registrado</span>
                                    <span className="block text-xs text-emerald-700">O contato entra na sua lista de clientes</span>
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 border-t border-slate-100 bg-white p-3">
                            <Smile className="ml-1 size-5 shrink-0 text-vs-muted" />
                            <span className="w-full rounded-lg bg-vs-canvas-blue px-3 py-2 text-sm text-vs-muted">Digite sua resposta...</span>
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-vs-blue-hover text-white">
                                <SendHorizontal className="size-4" />
                            </span>
                        </div>
                    </div>
                    <figcaption className="mt-4 text-center text-xs leading-relaxed text-vs-muted">
                        Exemplo ilustrativo com empresa fictícia. O atendimento com IA está em desenvolvimento.
                    </figcaption>
                </figure>
            </div>
        </section>
    );
}
