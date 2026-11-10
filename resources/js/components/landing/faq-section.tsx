import { ChevronDown } from 'lucide-react';
import { landingFocusRing } from './landing-layout';

const faqs = [
    { q: 'O que já está disponível hoje?', a: 'Cadastro de empresa, login, painel da empresa, gestão de clientes, serviços, profissionais, horários de trabalho, equipe e permissões, configurações e o Meu Site com publicação.' },
    { q: 'O que ainda está em desenvolvimento?', a: 'Atendimento com IA, agenda com reserva, aplicativo, propostas, avaliações no Google e o painel Meu Crescimento. Cada um aparece com a indicação do estágio atual.' },
    { q: 'Preciso informar CNPJ, endereço ou cartão para começar?', a: 'Não. O cadastro pede somente o nome da empresa, o segmento e os dados do responsável. Os demais dados podem ser preenchidos depois.' },
    { q: 'Os preços são definitivos?', a: 'Não. Os valores são hipóteses a validar, ainda não preços aprovados. Não há cobrança ativa nesta versão.' },
    { q: 'Meus dados ficam separados dos de outras empresas?', a: 'Sim. Cada empresa enxerga somente os próprios registros, e o acesso é verificado no servidor a cada requisição.' },
];

/** Accessible accordion: native disclosure buttons, keyboard operable, with visible focus. */
export function FaqSection() {
    return (
        <div className="mx-auto max-w-3xl divide-y divide-vs-line">
            {faqs.map((faq) => (
                <details key={faq.q} className="group py-5">
                    <summary className={`flex cursor-pointer list-none items-center justify-between gap-6 rounded-md text-left text-base font-semibold text-vs-text marker:content-none sm:text-lg ${landingFocusRing}`}>
                        {faq.q}
                        <ChevronDown aria-hidden="true" className="size-5 shrink-0 text-vs-muted transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none" />
                    </summary>
                    <p className="mt-4 pr-10 text-base leading-relaxed text-vs-muted">{faq.a}</p>
                </details>
            ))}
        </div>
    );
}
