# Execução: VETORSMART-BACKEND-05.2 — Homologação e consolidação do atendimento

Data: 13/11/2026. Local: `gateway/vetorsmart/`.

## Análise do correio

`correio.md` difere do commit anterior (`VETORSMART-AUTH-UX-01`, padronização da autenticação). A nova instrução foi executada. A execução anterior deste arquivo (integração do atendimento ao painel) foi substituída.

## Resultado geral

- Atendimento (`tests/Feature/Assistant`): **38 de 38 aprovados.**
- Suíte completa: **360 testes, 355 aprovados, 5 pulados (2FA), 0 falhas.**
- Concorrência com MySQL (`MysqlConcurrencyTest`, banco `vetorsmart_test`, `VETORSMART_CONCURRENCY=1`): **5 de 5 aprovados.**
- `npx tsc --noEmit`: sem erros. `vendor/bin/pint`: aplicado nos arquivos PHP alterados.
- Homologação no navegador (desktop 1440 px e mobile 390 px): **não realizada.**
- Implantação na VPS: **não realizada.** Commit: **não realizado.**

## Funcionalidades implementadas ou ajustadas

1. **Horário indisponível na confirmação (seção 2 do correio).** Antes, o assistente encaminhava a conversa à equipe. Agora:
   - a solicitação e o agendamento são gravados numa mesma transação; se a agenda recusar o horário, a transação é desfeita e nada fica registrado;
   - o visitante é informado de que o horário não foi reservado;
   - a disponibilidade do mesmo dia é consultada de novo pelo `AppointmentScheduler` e até **três** horários livres, os mais próximos do pedido, são oferecidos;
   - nada é trocado automaticamente: o visitante escolhe um número e responde "sim" de novo;
   - se não houver horário no dia, a conversa volta à escolha de data; "outra data" e "atendente" continuam disponíveis.
   - Arquivo: `app/Services/Assistant/AssistantFlow.php` (`book`, `offerAlternatives`, `offerSlots`).
   - Ajuste correlato: a conversa guarda somente as opções exibidas, para que o número escolhido sempre corresponda a uma opção vista pelo visitante (antes eram até 40 opções guardadas, com 8 exibidas).
2. **Atendimento humano sem identificação (seção 1).** A fila de pendentes de identificação já não depende de solicitação nem de cliente. Ajuste: a fila exibe "Não identificado" no lugar de "visitante não identificado". Arquivo: `resources/js/pages/tenant/assistant/index.tsx`.
3. **Testes do atendimento.** O helper de testes enviava o cookie do primeiro visitante em todas as requisições seguintes, o que fazia um segundo visitante cair na conversa do primeiro. Corrigido para enviar sempre o cookie do visitante atual (vazio quando não há). Arquivo: `tests/Feature/Assistant/AssistantTest.php`.
4. **Documentação.** `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`: seção 5 corrigida e seção 16 atualizada com horário indisponível, testes desta etapa e pendências.

## Itens já existentes, verificados sem alteração

- Aviso de privacidade antes de pedir nome e telefone.
- Widget `/atendimento`: envio duplicado bloqueado durante o envio, `client_id` para reenvio idempotente, estados de carregamento e erro, rótulos e foco acessíveis, layout com largura máxima de 720 px e quebra em telas de 420 px ou menos.
- Continuidade da conversa após recarregar a página, pelo cookie httpOnly.
- Conversão posterior da conversa em solicitação, com identificação pelo painel.
- Isolamento entre empresas em leitura, início, conclusão e identificação (404).
- Um profissional não recebe dois agendamentos conflitantes (trava no `AppointmentScheduler`).

## Testes executados

| Conjunto | Resultado |
|---|---|
| `tests/Feature/Assistant` | 38 aprovados, 0 falhas |
| Suíte completa | 360 testes: 355 aprovados, 5 pulados (2FA), 0 falhas |
| `MysqlConcurrencyTest` com `VETORSMART_CONCURRENCY=1` | 5 aprovados |
| `npx tsc --noEmit` | sem erros |

Novos cenários: horário retirado antes do "sim" (alternativas oferecidas, sem reserva, nova confirmação exigida); dois visitantes disputando o mesmo horário (um agendamento só, o segundo recebe alternativas).

## Evidências de navegador

**Não realizadas.** Para testar no navegador é preciso que o domínio verificado do tenant (`clima-total.vetorsmart.test` nos testes) resolva localmente e que exista um site publicado no banco de desenvolvimento. Nada disso foi configurado nesta etapa, e não há capturas nem registros de navegador. Os cenários exigidos (site → atendimento, atendimento → agendamento, atendimento → encaminhamento, painel → triagem, triagem → execução, execução → conclusão) foram cobertos apenas por teste HTTP e de serviço.

## Resultados dos testes de concorrência

- `MysqlConcurrencyTest`: 5 de 5 aprovados no MySQL de testes local.
- Dois visitantes disputando o mesmo horário: coberto em teste, em sequência. O segundo visitante não é agendado para o horário já reservado e recebe alternativas.
- Reenvio de mensagens: coberto pelo `client_id` e pelo teste de reenvio.
- Repetição de agendamento: coberta pela chave de idempotência.
- Falhas durante persistência: **não coberta por teste dedicado.** O caminho de erro técnico do agendamento existe (transfere à equipe com mensagem ao visitante), mas nenhum teste força uma falha de gravação. Só foi testada a falha do provedor de IA, que é outro caminho.
- **Não feito**: carga paralela real com vários processos simultâneos. Os testes rodam em sequência de sessões.

## Pendências

1. Homologação no navegador em desktop e mobile (ver acima).
2. Link para a política de privacidade do aviso. A plataforma não tem campo nem página de política; sem decisão de produto o link não foi criado.
3. Limite de 429 das rotas públicas não verificado com requisições HTTP reais (ver seção 16.4 da documentação).
4. Indicadores operacionais do painel: não criados.
5. Provedor de IA real: não integrado (continua `none`), conforme a restrição do correio.

## Riscos para produção

- Todas as alterações do atendimento estão **sem commit**, junto com as alterações anteriores ainda não commitadas.
- O comportamento de horário indisponível mudou: o visitante agora decide novamente em vez de ser encaminhado à equipe. Vale validar com a operação.
- Widget não testado no navegador; o layout foi revisado por leitura do código.
- Concorrência testada em sequência, sem carga paralela real.
- Aviso de privacidade sem link para política.

## Migrações

**Nenhuma migração nova nesta etapa.**

## Situação de commit e deploy

- **Commit: não realizado** (restrição do correio).
- **Deploy na VPS: não realizado** (restrição do correio).
- Nenhuma alteração em infraestrutura Docker compartilhada, nenhuma alteração destrutiva no banco e nenhuma alteração de regras comerciais.

## Arquivos modificados nesta etapa

- `app/Services/Assistant/AssistantFlow.php`
- `tests/Feature/Assistant/AssistantTest.php`
- `resources/js/pages/tenant/assistant/index.tsx`
- `docs/VETORSMART-BACKEND-05-ATENDIMENTO-IA.md`
- `executed.md`
