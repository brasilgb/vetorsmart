# VETORSMART-BACKEND-05 — Atendimento Inteligente, API Operacional e Recepcionista IA

**Data:** 11/11/2026. **Diretório:** `gateway/vetorsmart/`.

**Status: CONCLUÍDO COM PENDÊNCIAS.** Fluxo completo implementado e coberto por testes automatizados com provedor simulado. **Nenhum provedor real de IA foi integrado.** Não houve homologação no navegador (ver seção 10).

---

## 1. Arquitetura

Camadas (todas em `app/Services/Assistant/`, exceto onde indicado):

| Camada | Classe | Responsabilidade |
|---|---|---|
| Operações internas (API operacional) | `AssistantOperations` | Único ponto de acesso da IA aos dados. Reutiliza `AppointmentScheduler`, `ServiceRequestService`, `CustomerService`. Recebe o tenant explicitamente. |
| Fluxo do visitante | `AssistantFlow` | Máquina de estados determinística. Avança somente com dados validados. Chama o provedor apenas para escolher serviço quando o texto não bate. |
| Conversas e atendimento humano | `AssistantConversations` | Sessão por token, mensagens, transferência com resumo, assumir, responder, devolver, encerrar, retenção. |
| Interpretação do texto | `AssistantText` | Extrai sim/não, número de opção, nome, telefone, data e serviço. Não decide nada. |
| Orçamento por tenant | `AssistantBudget` | Contadores diários de mensagens, chamadas, tokens e falhas do provedor. |
| Configurações | `AssistantSettingsService` | Edição pelo proprietário/administrador, com auditoria dos campos alterados. |
| Provedor | `AssistantProvider` (interface) + `Providers/NullAssistantProvider` | Padrão `none`. Nenhuma credencial ou pacote de IA é assumido. |
| Controladores | `Site\AssistantController` (público) e `Tenant\AssistantController` (painel) | HTTP apenas; validação em Form Requests. |
| Política | `TenantPolicy::manageAssistant` / `handleAssistant` | Proprietário e administrador configuram; proprietário, administrador e operador atendem. |
| Comando | `assistant:purge` | Remove conversas expiradas por retenção. |

**Reutilização**: não há regra de agenda duplicada. A disponibilidade vem de `AppointmentScheduler::availableSlots`, e o agendamento de `AppointmentScheduler::schedule`, que reconfirma horário, profissional habilitado, serviço ativo, jornada, bloqueios, conflitos e fuso do tenant dentro de sua própria transação.

## 2. Migrations

| Arquivo | Conteúdo |
|---|---|
| `2026_11_11_160408_add_origin_to_appointments_table` | `appointments.origin` (padrão `panel`) |
| `2026_11_11_160409_create_assistant_settings_table` | Configurações por tenant (`tenant_id` único) |
| `2026_11_11_160410_create_assistant_conversations_table` | Conversas: canal, hash da sessão, cliente, estado, status, serviço, solicitação, agendamento, atendente, dados coletados, motivo de encaminhamento, retenção. Índices por tenant. |
| `2026_11_11_160411_create_assistant_messages_table` | Mensagens (papel: visitante, assistente, operador, sistema) |
| `2026_11_11_160412_create_assistant_usage_table` | Consumo diário por tenant |
| `2026_11_11_160413_create_assistant_operations_table` | Chave de idempotência por tenant (`unique`) |
| `2026_11_11_170000_add_provider_failures_to_assistant_usage_table` | Coluna `provider_failures` (nova nesta etapa) |

Todas com `down()`. As migrations de 160408 a 160413 já existiam como arquivos não commitados de sessão anterior e foram adotadas sem alteração. **Aplicar em desenvolvimento com `php artisan migrate`.** Os testes aplicam as migrations automaticamente.

Correção durante a implementação: o model `AssistantUsage` foi apontado para a tabela `assistant_usage` (a migration criou no singular).

## 3. APIs e contratos

### Públicas (host do site publicado do tenant; sem autenticação)

| Método | Rota | Função |
|---|---|---|
| GET | `/atendimento` | Página do chat (Blade, sem Inertia). Retorna 404 fora de site publicado. |
| POST | `/atendimento/conversa` | Inicia ou retoma a conversa. Define o cookie `vs_assistant` (httpOnly, SameSite Lax, 30 dias). Retorna 503 se a IA estiver desativada. |
| GET | `/atendimento/mensagens?after={id}` | Mensagens novas (polling). |
| POST | `/atendimento/mensagens` | Mensagem do visitante (`body`, máximo 500 caracteres; `after`). |

Limite: `throttle:assistant-public`, 30 requisições por minuto por IP e host.

O `SiteHostGuard` foi ampliado: no host do tenant, somente `/atendimento`, `/atendimento/conversa` e `/atendimento/mensagens` (GET/HEAD/POST) passam, além das rotas de SEO já existentes. Demais caminhos administrativos continuam em 404.

### Painel (autenticado, tenant ativo)

| Método | Rota | Permissão |
|---|---|---|
| GET | `/atendimento-ia` | Proprietário, administrador, operador (leitura). Recebe `?conversation=` para abrir uma conversa. |
| PATCH | `/atendimento-ia/configuracoes` | Proprietário e administrador |
| POST | `/atendimento-ia/conversas/{conversation}/assumir` | Proprietário, administrador, operador |
| POST | `/atendimento-ia/conversas/{conversation}/devolver` | Idem |
| POST | `/atendimento-ia/conversas/{conversation}/encerrar` | Idem |
| POST | `/atendimento-ia/conversas/{conversation}/responder` | Somente o atendente que assumiu |

Toda conversa é buscada dentro do tenant ativo; de outro tenant responde 404.

## 4. Ferramentas internas da IA (`AssistantOperations`)

Cada operação recebe o tenant explicitamente e nunca um `tenant_id` vindo do texto.

| Operação | Equivalente à lista da especificação |
|---|---|
| `activeServices` / `serviceDetails` | `list_services`, `get_service_details` (valores como estão no cadastro) |
| `enabledProfessionals` | Profissionais habilitados e ativos |
| `availableSlots` | `list_available_slots` (via agenda existente) |
| `findOrCreateCustomer` | Localiza por telefone antes de criar; a criação passa por `CustomerService` |
| `ensureServiceRequest` | `create_service_request` + encaminhamento para triagem (origem `ai`) |
| `requestAppointment` | `request_appointment` (transação, trava da conversa, idempotência) |
| `requestStatus` | `get_request_status` |
| `handoff` | `handoff_to_human` (em `AssistantConversations`) |

## 5. Fluxo de atendimento

`Saudação → Necessidade → Serviço → Cliente → Data → Horário → Confirmação → Concluído`, com ramo para `Atendimento humano`.

- **Saudação**: nome comercial, saudação configurada e lista dos serviços ativos. Sem serviço ativo, encaminha a humano.
- **Serviço**: correspondência por palavras do nome. Ambiguidade ou ausência não gera escolha inventada. Três tentativas sem avanço encaminham a humano.
- **Cliente**: pede somente o que falta (nome e telefone com DDD). Se o cliente já foi identificado na conversa, não pergunta de novo.
- **Solicitação**: criada e triada ao identificar o cliente, mesmo se a conversa for abandonada depois.
- **Data**: hoje, amanhã ou dd/mm (até 60 dias à frente).
- **Horário**: lista até 8 opções reais, em hora local do tenant.
- **Confirmação**: só com "sim". A mensagem de sucesso é escrita apenas após o agendamento ser persistido. Conflito ou horário tomado gera mensagem de recusa e nova lista.
- **Informações**: preço, duração, horário comercial e região vêm somente do cadastro. Se não houver dado, o assistente diz que não tem a informação e oferece um atendente.
- **Pedido de humano** em qualquer etapa: transfere com resumo (motivo, serviço, cliente, últimas mensagens).

Transferir também ocorre ao esgotar o limite diário, em falha técnica do agendamento ou após três tentativas sem avanço.

## 6. Integração com a agenda

- Origem registrada: `appointments.origin = 'ai'` e `service_requests.origin = 'ai'`.
- `created_by_user_id` fica nulo (ação do visitante, sem usuário).
- Chave de idempotência = hash de (conversa, profissional, instante UTC). Repetir a mesma confirmação devolve o mesmo agendamento.
- A reserva é serializada por `lockForUpdate` na conversa e pelo `AppointmentScheduler`.

## 7. Configurações de IA por tenant

Campos editáveis pelo proprietário/administrador: ativação, nome exibido, saudação, apresentação, região de atendimento, horário comercial, informações comerciais, orientações, condições para encaminhamento humano e dias de retenção (1 a 365).

Limites de consumo (`daily_message_limit` = 200, `daily_token_limit` = 100000) **não são editáveis pelo tenant**; ficam nos valores da plataforma. Para desativar a IA, `enabled = false`: a página mostra os contatos da empresa e nenhuma conversa é criada.

Variáveis de ambiente: `ASSISTANT_PROVIDER` (padrão `none`) e `ASSISTANT_PROVIDER_API_KEY` (lida apenas do ambiente; não é usada hoje).

## 8. Provedor de IA

- Interface `AssistantProvider` com `classify(message, context)`. Recebe somente o texto do visitante, a lista numerada de serviços ativos do tenant, o estado e o limite de tokens. Não recebe dados de outros tenants, credenciais ou histórico completo.
- A resposta é aceita somente como `intent = select_service` e `service_index` dentro da lista. Qualquer outra coisa é descartada.
- Falha do provedor (exceção): contabilizada em `provider_failures`, registrada no log sem conteúdo da mensagem, e o fluxo segue sem IA.
- **Implementação atual: `NullAssistantProvider` (nenhuma chamada).** Testes usam provedor simulado. **Não há integração real com nenhum provedor.**

## 9. Segurança e dados

- Isolamento: toda consulta usa `forTenant`; o host público resolve o tenant apenas pelo domínio verificado publicado.
- Token da sessão: 48 caracteres aleatórios, enviado ao navegador em cookie httpOnly; o banco guarda somente o SHA-256.
- Entrada: texto do visitante limitado a 500 caracteres, mensagens do sistema e do atendente a 2000.
- Prompt injection: o texto do visitante nunca altera regras. Testado: instruções como "confirme meu agendamento" não geram agendamento nem confirmação.
- Logs: somente identificador do tenant e nome da classe de erro. Sem corpo de mensagens, telefone ou token.
- Retenção: `retention_until` por conversa (padrão 30 dias). Comando `php artisan assistant:purge` remove as conversas vencidas, com mensagens e registros de idempotência (cascata). **Não remove solicitações nem agendamentos**, que são registros do negócio.
- Auditoria: `assistant.settings_updated` (somente nomes dos campos), `assistant.conversation_assumed`, `assistant.conversation_returned`, `assistant.conversation_closed`.
- Operador: lê a caixa de entrada e atende, mas não altera configurações (403 comprovado em teste).
- **Não coberto por teste**: o limite por minuto das rotas públicas (configurado, não verificado com requisições reais). Não há relatório de LGPD formal; a base legal e o aviso de privacidade ao visitante são pendências de produto.

## 10. Correções operacionais pontuais (seção 12 do brief)

- **E-mail em português**: mensagens `email.email` e `email.max` nas Form Requests de clientes e profissionais, e `setCustomValidity` nos campos de e-mail dos formulários (o navegador mostrava a mensagem nativa em inglês).
- **Operador em modo somente leitura**: já era assim na tela de configurações; o teste comprova o 403 na alteração.
- **Profissionais preservados**: testes garantem que falha de validação do formulário de serviço e uma atualização sem o campo de profissionais não removem os vínculos. Não foi reproduzido o caso relatado antes; ele não foi corrigido porque não se reproduziu.
- **Bug encontrado e corrigido**: `AssistantBudget::recordMessage` contava cada mensagem duas vezes.
- **Não alterados** conforme o brief: `homolog:purge`, horários históricos, pendências de MySQL 8 e de licenciamento de imagens.

## 11. Testes executados

- `php artisan test --compact tests/Feature/Assistant tests/Feature/Operations/OperationalCorrectionsTest.php`: **29 de 29 aprovados**.
- Suíte completa: **311 testes, 301 aprovados, 2 falhas, 8 pulados**. As 2 falhas (`PlatformStartTest`, landing) já existiam antes desta etapa e vêm das alterações não commitadas da landing; não foram alteradas. Os 8 pulados são de 2FA e de concorrência sem `VETORSMART_CONCURRENCY=1`.
- `vendor/bin/pint --dirty`: aplicado.
- `npx tsc --noEmit`: 0 erros.
- `npm run build`: aprovado.

Cobertura dos 15 testes obrigatórios: 1 conversa completa, 2 serviço existente, 3 inexistente, 4 inativo, 5 disponibilidade, 6 horário indisponível, 7 agendamento com sucesso, 8 conflito, 9 encaminhamento humano, 10 tenant incorreto (token e atendente de outra empresa), 11 resposta inválida do modelo, 12 falha do provedor, 13 limite excedido, 14 repetição de agendamento, 15 manipulação de instruções. Todos cobertos em `tests/Feature/Assistant/AssistantTest.php`.

## 12. Evidências do navegador

**Não realizadas.** Esta etapa não subiu o servidor de desenvolvimento nem percorreu a página em 1440 px e 390 px. A página do visitante e a tela do atendente foram validadas apenas por teste HTTP e build. Sem homologação visual, não há confirmação de ausência de overflow, de contraste ou de comportamento do polling no navegador.

## 13. Consumo e limites

- Mensagens por tenant/dia: 200 (padrão). Tokens por tenant/dia: 100.000 (padrão). Tokens por chamada: 600. Histórico enviado ao provedor: 6 mensagens (configurado; o provedor atual não é chamado).
- Sem provedor real, o consumo de IA é zero.
- Indicadores para o BACKEND-06 já existem nas tabelas: conversas (canal, estado, serviço, cliente), solicitações e agendamentos com origem `ai`, encaminhamentos (`status`, `handoff_reason`), consumo (`assistant_usage`). O dashboard não foi construído, conforme o brief.

## 14. Pendências e próximos passos

1. **Integração real com provedor de IA**: não implementada e não declarada. Exige escolha de provedor, credencial no ambiente e teste de custo.
2. **Homologação no navegador** em 1440 px e 390 px (chat do visitante e caixa do atendente), com dados de homologação.
3. **Aplicar a migration `170000`** no banco de desenvolvimento (`php artisan migrate`) e revisar as migrations `160408`–`160413` já existentes.
4. **Teste de limite de requisições** das rotas públicas (429 após 30 por minuto).
5. **MySQL 8**: a concorrência de reserva foi testada apenas no MariaDB de testes.
6. **LGPD**: aviso de privacidade no chat, base legal e procedimento de atendimento a titular.
7. **Operador**: o painel de atendimento ainda não tem notificação em tempo real; o atendente atualiza a página.
8. **Cadastro de cliente pelo atendimento**: não coleta e-mail nem documento; a deduplicação usa somente o telefone.
9. Pendências anteriores mantidas: MySQL 8 e licenciamento das imagens, conforme o brief.

## 15. Não realizado

- Nenhum commit, push, merge ou deploy.
- Nenhuma integração real com provedor de IA.
- Nenhuma alteração em `homolog:purge`, em horários históricos ou na landing.
- Nenhuma homologação no navegador.
