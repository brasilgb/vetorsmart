# VETORSMART-PANELS-04 — E-mail operacional e notificações

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Sem deploy, sem migration na VPS, sem acesso à produção.

## Parecer

**APROVADO COM RESTRIÇÕES.** As notificações operacionais, a central de notificações e as preferências estão implementadas e testadas:

- 552 de 552 testes automatizados;
- homologação no navegador: funcional 9 de 9 e visual 32 de 32, com 0 violações de axe.

As restrições são de produção: o SMTP não está configurado, há 5 migrations pendentes e o worker precisa ser conferido. Elas estão na seção 6. A homologação visual do RootAdmin (PANELS-03) continua pendente, porque a conta local não foi criada.

## Etapa 1 — Consolidação da PANELS-03

- Revisão do diff. Testes: 520 de 520. PHPStan: 0 erros. Pint: ok. Nenhuma credencial no diff.
- Commits na `main`:
  - `32daf42` `fix(email)`: envio de autenticação pela fila e ajustes da console;
  - `58da0e0` `docs(release)`: registra PANELS-03.
- Push: `4424cb1..58da0e0`. **Sem deploy.** O `correio.md` e o `pnpm-lock.yaml` ficaram fora dos commits.

## Etapa 2 — Infraestrutura de e-mail

| Item | Situação |
|---|---|
| SMTP por variáveis de ambiente | `config/mail.php` lê `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME`. Nenhuma credencial no código. |
| Fila assíncrona | Todo e-mail sai pela fila `database`: confirmação de cadastro, redefinição de senha e os e-mails operacionais. Os operacionais só entram na fila **depois do commit** da transação. |
| Tentativas e falhas | 3 tentativas, com espera de 60 s e 300 s. Depois da última, o job vai para `failed_jobs`. A credencial nunca entra no payload, só o destinatário e o texto. |
| Worker | Em produção já existe o container `vetorsmart-worker` (`queue:work database`, DEPLOY-08). A supervisão, o reinício automático e o `queue:restart` no deploy precisam ser conferidos na VPS (seção 6). |
| Templates responsivos e identidade | Tema `vetorsmart` (`resources/views/vendor/mail/html/themes/vetorsmart.css`), com cabeçalho com logo e nome, botão azul VetorSmart (#2563EB), fundo e textos da paleta do painel, rodapé em português. Só os componentes alterados foram publicados. |
| Idioma | **Defeito corrigido:** os e-mails de confirmação de cadastro e de redefinição de senha saíam **em inglês** (o `pt_BR.json` não cobria esses textos). Agora são escritos em português, e o texto "se o botão não funcionar" do template foi traduzido. |
| Links nos e-mails | Os links do painel usam `platform.public_url` (o domínio central), mesmo quando o evento acontece no subdomínio ou no domínio próprio da empresa, onde o painel não existe. |

**Itens de produção não verificáveis daqui** (não há acesso à VPS nem ao DNS):

- `APP_URL`: precisa ser `https://vetorsmart.com.br`, porque os links assinados são gerados no worker a partir dele;
- `PLATFORM_PUBLIC_URL`;
- `MAIL_FROM_ADDRESS`;
- SPF, DKIM e DMARC.

Os relatórios RELEASE-10.x registram `MAIL_MAILER=log` em produção.

## Etapa 3 — Notificações operacionais

Implementação: `App\Services\OperationalNotifier`, chamado pelos serviços de domínio, nunca pelos controllers. Assim, a mesma regra vale para o painel, o site público, o atendimento e a API.

| Evento | Painel (equipe) | E-mail (equipe) | E-mail (cliente) | Onde dispara |
|---|---|---|---|---|
| Nova solicitação | Todos os membros ativos, menos o autor | Sim (opcional) | "Recebemos sua solicitação" | `ServiceRequestService::create` (painel, site, atendimento e API) |
| Agendamento confirmado ou remarcado | Todos, menos o autor | Sim (opcional) | "Agendamento confirmado", com data e serviço | `AppointmentScheduler::schedule` e `reschedule` |
| Agendamento cancelado | Todos, menos o autor | Sim (opcional) | "Agendamento cancelado" | `AppointmentScheduler::cancel` e o cancelamento da solicitação |
| Conversa aguardando atendimento | Todos | Sim (opcional) | — | `AssistantConversations::handoff`, só quando a conversa **entra** em espera |
| Serviço concluído | — (é o trabalho da própria equipe) | — | "Atendimento concluído" | `ServiceRequestService::complete` |
| Aviso administrativo: empresa suspensa ou reativada | Proprietários | **Sempre** (obrigatório) | — | `TenantStatusService::change` |

Garantias:

- **Só depois do commit:** `DB::afterCommit`. Se a operação é desfeita (rollback), ninguém é notificado.
- **Sem duplicidade:** cada evento tem uma chave (por exemplo, `request.received:{id}` ou `appointment.confirmed:{id}:{horário}`) gravada uma única vez em `notification_deliveries`, que tem índice único. Um evento repetido não gera nada. Uma remarcação para outro horário é um evento novo.
- **Sem excesso:**
  - o autor da ação não recebe aviso dela;
  - a conclusão não avisa a equipe;
  - a conversa em espera é avisada uma vez;
  - membro inativo, conta suspensa ou e-mail não confirmado não recebem e-mail.
- **Cliente:** só recebe se tiver e-mail cadastrado e se a empresa não desligou os e-mails a clientes. O nome do remetente é o da empresa, e o `reply-to` é o e-mail da empresa. O texto do cliente é escapado.
- **Sem WhatsApp:** nenhuma notificação depende dele.

## Etapa 4 — Central de notificações

- Item "Notificações" no menu e **sino no cabeçalho com o número de não lidas**. O rótulo acessível diz, por exemplo, "Notificações, 2 novas".
- `/notificacoes`:
  - lista paginada (20 por página), da mais nova para a mais antiga, com categoria, data no fuso da empresa, título e resumo;
  - marcador "Nova";
  - botões "Abrir" (marca como lida e vai ao registro relacionado), "Marcar como lida" e "Marcar todas como lidas";
  - estado vazio.
- **Isolamento:**
  - tabela `tenant_notifications` com `tenant_id` e `user_id`;
  - aviso de outra pessoa ou de outra empresa responde **404**;
  - quem trabalha em duas empresas vê, em cada uma, só os avisos daquela empresa;
  - o link guardado é sempre um caminho do painel. Um valor externo (`https://…` ou `//…`) volta para a lista em vez de sair do site.

## Etapa 5 — Preferências

- Por pessoa e por empresa (`tenant_users.notification_preferences`): liga e desliga o **e-mail** de cada categoria opcional. O aviso no painel continua.
- **Avisos administrativos são essenciais**, sem chave. Tentar desligá-los é recusado com mensagem.
- Por empresa (proprietário e administrador): liga e desliga os **e-mails aos clientes**. Para o operador, a opção não aparece, e a requisição é recusada (403). A mudança é auditada.
- **Comunicação comercial ou promocional:** não existe categoria, e a plataforma não envia propaganda por este canal. A tela diz isso. Quando houver, deve ter consentimento próprio, separado dos avisos operacionais.

## Etapa 6 — Testes

| Verificação | Resultado |
|---|---|
| Pest completo | **552 de 552**, 3426 asserções (eram 520) |
| Novos testes | `Notifications/OperationalNotificationsTest` (16), `NotificationCenterTest` (14), `MailQueueRecoveryTest` (2) |
| Disparo após o commit | Rollback → 0 avisos, 0 e-mails, 0 chaves |
| Duplicidade | Evento repetido → 1 aviso por pessoa, 1 e-mail |
| Isolamento | Membro de outra empresa nunca é notificado; aviso de outra empresa ou pessoa → 404; "marcar todas" só afeta os próprios |
| Falha e recuperação do SMTP | Fila `database` real e **worker real** (`queue:work`): com o SMTP inacessível, o job volta para a fila (tentativa 1, sem falha definitiva). Com o servidor de volta, depois da espera, o e-mail é entregue e a fila esvazia. Falhando 3 vezes, o job vai para `failed_jobs`. |
| Permissões | Operador não altera os e-mails a clientes (403); visitante → login |
| Segurança | Nome e descrição com HTML são escapados no e-mail ao cliente; link do e-mail aponta para a plataforma, não para o host do evento |
| PHPStan | 0 erros (4 avisos de nulidade corrigidos) |
| Pint, TypeScript e build | ok |
| Navegador: funcional (Chromium) | **9 de 9**:<br>• uma solicitação registrada pelo operador aparece para o proprietário e soma 1 no sino;<br>• o autor não é notificado;<br>• "Abrir" leva à solicitação e baixa o contador;<br>• a preferência é salva;<br>• a opção de clientes fica oculta para o operador;<br>• "Marcar todas" zera o contador;<br>• nenhum erro de JavaScript. |
| Navegador: visual | **32 de 32**: Chromium e Firefox × desktop e celular × claro e escuro × proprietário e operador, nas telas `/notificacoes` e painel com o sino. Sem rolagem horizontal, sem erro de JavaScript, **0 violações de axe** (WCAG 2.0/2.1 A e AA). |
| E-mails renderizados | Mensagem operacional (700 px) e confirmação de cadastro (390 px): layout, botão e textos em português. A logo usa o endereço absoluto público; localmente, sem servidor, ela não carrega. |

Migrations aplicadas **só no banco local**: `tenant_notifications`, `notification_deliveries` e `notification_preferences`.

## Defeitos encontrados

1. E-mails de autenticação em inglês. **Corrigido.**
2. Links de e-mail gerados a partir do host da requisição apontariam para o subdomínio da empresa, onde o painel responde 404. **Evitado:** os links usam `platform.public_url`.
3. Nos testes, uma função auxiliar com nome repetido entre arquivos. **Corrigido.**

## Configurações pendentes de produção

1. **SMTP:** `MAIL_MAILER=smtp`, host, porta, `MAIL_SCHEME`, usuário e senha de um provedor transacional, mais `MAIL_FROM_ADDRESS` em `@vetorsmart.com.br` e `MAIL_FROM_NAME=VetorSmart`.
2. **DNS (Hostinger):**
   - SPF com o provedor;
   - DKIM (chave do provedor);
   - DMARC (começar com `p=none` e um endereço de relatório, depois endurecer).
3. **`APP_URL=https://vetorsmart.com.br`** e **`PLATFORM_PUBLIC_URL=https://vetorsmart.com.br`**.
4. **Worker:**
   - conferir `restart: unless-stopped` (ou equivalente) no `vetorsmart-worker`;
   - rodar `php artisan queue:restart` em todo deploy;
   - acompanhar `failed_jobs` (`php artisan queue:failed`).
5. **Migrations a executar no deploy:**
   - da PANELS-02: `is_homolog` e verificação das contas existentes;
   - da PANELS-04: as 3 de notificações.
6. Depois do deploy, mandar um e-mail de teste e repetir o fluxo de cadastro, confirmação e recuperação com uma caixa real.

## Riscos operacionais

- Sem SMTP em produção, o cadastro novo não é confirmado (PANELS-02) e nenhum aviso sai por e-mail. Os avisos no painel funcionam do mesmo jeito.
- Com o worker parado, os e-mails ficam na fila. Não se perdem, mas atrasam. Convém monitorar o tamanho de `jobs` e o conteúdo de `failed_jobs`.
- `notification_deliveries` cresce uma linha por evento. É leve, mas convém uma rotina de limpeza (por exemplo, mais de 90 dias) junto das tarefas agendadas.
- O contador do sino adiciona uma contagem indexada por requisição do painel.
- Reputação de envio: e-mails "em nome da empresa" saem do domínio da VetorSmart, com `reply-to` da empresa. Sem SPF, DKIM e DMARC, podem cair em spam.
- Homologação visual do RootAdmin (PANELS-03) ainda pendente.

## Arquivos (PANELS-04, sem commit)

**Aplicação:**
- `app/Enums/NotificationCategory.php`
- `app/Http/Controllers/Tenant/NotificationController.php`
- `app/Http/Requests/Tenant/UpdateNotificationPreferencesRequest.php`
- `app/Models/TenantNotification.php`
- `app/Notifications/OperationalMail.php`
- `app/Services/OperationalNotifier.php`
- Alterados: `HandleInertiaRequests`, `TenantUser`, `VerifyEmailNotification`, `ResetPasswordNotification`, `AppointmentScheduler`, `AssistantConversations`, `HomologPurgeService`, `ServiceRequestService` e `TenantStatusService`

**Configuração, idioma e rotas:**
- `config/mail.php`
- `lang/pt_BR.json`
- `routes/tenant.php`

**Banco:**
- 3 migrations novas

**Interface:**
- `resources/js/pages/tenant/notifications/index.tsx`
- `resources/js/components/vetor-smart/notification-bell.tsx`
- `app-sidebar.tsx`, `app-sidebar-header.tsx` e `types/global.d.ts`
- `resources/views/vendor/mail/…`

**Testes:**
- `tests/Feature/Notifications/` (3 arquivos)

## Próxima etapa recomendada

1. Configurar o SMTP e o DNS de produção.
2. Deploy das PANELS-02, 03 e 04 com as 5 migrations, seguido de um e-mail de teste.
3. Homologação visual do RootAdmin.
4. Planos e assinaturas sem gateway (especificação da PANELS-03).
