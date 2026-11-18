# VETORSMART-PANELS-01 — Auditoria dos painéis do assinante e do RootAdmin

Data: 18/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`. Nada foi feito na VPS: sem deploy, migration ou alteração em produção.

## Parecer

**APROVADO COM RESTRIÇÕES.** O diagnóstico se baseia no código e nos testes reais. A homologação visual das telas autenticadas não foi feita (motivo na seção 5).

O núcleo operacional do assinante (clientes, catálogo, profissionais, solicitações, agenda, atendimento, site, equipe e configurações) está implementado, isolado por empresa e coberto por testes. Para comercializar o SaaS, faltam:

- assinatura e cobrança;
- notificações (e-mail e WhatsApp);
- proteção do cadastro público;
- orçamentos;
- os módulos do RootAdmin que hoje aparecem desativados.

Também foram encontrados dois defeitos: um no expurgo de homologação e outro no login.

## 1. Preparação

| Item | Resultado |
|---|---|
| Branch | `main` |
| HEAD inicial | `1ec0a5c`, 1 commit atrás da `origin/main` |
| Sincronização | Fast-forward para `b411400`. O commit só traz documentação: relatórios WILDCARD-01.2 a 01.5, `correio.md` e `executed.md`. |
| Código em relação à versão implantada | Idêntico. Entre `1ec0a5c` e `b411400` não há diferença em `app`, `routes`, `resources` e `database`. |
| Alterações locais preservadas | `correio.md` (novo correio) e `pnpm-lock.yaml` (modificado antes, sem relação com este trabalho) |
| Diferenças entre o local e a VPS | No local, `APP_URL=http://localhost:8000`, e-mail em `log`, provedor de IA `none` e `PLATFORM_SUBDOMAIN_SITES` desligada. Na VPS há o certificado curinga, mas a flag continua desligada e nenhuma empresa tem site publicado (WILDCARD-01.5). |

## 2. Painel do assinante

Legenda:

- **OK**: implementada e funcional.
- **Parcial**: implementada parcialmente.
- **Interface**: tela existente sem operação completa.
- **Ausente**: não implementada.
- **Defeito**: defeito identificado.

| Módulo | Situação | Observações (do código) |
|---|---|---|
| Dashboard | **OK**, com ressalvas | Indicadores reais: clientes, serviços, profissionais, solicitações abertas, agendamentos de hoje, em andamento, concluídos e cancelados em 30 dias, conversão e próximos compromissos. Nenhum número é fictício. Ressalvas de UX: os itens do checklist não são links; o checklist não inclui "publicar o site" nem "ativar o atendimento"; o card "Concluídos (30 dias)" leva à lista de todos os concluídos, sem o recorte de 30 dias; a prop `membershipActive` é enviada, mas não é usada; o docblock do controller está desatualizado. |
| Clientes | **OK** | Lista paginada (15) com busca e filtro de situação; cadastro, edição e arquivamento (só proprietário ou administrador). Pessoa física e jurídica. |
| Catálogo de serviços | **OK** | CRUD, desativação, categorias e vínculo com profissionais. Criar e editar exige proprietário ou administrador. |
| Profissionais | **OK** | CRUD, ativação e desativação, disponibilidade semanal e vínculo com serviços. |
| Atendimento inteligente | **Parcial** | Configuração (proprietário ou administrador), caixa de conversas, assumir, devolver à IA, encerrar, identificar o cliente e responder. **Não há modelo de linguagem**: só existe o `NullAssistantProvider`, e o fluxo é determinístico (`ASSISTANT_PROVIDER=none`). A caixa não se atualiza sozinha (sem polling), então o atendente só vê mensagens novas ao recarregar. As listas vêm limitadas a 50 conversas, sem paginação. |
| Solicitações | **OK** | Fluxo completo de status (solicitada → triada → agendada → em andamento → concluída / não realizada / cancelada), com histórico de eventos. Limitações: o filtro é só por status (sem busca nem período), e "nova solicitação" carrega todos os clientes ativos num `select` sem paginação, o que não escala. |
| Orçamentos | **Ausente** | Não há modelo, campo ou tela de orçamento. A solicitação não tem valor nem proposta. |
| Agenda e execução | **OK** | Visões de dia, semana e mês; consulta de horários livres; reagendamento, cancelamento e bloqueios. A execução (iniciar, concluir, não realizada) fica na solicitação. A listagem usa uma única consulta com join, sem N+1. |
| Indicadores de crescimento | **Interface** | A tabela `growth_events` só registra eventos de configuração (cliente, serviço, profissional, site, domínio) e nenhuma tela os consome. Não há relatório por período no painel do assinante. |
| Configurações da empresa | **OK**, com ressalvas | Dados, contato, endereço, segmento, horário de funcionamento, política de confirmação, fuso, moeda, cor da marca e mensagem de boas-vindas. **Não há upload de logo nem de imagens.** |
| Meu Site | **OK** | Rascunho, prévia, publicação e retirada; subdomínio e domínio próprio com verificação. O site público só tem tema claro. |
| Usuários e permissões | **Parcial** | Papéis proprietário, administrador e operador, com a garantia de que sempre resta um proprietário ativo; o administrador só gerencia operadores. Limitações: o proprietário define a senha do novo usuário (não há convite por e-mail nem troca obrigatória no primeiro acesso), e um e-mail já cadastrado não pode entrar em uma segunda empresa (`unique:users,email`). |
| Perfil, segurança e aparência | **OK** | Perfil, troca de senha (limite de 6 por minuto), exclusão de conta e tema. |
| Notificações | **Ausente** | Nada em `app/` envia e-mail ou notificação. A empresa não é avisada de agendamento feito pelo site nem de transferência para humano, e o cliente final não recebe confirmação. |

Permissões verificadas nas policies: o operador opera clientes, solicitações, agenda e atendimento, mas não arquiva clientes, não altera o catálogo, as configurações ou o site e não vê a equipe. O menu lateral esconde "Usuários e permissões" para o operador.

## 3. RootAdmin

| Área | Situação | Observações |
|---|---|---|
| Visão geral | **OK** | Indicadores reais: empresas por status, usuários, sites publicados, agendamentos futuros, consumo de IA do mês e distribuição por segmento. As empresas `[HOMOLOG]` ficam fora das contagens. Os indicadores sem fonte aparecem como "pendente", com o motivo. |
| Empresas | **Parcial** | Lista com busca e filtros, detalhe (dados, usuários, domínio, site, atendimento, histórico) e ativar/suspender com confirmação auditada. Não é possível editar os dados nem o segmento de uma empresa. O filtro aceita `cancelled`, mas nenhuma ação leva a esse status. |
| Usuários | **Parcial** | Somente consulta, com filtros. Não há bloqueio de usuário nem redefinição de senha pela console. |
| Assinaturas e situação comercial | **Ausente** | Menu desativado. Não há plano, assinatura, período de teste nem cobrança. |
| Sites e domínios | **Interface** | Menu desativado. Só aparece no detalhe da empresa. |
| Agendamentos e operações | **Parcial** | Só a contagem de agendamentos futuros. Não há visão operacional por empresa (intencional: a console não lê dados operacionais dos tenants). |
| Consumo de IA | **Parcial** | Mensagens, chamadas, falhas e tokens do mês, mas o custo é "pendente" e o menu "IA e Créditos" está desativado. Hoje os números são de fluxo determinístico, porque nenhum provedor está ativo. |
| Indicadores financeiros | **Ausente** | MRR e pagamentos aparecem corretamente como "pendente". |
| Auditoria e segurança | **OK** | Filtros por ação, empresa e período. |
| Segmentos | **OK** | Criação e edição, sem exclusão (intencional). |
| Configurações globais, Suporte, Crescimento e SEO | **Ausente** | Menus desativados. |

Não foram encontrados indicadores fictícios apresentados como dados reais.

## 4. Defeitos e riscos

| # | Tipo | Descrição | Evidência |
|---|---|---|---|
| D1 | **Defeito** | O `homolog:purge --execute` falha quando a empresa `[HOMOLOG]` tem uma solicitação. O serviço não apaga `service_requests`, `service_request_events`, `appointments`, `professional_blocks` e as tabelas `assistant_*`, então a remoção de `customers` viola a chave estrangeira `service_requests_tenant_id_customer_id_foreign`. A transação é desfeita e nada é removido. O teste existente não cria solicitações. | Teste temporário (removido em seguida): `SQLSTATE[23000] 1451 … delete from customers`. Em produção, qualquer homologação de ponta a ponta já deixa a empresa nesse estado. |
| D2 | **Defeito (UX)** | A tela de login não tem o link "Esqueci minha senha", embora `Features::resetPasswords()` esteja ativo e o backend envie `canResetPassword`. O `login.tsx` não usa a prop. A página `/forgot-password` existe, mas só é acessível digitando o endereço. | `resources/js/pages/auth/login.tsx`; captura no Chromium |
| R1 | Risco de segurança | O cadastro público (`POST /register`) não tem verificação de e-mail nem limite por hora. **Correção feita na PANELS-02:** já existia um limite de 5 tentativas por minuto por IP dentro de `CreateNewUser`. Esta auditoria havia afirmado que não havia limite nenhum, porque só olhou o `config/fortify.php`. Com 5 por minuto, um script ainda consegue criar cerca de 7.200 empresas por dia a partir de um único IP. | `config/fortify.php`, `app/Actions/Fortify/CreateNewUser.php` |
| R2 | Risco operacional | Uma empresa é tratada como homologação pelo prefixo `[HOMOLOG]` no nome, e o cadastro não bloqueia esse prefixo. Uma empresa real com esse nome sairia dos indicadores e entraria no expurgo. Hoje só os usuários com e-mail de homologação são removidos, mas os dados da empresa seriam apagados. | `HomologPurgeService::NAME_PREFIX`, `CreateNewUser` |
| R3 | Desempenho (baixo) | A cada visita Inertia, o `HandleInertiaRequests` resolve de novo a empresa ativa e a lista de empresas (`tenant`, `tenants`), repetindo o que o `ResolveTenant` já fez. São algumas consultas por requisição. | `app/Http/Middleware/HandleInertiaRequests.php` |
| R4 | Escalabilidade | A tela "nova solicitação" carrega todos os clientes ativos; o atendimento limita as listas a 50, sem paginação. | Controllers `ServiceRequestController` e `AssistantController` |
| R5 | Comercial | Sem assinatura, cobrança e notificações, o SaaS não pode ser vendido nem operado sem acompanhamento manual. | Seções 2 e 3 |

Isolamento multi-tenant:

- O tenant vem sempre da sessão, validado contra os vínculos ativos (`ResolveTenant`, `TenantResolver`), nunca da URL ou do payload.
- Todos os controllers do painel filtram por `forTenant` e buscam os registros por ID dentro da empresa.
- As policies conferem `owns(tenant_id)`.
- As chaves estrangeiras compostas `(tenant_id, id)` impedem vínculos entre empresas.
- A agenda, que usa uma consulta direta, declara `a.tenant_id` explicitamente.

Nenhum vazamento foi encontrado. Há 78 testes em `Tenancy` e 132 em `Operations`.

## 5. Resultados dos testes

| Verificação | Resultado |
|---|---|
| Pest (MySQL, `VETORSMART_CONCURRENCY=1`) | **486 de 486 aprovados**, 0 pulados, 3126 asserções, cerca de 40 s |
| PHPStan | 0 erros |
| Pint (`--test`) | ok |
| TypeScript (`tsc --noEmit`) | 0 erros |
| Build | ok |
| Homologação visual: páginas públicas e de autenticação (`/`, `/login`, `/register`, `/forgot-password`), Chromium, desktop 1440×900 e mobile 390×844, claro e escuro | **24 de 24 aprovadas**: HTTP 200, sem rolagem horizontal, sem erro de console. `/dashboard` e `/rootadmin` sem sessão redirecionam para o login. |

O que não foi feito:

- **Telas autenticadas (painel e RootAdmin).** Não há credencial conhecida no banco local, e a troca temporária da senha das contas `[HOMOLOG]` foi bloqueada pela política de permissões da sessão. Nenhum dado foi alterado.
- **Firefox e WebKit.** Os binários instalados são de outra versão do Playwright disponível.
- **Testes de acessibilidade com axe.** O `axe-core` não está disponível.

Para concluir a homologação, é preciso uma conta de homologação com senha conhecida (ou autorização para redefinir uma) e repetir o roteiro em Chromium, Firefox e WebKit.

## 6. Recomendações de UX

1. Na tela de login, mostrar o link "Esqueci minha senha" quando `canResetPassword` for verdadeiro (D2).
2. No dashboard, transformar os itens do checklist em links para a tela de cada passo e incluir "Publicar o site" e "Ativar o atendimento".
3. Corrigir o card "Concluídos (30 dias)": aplicar o período na lista ou mudar o rótulo.
4. Ativar o polling (Inertia `usePoll`) na caixa de atendimento, que hoje exige recarregar para ver mensagens novas.
5. Em "nova solicitação", trocar o `select` de clientes por uma busca com autocompletar e permitir cadastrar o cliente na mesma tela.
6. Na lista de solicitações, adicionar busca por cliente e filtro de período.
7. Em Configurações e Meu Site, permitir o envio de logo e imagens.
8. Na equipe, convidar por e-mail e exigir a troca de senha no primeiro acesso.
9. Repetir a homologação visual das telas autenticadas, com axe, antes de liberar a venda.

## 7. Matriz de pendências

Prioridades: P0 (bloqueia a venda ou a operação), P1 (essencial no primeiro ciclo comercial), P2 (melhoria).

| Módulo | Situação | Problema | Impacto | Prioridade | Dependências | Correção recomendada |
|---|---|---|---|---|---|---|
| Expurgo de homologação | Defeito | D1 | Não é possível limpar os dados de teste em produção | **P0** | — | Apagar as tabelas operacionais e `assistant_*` em ordem e cobrir com um teste que tenha solicitação, agendamento e conversa |
| Login | Defeito | D2 | O usuário não recupera o acesso sozinho; gera chamados de suporte | **P0** | E-mail de produção configurado | Exibir o link; validar o envio de e-mail na VPS |
| Cadastro público | Risco | R1 | Abuso e criação de empresas falsas | **P0** | E-mail de produção | Limite (`throttle`) no `register`; avaliar a verificação de e-mail |
| Assinaturas e cobrança | Ausente | Sem plano, teste, cobrança nem bloqueio por inadimplência | Não há como faturar | **P0** | Definição comercial (planos e gateway) | Modelo de plano e assinatura, status de teste, integração de pagamento, tela no RootAdmin e indicadores de MRR |
| Notificações | Ausente | Nenhum aviso à empresa nem ao cliente | Agendamentos e transferências passam despercebidos | **P0** | E-mail de produção; decisão sobre WhatsApp | E-mail (fila) para novo agendamento, transferência para humano e confirmação ao cliente |
| Prefixo de homologação | Risco | R2 | Empresa real fora dos indicadores ou expurgada | **P1** | D1 | Recusar `[HOMOLOG]` no cadastro público ou usar uma marca explícita no banco |
| Atendimento IA | Parcial | Sem modelo de linguagem; sem atualização automática | Atendimento limitado ao fluxo fixo | **P1** | Escolha do provedor, custo e retenção | Implementar o provedor no contrato `AssistantProvider`; adicionar polling; paginar |
| Orçamentos | Ausente | A solicitação não tem valor nem proposta | Processo comercial dos prestadores fica fora do sistema | **P1** | Definição do fluxo (aprovação pelo cliente?) | Entidade de orçamento ligada à solicitação, com itens, valor, validade e aceite |
| Equipe | Parcial | Senha definida pelo proprietário; e-mail único global | Prática insegura; um usuário não atende duas empresas | **P1** | Notificações | Convite por e-mail; vincular um usuário existente |
| RootAdmin: Empresas e Usuários | Parcial | Não edita empresa nem bloqueia usuário | Suporte depende de acesso ao banco | **P1** | — | Edição de dados e segmento; bloqueio e reset de usuário, auditados |
| Configurações e site | Parcial | Sem logo nem imagens | Site público pouco personalizado | **P1** | Armazenamento de arquivos na VPS | Upload validado (tipo e tamanho) com armazenamento por empresa |
| Indicadores de crescimento | Interface | Eventos sem relatório | Sem prova de valor para o assinante | **P2** | — | Relatório por período (solicitações, conversão, origem: site, atendimento, painel) |
| Solicitações | OK | Sem busca; `select` sem paginação | Lento com muitos clientes | **P2** | — | Busca e período; autocompletar |
| RootAdmin: Sites, IA e Créditos, Suporte, Configurações globais | Ausente | Menus desativados | Gestão da plataforma incompleta | **P2** | Assinaturas (para créditos) | Implementar conforme a prioridade comercial |
| Desempenho das props compartilhadas | Risco baixo | R3 | Consultas repetidas | **P2** | — | Reaproveitar a membership do `TenantContext` no `HandleInertiaRequests` |
| Homologação visual autenticada | Pendente | Não executada | Sem evidência visual do painel nesta etapa | **P1** | Credencial de homologação | Roteiro Playwright com axe em Chromium, Firefox e WebKit |

## 8. Sequência recomendada

1. **Correções rápidas (P0 técnico):** D1, D2 e R1, com testes. Configurar e validar o e-mail de produção.
2. **Notificações por e-mail:** agendamento, transferência para humano e confirmação ao cliente. Os convites da equipe vêm junto.
3. **Assinaturas e cobrança:** planos, período de teste, gateway, bloqueio por inadimplência e telas do RootAdmin (Assinaturas e Financeiro, MRR).
4. **Ajustes do RootAdmin:** editar empresa, bloquear e redefinir usuário, tratar o prefixo de homologação (R2).
5. **Orçamentos** ligados à solicitação.
6. **Atendimento IA:** escolher o provedor, implementar o contrato, polling e paginação, custo de IA no RootAdmin.
7. **Upload de logo e imagens**, depois os indicadores de crescimento.
8. **Homologação visual completa** (três navegadores, desktop e mobile, claro e escuro, axe) antes da liberação comercial. Depois, o portal regional.

## Restrições cumpridas

Não foram feitos:

- deploy, migration ou acesso à VPS;
- alteração de código da aplicação;
- trabalho no portal regional, na landing ou em templates;
- alteração nos outros SaaS;
- ativação de subdomínios;
- configuração de MFA.

O único arquivo criado no repositório é este relatório (com o `executed.md` atualizado). O teste temporário que confirmou o D1 foi apagado logo após a execução. Os dados do banco local não foram alterados.
