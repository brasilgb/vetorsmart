import { ChevronDown } from 'lucide-react';
import { landingFocusRing } from './landing-layout';

const faqs = [
    { q: 'Para quem é o VetorSmart?', a: 'Para prestadores de serviços, autônomos, técnicos especializados e pequenas empresas que precisam de presença digital profissional e de uma operação organizada, sem complicação técnica.' },
    { q: 'Preciso instalar algum programa?', a: 'Não. O VetorSmart funciona no navegador do celular, do tablet ou do computador. O aplicativo para celular ainda está em desenvolvimento.' },
    { q: 'O que já está disponível hoje?', a: 'Cadastro de empresa, login, painel da empresa, gestão de clientes, serviços, profissionais, horários de trabalho, equipe e permissões, configurações e o Meu Site com publicação.' },
    { q: 'O que ainda está em desenvolvimento?', a: 'Atendimento com IA, agenda com reserva, aplicativo, propostas, avaliações no Google e o painel Meu Crescimento. Cada um aparece com a indicação do estágio atual.' },
    { q: 'Preciso informar CNPJ, endereço ou cartão para começar?', a: 'Não. O cadastro pede somente o nome da empresa, o segmento e os dados do responsável. Os demais dados podem ser preenchidos depois.' },
    { q: 'Os preços são definitivos?', a: 'Não. Os valores são hipóteses a validar, ainda não preços aprovados. Não há cobrança ativa nesta versão.' },
    { q: 'Meus dados ficam separados dos de outras empresas?', a: 'Sim. Cada empresa enxerga somente os próprios registros, e o acesso é verificado no servidor a cada requisição.' },
];

/** Accessible accordion in Stitch cards: native disclosure, keyboard operable, with visible focus. */
export function FaqSection() {
    return (
        <div className="mx-auto grid max-w-4xl gap-2">
            {faqs.map((faq) => (
                <details key={faq.q} className="group overflow-hidden rounded-xl border border-vs-line bg-white shadow-xs">
                    <summary className={`flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-6 py-4 text-left font-display text-lg font-bold text-vs-text transition-colors marker:content-none hover:text-vs-blue-hover ${landingFocusRing}`}>
                        {faq.q}
                        <ChevronDown aria-hidden="true" className="size-5 shrink-0 text-vs-muted transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none" />
                    </summary>
                    <p className="px-6 pb-4 text-base leading-relaxed text-vs-muted">{faq.a}</p>
                </details>
            ))}
        </div>
    );
}
