<<<<<<< HEAD
# Execução: VETORSMART-BACKEND-05.2 — Homologação e consolidação do atendimento
=======
# Execução — VETORSMART-SERVICES-01.4 (Isolamento de testes e homologação na VPS)

Data: 12/11/2026. Diretório: `gateway/vetorsmart/`. Servidor: VPS de produção. Banco: MySQL 8.4.11.

## Instrução

- `correio.md` difere da versão executada anteriormente (SERVICES-01.3). Esta execução seguiu a versão atual (SERVICES-01.4).

## Resultado

**TÉCNICO (testes, Pint, tsc, build): APROVADO.** 332 de 337 testes aprovados e 5 pulados (concorrência, rodada à parte: 5 de 5). Única falha anterior corrigida no teste de auditoria (ordem de chaves JSON no MySQL 8.4).

**HOMOLOGAÇÃO NO NAVEGADOR (desktop, 390 px, multi-tenant, atendimento público): NÃO EXECUTADA.** Credenciais das contas de homologação indisponíveis; a busca foi negada pelo classificador de permissões e não foi contornada.

**LIBERAÇÃO: NÃO APROVADA.**

Atualização: banco `vetorsmart_test` removido pelo responsável; deploy realizado (ver abaixo). Detalhes em `docs/VETORSMART-SERVICES-01.4.md`.

## Ambiente de testes

| Item | Resultado |
|---|---|
| Container de teste | `vetorsmart-test-mysql` (MySQL 8.4.11), rede `vetorsmart-test-net`, sem portas publicadas. Parado ao final. |
| Banco e usuário | `vetorsmart_test`; usuário `vs_test` com privilégio só nesse banco. `root` não usado. |
| Código testado | Cópia isolada em scratchpad, com `.env` de teste. Nada gravado em `public/build` do repositório. |
| Configuração de teste | `APP_URL=http://localhost`, padrões de `config/platform.php` (esperados pelos testes). |
| Senhas | Fora do repositório e fora dos relatórios. |

## Verificações (resultados reais)

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | 337 testes: 332 aprovados, 5 pulados, 0 falhas |
| Correção | `tests/Feature/Tenancy/AuditLogTest.php`: `toBe` → `toEqualCanonicalizing` (MySQL 8.4 reordena chaves JSON; asserções mantidas). |
| `VETORSMART_CONCURRENCY=1 … MysqlConcurrencyTest.php` | 5 de 5 aprovados (MySQL 8.4.11) |
| `vendor/bin/pint --test` | Reprovado em 7 arquivos; corrigidos com `pint` (somente formatação); agora aprovado |
| `npx tsc --noEmit` | 0 erros (Wayfinder gerado na cópia isolada) |
| `npm run build` | Aprovado (cópia isolada) |

Arquivos corrigidos pelo Pint: `app/Policies/ServiceCatalogPolicy.php`, `app/Models/Customer.php`, `app/Http/Resources/Api/V1/{ServiceResource,ServiceCategoryResource,ProfessionalResource,CustomerResource}.php`, `app/Http/Controllers/Api/V1/CustomerController.php`.

## Homologação

- Site respondendo: `https://vetorsmart.com.br/` → 200; `/login` → 200.
- Não realizados: desktop, 390 px, isolamento das empresas A e B, site público e assistente, fluxo completo até a conclusão.
- Resultado: **não verificado**. Nada declarado como aprovado.

## Não realizado / restrições

- Homologação no navegador (acima).
- Nenhuma alteração no `.env` de produção, nos containers `vetorsmart`/`worker`/`scheduler`, em migrations ou em credenciais.
- Nenhuma conta, solicitação ou dado criado na produção.
- Deploy realizado com autorização: imagens `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler` reconstruídas e reiniciadas (`docker compose up -d --no-deps`). Sem migrations (nenhuma pendente). Rollback: tags `rollback-pre-services-01.4`.
- Nenhum commit, push ou merge.

## Pendências e riscos

---

# Execução: VETORSMART-AUTH-UX-01 (Padronização da autenticação)
>>>>>>> 576c9219af61b9d41bc77c75352a80b5ade2f900

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

1. Fornecer, por canal autorizado, as credenciais das contas de homologação A/B (ou outro método autorizado) para executar a homologação.
2. Pendências herdadas: verificadas em 12/11/2026. O banco `vetorsmart` de produção tem 0 usuários e 0 tenants; não existem contas `srv.owner.a/b@example.test` nem dados `[HOMOLOG-SRV]`. Nada foi removido. Confirmar se o homologação anterior ocorreu em outro banco.

**Atualização:** `php artisan db:seed --force` executado em produção. Seeder revisado antes: apenas `SegmentSeeder` (10 segmentos de referência, `updateOrCreate`, sem contas nem senhas). Resultado: `segments`=10; `users`=0; `tenants`=0.

## Critério final

Não atendido. O módulo não está validado nem homologado no ambiente real.

---

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
