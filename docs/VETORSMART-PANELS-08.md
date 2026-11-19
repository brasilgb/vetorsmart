# VETORSMART-PANELS-08 — Conclusão funcional dos painéis e fluxos operacionais

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy ou migrations na VPS.
- Efí, cobranças e bloqueios inalterados.
- Sem MFA.
- Sem dependências novas.

## Parecer

**Concluída.** Os 8 critérios de aceite foram atendidos:

| # | Critério | Situação |
|---|---|---|
| 1 | PANELS-07 consolidada | `bff278d` |
| 2 | Fluxo operacional do assinante | Já existente e integrado (seção 1), sem reimplementação |
| 3 | Equipe, serviços e disponibilidade | Já existentes (seção 1); faltava o link de avaliação do Google nas informações comerciais (seção 2.3) |
| 4 | RootAdmin prioritário | Operacional; o detalhe da empresa ganhou os chamados e o acesso à gestão de usuários (seção 2.4) |
| 5 | Notificações de suporte | Implementadas pela fila existente (seção 2.1) |
| 6 | Permissões e isolamento | Preservados (testes de isolamento do suporte e das notificações) |
| 7 | Testes direcionados | 9 novos e 442 dos grupos afetados |
| 8 | Regras financeiras | Nenhum arquivo de cobrança, Efí ou bloqueio alterado |

## 1. Funcionalidades existentes confirmadas (evidência no código e nos testes)

| Área | Existente | Evidência |
|---|---|---|
| Recebimento de solicitações | Painel, site e IA (`RequestOrigin`) | `ServiceRequestController::store`, `ServiceRequestWorkflowTest` (11) |
| Triagem e encaminhamento | Triagem com serviço, profissional e prioridade | `triage`, `TriageServiceRequestRequest` |
| Agendamento e alteração de horário | Agendar a partir da solicitação; remarcar e cancelar na agenda | `schedule`; `AgendaController::reschedule` e `cancel`; `AgendaTest` (14) |
| Execução e conclusão | Iniciar, concluir, não realizado (com motivo) | `start`, `complete`, `notPerformed` (`ReasonRequest`) |
| Cancelamento com motivo | Obrigatório | `cancel` (`ReasonRequest`), `closing_reason` |
| Histórico da solicitação | `service_request_events` exibido no detalhe | `requests/show.tsx`, `AgendaHistoryTest` |
| Painel coerente com os registros | Indicadores por consulta real | `DashboardController` (sem simulação) |
| Equipe e permissões | Listar, adicionar, editar perfil e nome, ativar e desativar; limite de vagas; isolamento | `TeamController`, `TenantUserService`, `SeatService`, `TenantScreensTest`, `SeatTest`, `OperatorPermissionMatrixTest` |
| Serviços | Cadastro e edição com descrição, duração e preço (fixo, a partir de, sob consulta); ativar e desativar (`active` na edição); vínculo com profissionais; o site exibe os serviços escolhidos em "Meu Site" | `ServiceController`, `UpdateServiceRequest`, `WebsiteSnapshotBuilder`, `ServicesModuleTest`, `ServiceCatalogTest` |
| Disponibilidade | Horários por profissional, bloqueios, consulta de horários livres, prevenção de conflito e fuso da empresa | `ProfessionalAvailabilityService`, `AgendaController::slots` e `storeBlock`, `AppointmentScheduler` (verifica conflito), `TenantTimezoneTest`, `MysqlConcurrencyTest` |
| Informações comerciais | Nome, descrição, telefone, WhatsApp, endereço, cidade, UF, horário de funcionamento e cor da marca; região atendida (nas configurações do Atendimento IA) | `UpdateTenantSettingsRequest`, `TenantSettingsService`, `TenantSettingsOperationalTest` |
| RootAdmin | Empresas (filtros, detalhe, situação contratual), usuários por empresa (filtro `tenant_id`), ações de conta (PANELS-05), indicadores reais, suporte (PANELS-06 e 07) | Telas e testes das etapas anteriores |

**Não alterado:** regras de solicitação, agenda, serviços, equipe ou disponibilidade, por já estarem homologadas.

## 2. Funcionalidades implementadas

### 2.1 Notificações de suporte por e-mail

Usam a infraestrutura existente: `OperationalNotifier` e `OperationalMail` na fila, com 3 tentativas.

| Evento | Quem recebe | O quê |
|---|---|---|
| A equipe responde publicamente | Proprietários e administradores ativos da empresa (não o operador) | Aviso na central do painel e e-mail "Resposta da equipe VetorSmart no chamado #N", com assunto e link. **O texto da resposta não vai no e-mail** |
| A empresa responde um chamado "Aguardando a empresa" ou "Resolvido" | O responsável do chamado (administrador da plataforma ativo); sem responsável, todos os administradores da plataforma ativos | E-mail "Empresa respondeu o chamado #N", com empresa, assunto e link para o RootAdmin |

**Requisitos atendidos:**
- **pela fila**, com `afterCommit`;
- **notas internas nunca notificam;**
- **sem conteúdo sensível**: nem a resposta, nem dados de contato;
- **sem duplicidade**: chave única por resposta em `notification_deliveries` (testado);
- **falha no envio não bloqueia o chamado**: o aviso fica em `try/catch` com registro em log (testado com o envio forçado a falhar);
- **sem serviço externo.**
- Só recebe e-mail quem tem o endereço confirmado (regra existente).

### 2.2 Próxima ação nas respostas

- "Aberto" e "Em andamento" ficam com a **equipe**; "Aguardando a empresa", com a **empresa** (`SupportTicketStatus::nextActionBy()`).
- Ao responder publicamente, a equipe marca **"Aguardar retorno da empresa"** (marcado por padrão; some na nota interna). O chamado passa para "Aguardando a empresa", a menos que outra situação tenha sido escolhida na mesma ação.
- A resposta da empresa a um chamado aguardando ou resolvido devolve a ação à equipe ("Aberto") e avisa a equipe.
- As listas e os detalhes mostram **"Próxima ação: equipe" ou "empresa"** no RootAdmin, e **"Com a equipe VetorSmart" ou "Aguardando sua resposta"** (em destaque) no painel.

### 2.3 Link de avaliação do Google (informações comerciais)

- **Campo novo em Configurações:** "Link de avaliação no Google", com instrução de onde obtê-lo.
- **Sem estrutura paralela:** fica no JSON `tenant_settings.settings`, já existente, sem migration.
- **Gravação mesclada:** atualizar o link não apaga moeda e formato de data (testado).
- **Segurança:** só `https://` de domínios do Google (google.com, google.com.br, g.page, goo.gl). Outro domínio é recusado com mensagem (testado), para que o site público não exiba um link malicioso.
- **No site publicado:** o contato mostra "Avalie-nos no Google" (`rel="noopener nofollow"`, nova aba). Entra no conteúdo publicado na **próxima publicação** do site.

### 2.4 RootAdmin: chamados e acessos da empresa

O detalhe da empresa ganhou:
- o cartão **"Chamados de suporte"**: quantos estão em aberto, os 5 mais recentes (só desta empresa, testado) e o link "Ver todos os chamados da empresa" para a Central de Suporte filtrada;
- o link **"Gerir acessos em Usuários e Acessos"** para a lista de usuários filtrada pela empresa, onde estão suspensão, reativação e reenvio de confirmação.

**Nenhuma ação financeira foi acrescentada.**

## 3. Problemas corrigidos

1. A resposta da equipe não indicava quem deveria agir em seguida; um chamado respondido continuava "Aberto" mesmo quando dependia da empresa. Corrigido pela regra da seção 2.2.
2. A empresa não ficava sabendo das respostas da equipe sem abrir o painel, e a equipe não ficava sabendo das respostas das empresas. Corrigido pela seção 2.1.
3. As informações comerciais não tinham o link de avaliação do Google, pedido no correio.
4. O detalhe da empresa no RootAdmin não levava aos chamados nem à gestão de acessos dela.

## 4. Alterações de banco

**Nenhuma.** O link de avaliação usa o JSON `settings`, que já existe. Continuam **18 migrations** pendentes para a próxima implantação.

## 5. Arquivos

**Criados:** `tests/Feature/Tenancy/OperationalPanelsTest.php` e este relatório.

**Alterados:**
- `app/Services/OperationalNotifier.php` (`supportAnswered` e `supportCompanyReplied`);
- `app/Services/Support/SupportTicketService.php` (avisos e `await_company`);
- `app/Enums/SupportTicketStatus.php` (`nextActionBy`);
- `app/Http/Controllers/RootAdmin/SupportController.php` e `Tenant/SupportController.php` (próxima ação);
- `app/Http/Requests/RootAdmin/UpdateSupportTicketRequest.php` (`await_company`);
- `app/Http/Controllers/RootAdmin/TenantController.php` (chamados no detalhe);
- `app/Http/Requests/Api/V1/UpdateTenantSettingsRequest.php` (link e validação);
- `app/Services/TenantSettingsService.php` (gravação mesclada);
- `app/Http/Resources/Api/V1/TenantSettingResource.php`;
- `app/Services/Websites/WebsiteSnapshotBuilder.php`;
- `resources/views/website/site.blade.php`;
- `resources/js/pages/tenant/settings/index.tsx`;
- `resources/js/pages/tenant/support/index.tsx` e `show.tsx`;
- `resources/js/pages/rootadmin/support/index.tsx` e `show.tsx`;
- `resources/js/pages/rootadmin/tenants/show.tsx`.

## 6. Testes realizados

| Verificação | Resultado |
|---|---|
| `OperationalPanelsTest` (novo) | **9 de 9**: resposta pública avisa proprietário (fila, sem o texto da resposta) e não o operador; nota interna não avisa; mesma resposta avisa uma vez só; "aguardar retorno" passa a ação à empresa e a resposta da empresa devolve à equipe, avisando o responsável; sem "aguardar", fica com a equipe e não há aviso; sem responsável, avisa todos os administradores da plataforma ativos; **falha no envio não bloqueia**; link do Google só de domínio Google e gravação mesclada; detalhe da empresa só com os chamados dela |
| Grupos afetados (`Tenancy`, `RootAdmin`, `Operations`, `Websites`, `Notifications`) | **442 testes, 433 aprovados**; os 9 restantes são os de concorrência, que exigem `VETORSMART_CONCURRENCY=1` |
| PHPStan | 0 erros (3 apontamentos corrigidos na causa) |
| Pint | ok (ajustou só imports e espaçamento) |
| TypeScript / build | ok / ok |
| Visual das telas alteradas do assinante (Suporte e Configurações; desktop e celular, claro e escuro) | **8 de 8**, sem violações de axe nem rolagem horizontal |

A suíte completa e a homologação geral não foram executadas, conforme o correio.

## 7. Pendências reais

1. **RootAdmin no navegador:** não há conta root local autorizada, e nenhuma conta privilegiada foi criada. As telas de suporte, o detalhe da empresa e as exportações foram validados por testes.
2. **Entrega real dos e-mails:** depende do SMTP de produção, ainda pendente desde a RELEASE-11.1. Os avisos são enfileirados e testados; a entrega não foi verificada, e nenhum e-mail real foi enviado.
3. **Link do Google no site:** aparece após a próxima publicação do site pela empresa. A renderização pública não foi conferida em navegador nesta etapa.
4. **Região atendida:** continua nas configurações do Atendimento IA, sem duplicação. Exibi-la também em Configurações seria só uma questão de interface.
5. **Imagens e logotipo da empresa:** não há suporte a upload hoje (só a cor da marca). É uma funcionalidade nova, que exige armazenamento de arquivos.

## 8. Recomendações para concluir os painéis

1. **Homologação integrada** dos painéis (assinante e RootAdmin, com conta root legítima) numa etapa própria, antes da implantação das 18 migrations.
2. **Upload de logotipo e imagens** do site, com limite de tamanho, tipos permitidos e armazenamento em `storage/app/public` isolado por empresa.
3. **Exibir a região atendida** na tela de Configurações, lendo e gravando o mesmo campo do Atendimento IA.
4. **Preferência de aviso de suporte por pessoa** na central de notificações, se os proprietários pedirem. Hoje é aviso administrativo obrigatório.
5. **Prazo sugerido por prioridade** nos chamados, editável, sem SLA contratual.

## 9. Git

- `bff278d`: PANELS-07.
- **PANELS-08 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**
