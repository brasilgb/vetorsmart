# VETORSMART-BACKEND-04 — Gestão Operacional do Tenant, Segurança e Padronização Visual

**Status: CONCLUÍDO COM RESSALVAS.** Fase A (regularização) e Fase B (gestão operacional) implementadas. Validação: 203 testes (200 aprovados, 0 falhas, 3 pulados de 2FA), Pint, PHPStan (0 erros), TypeScript (0 erros) e build. **Homologação em navegador real não foi executada** (Chrome não instalado). **Compatibilidade com MySQL 8 não foi comprovada** (servidor local é MariaDB 11.8.6).

Data: 10/11/2026. Diretório: `gateway/vetor-smart/`. Referência: `docs/VETORSMART-BACKEND-03.md`.

---

## 1. Diagnóstico inicial

- BACKEND-01, 02 e 03 lidos. Código existente confirmado: sessão, tenant, policies, auditoria, clientes, categorias, serviços, profissionais, configurações, console RootAdmin e telas de login/cadastro.
- Pendências de segurança do BACKEND-03 identificadas: `.env.example` com senha real e `APP_KEY` real (na cópia de trabalho); cookie seguro não configurado para produção.
- Repositório `gateway/vetor-smart` é independente (branch `main`). Não houve commit, push, merge ou deploy.

## 2. Fase A — Correções do BACKEND-03

### 2.1 Segurança das configurações

- **`.env.example`**: as duas linhas com segredo (`APP_KEY` e `DB_PASSWORD`) foram substituídas por valores vazios e placeholder (`CHANGE_ME_DB_PASSWORD`). Edição pontual: demais alterações locais preservadas.
- **Histórico do git**: o `.env.example` commitado (`HEAD`, commit `cd94240 first commit`) **não contém segredos**; o vazamento existia apenas na cópia de trabalho, não commitada. Verificação feita sem imprimir valores.
- **Cookie seguro**: `config/session.php` passou a `env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`. Produção envia cookie só por HTTPS por padrão; desenvolvimento local não muda.
- **Rotação de credenciais — procedimento documentado, não executado.** Antes de rotacionar:
  1. Senha MySQL: dados não são criptografados pela senha, mas as conexões de integração e o `.env` de cada ambiente precisam ser atualizados juntos; sem impacto em dados gravados.
  2. `APP_KEY`: **impacto real**. Sessões existentes são invalidadas; qualquer campo criptografado com `Crypt`/`encrypted` deixa de ser lido. Este projeto não usa campos criptografados, mas a verificação deve ser repetida antes da rotação. Cookies `XSRF-TOKEN` e sessões serão renovados.
  3. Rotação só com autorização explícita e em janela de manutenção.

### 2.2 Provisionamento do RootAdmin sem terminal

Comando `php artisan platform:root-admin` ampliado, sem criar outro comando:

- Modo interativo: inalterado (senha oculta).
- Modo sem terminal: `--password-env=NOME` (lê variável de ambiente) ou `--password-stdin` (lê uma linha da entrada padrão). **A senha nunca é argumento de linha de comando.**
- Sem nenhuma das duas fontes: recusa com erro.
- Conta existente: apenas promovida; credenciais não são substituídas.
- Validação: nome obrigatório, senha de 12 ou mais caracteres, e-mail válido.
- Auditoria registra `platform.root_admin_provisioned` sem a senha.
- Testes: `CreateRootAdminCommandTest` (8 testes), incluindo variável vazia, senha curta e ausência de segredo na auditoria.
- **Nenhum administrador foi criado** (nem fictício, nem real).

### 2.3 Dados de homologação `[HOMOLOG]`

Rotina `php artisan homolog:purge` com `HomologPurgeService`:

- **Padrão: prévia.** Mostra quantos registros seriam removidos, sem alterar nada.
- **Remoção** exige `--execute`, terminal interativo e digitação exata de `EXCLUIR HOMOLOG`.
- Escopo estrito: empresas cujo nome começa com `[HOMOLOG]`; usuários com e-mail `homolog.%@example.test` **sem nenhum outro vínculo**. Usuário homolog ainda vinculado a empresa real é preservado (testado).
- Ordem de exclusão respeita dependências (vínculos profissional–serviço, profissionais, serviços, categorias, clientes, configurações, vínculos de usuário, empresas) dentro de transação.
- Registros de auditoria são **mantidos**: a referência à empresa vira `NULL` (FK `nullOnDelete`). É registrada uma entrada `platform.homolog_purged` com contagens.
- Testes: `PurgeHomologDataTest` (6 testes): prévia não altera; sem terminal recusa; frase errada não remove; remoção preserva dados reais; e-mail ainda vinculado não é removido; auditoria registrada.
- **Prévia executada no banco de desenvolvimento** (sem remoção): 2 empresas `[HOMOLOG]`, 3 vínculos, 3 clientes, 2 serviços, 1 profissional, 3 usuários, 14 entradas de auditoria que seriam preservadas. **Remoção não executada** — depende de autorização explícita.

### 2.4 Compatibilidade MySQL 8

- **Limitação real:** o único servidor disponível é **MariaDB 11.8.6** (`mysqld` Debian). Não há MySQL 8 nem Docker nesta máquina. Não instalei servidor sem autorização.
- **Não declaro compatibilidade com MySQL 8 como comprovada.**
- Medidas tomadas para portabilidade: nomes de índice explícitos quando o gerado excede 64 caracteres (limite MySQL — encontrado e corrigido: `prof_avail_unique`); `CHECK` constraints criadas só quando o driver é MySQL; FKs compostas; `InnoDB` verificado no teste de integridade.
- Teste `the operational tables use InnoDB...` roda no servidor atual e verifica motor e FKs; não substitui MySQL 8.

## 3. Fase B — Gestão operacional do tenant

### 3.1 Clientes
- Listagem com busca (nome, e-mail, telefone), filtro de situação e paginação (15).
- Cadastro e edição em um único formulário; apenas o nome é obrigatório.
- Dados de contato e endereço; situação ativo/inativo.
- Duplicidade recusada dentro da empresa (documento, e-mail ou telefone/WhatsApp), com mensagem na tela.
- **Arquivar** (não excluir): registros não são apagados enquanto não houver dependências. **Exclusão definitiva não foi oferecida** porque ainda não existe módulo com dependências (agenda, atendimentos); quando existir, a regra deve ser reavaliada.
- **Histórico de atendimentos: não implementado** (módulo ainda inexistente — não exibido).

### 3.2 Serviços
- Listagem com busca por nome, filtro por categoria e situação, ordenação (ordem, nome, preço).
- Cadastro e edição: nome, descrição, categoria, duração estimada, tipo e valor de preço, exige avaliação, ativo.
- Regras de preço: `valor fixo` e `a partir de` exigem valor; `sob consulta` não aceita valor. Ao trocar para sob consulta, o valor é limpo no formulário.
- Ativação e desativação (desativar mantém histórico).
- Associação aos profissionais habilitados: feita pela tela do profissional (vínculo dos dois lados é o mesmo registro).
- Sem regras financeiras complexas.

### 3.3 Profissionais
- Cadastro e edição com identificação e contato; especialidades e observações.
- Serviços executados: seleção múltipla, validada (mesmo tenant, serviço ativo para novos vínculos).
- **Disponibilidade e horários**: tabela `professional_availabilities` (dia 0–6, início, término) com validação de ordem, sobreposição no mesmo dia e `CHECK` no banco. Substituição atômica do conjunto.
- Ativação e desativação.
- Dados preparados para agenda futura (sem agenda implementada).

### 3.4 Usuários e permissões
- Listagem dos vínculos do tenant (paginada), com perfil e situação; sem senha.
- Inclusão controlada: nome, e-mail, senha inicial e perfil. **Sem convite por e-mail** (não há envio de e-mail); a senha inicial deve ser entregue por canal seguro.
- Alteração de perfil (proprietário pode tudo; administrador só gerencia operadores).
- **Suspensão e reativação de acesso** do vínculo (conta do usuário não é apagada).
- Remoção de vínculo = suspensão (não há exclusão de vínculo).
- **Proteção do último proprietário ativo**: rebaixar ou suspender o último proprietário retorna erro (testado). A regra cobre "administrador" porque o proprietário é o administrador máximo; administradores comuns podem ser suspensos.

### 3.5 Configurações da empresa
Tela com quatro seções, cada uma salva separadamente:
- **Dados da empresa**: nome público, segmento, descrição, telefone, WhatsApp, endereço, cidade e UF.
- **Identidade visual**: cor da marca escolhida entre os tokens aprovados (`BrandColor`). **Logotipo próprio não implementado** (upload exigiria armazenamento de arquivos; pendência).
- **Horários**: por dia da semana, aberto/fechado, abertura e fechamento. Validação no servidor (`BusinessHoursRules`): fechamento após abertura. **Intervalos (pausas) são validados, mas a tela ainda não oferece edição de intervalos** — pendência.
- **Preferências**: fuso horário, moeda (apenas BRL), formato de data, confirmação de agendamento, modos de atendimento e mensagem de boas-vindas.
- Operador: vê tudo somente leitura (campos desabilitados); alterações são recusadas pelo servidor (403).
- Não implementado: cobrança, WhatsApp, IA — conforme restrição.

## 4. Identidade visual

- **Login universal**: mantido do BACKEND-03; sem link de recuperação de senha.
- **Cadastro**: mesma tela, sem rolagem no desktop típico (campos compactos); rolagem permitida em telas menores.
- **Paleta**: tokens em `resources/css/app.css`; sidebar `#0F172A` mantida; nenhuma cor nova fora dos tokens.
- **Modo escuro**: bloco `.dark` completado com a mesma família de cores, ajustado para contraste em fundo escuro (texto principal ~15:1, texto secundário ~6,5:1, botão primário com texto escuro). **Verificação de contraste feita por cálculo de razão, não visualmente.** Pendência: validar no navegador.
- **Sidebar**: ícones consistentes (lucide), item ativo destacado, cabeçalho com empresa ativa e seletor validado no backend.
- **Modais de confirmação** (`ConfirmAction`) para arquivar, desativar, suspender acesso e suspender empresa. Nenhuma ação crítica executa sem confirmação no modal.
- **Estados vazios** com mensagem clara (`EmptyState`) em clientes, serviços e profissionais.
- **Tabelas** com rolagem horizontal em telas pequenas (`overflow-x-auto`); sem overflow da página.
- Sem chat ou botão de WhatsApp nas áreas privadas.

## 5. Hierarquia do menu do tenant

Dashboard → Clientes → Serviços → Profissionais → Usuários e permissões → Configurações.

- "Usuários e permissões" aparece apenas para proprietário e administrador (perfil vem do backend).
- Todos os itens exibidos têm tela funcional. Nenhum item sem funcionamento.
- RootAdmin mantém navegação própria (Console, Empresas, Segmentos, Usuários, Auditoria), sem as operações do tenant.

## 6. Segurança e multi-tenancy

- Toda rota de tenant passa por `tenant.resolve`; a empresa vem do `TenantContext` (sessão validada contra vínculo ativo), nunca da URL ou do payload.
- Registros são buscados sempre com `forTenant()`; registro de outra empresa retorna 404.
- Autorização por policy em cada ação, no servidor.
- Operações que alteram vários registros usam transação (cadastro de profissional com serviços e horários; limpeza de homologação; substituição de horários).
- CSRF: formulários web usam o token do Inertia; a API mantém a proteção do middleware web.
- Contas e empresas suspensas são bloqueadas (testado).
- Auditoria: criação, alteração, arquivamento, ativação/desativação, vínculos de serviços, horários, configurações e acesso de usuários.

## 7. Arquivos e migrations

**Migrations (novas)**
- `2026_11_10_105432_create_professional_availabilities_table` — tabela, índice único `prof_avail_unique`, FK composta, `CHECK` de dia e de ordem (MySQL).
- `2026_11_10_105433_add_business_hours_and_brand_color_to_tenant_settings_table` — `business_hours` (JSON) e `brand_color`.

**Incidente registrado**: a primeira tentativa da migration de disponibilidade falhou (nome de índice > 64 caracteres) e deixou uma tabela **vazia** criada pelo próprio processo. Ela foi removida (0 linhas, nenhum dado de usuário) e a migration foi reaplicada corretamente. Nenhuma migration destrutiva foi executada.

**Principais arquivos novos**: `app/Models/ProfessionalAvailability.php`, `app/Enums/BrandColor.php`, `app/Services/{AvailabilityRules,ProfessionalAvailabilityService,BusinessHoursRules,HomologPurgeService}.php`, `app/Console/Commands/PurgeHomologData.php`, `app/Http/Controllers/Tenant/*`, `app/Http/Controllers/Api/V1/Professionals/ProfessionalAvailabilityController.php`, `routes/tenant.php`, telas em `resources/js/pages/tenant/*`, componentes em `resources/js/components/vetor-smart/*`.

**Alterados**: `.env.example` (somente placeholders), `config/session.php`, `app/Console/Commands/CreateRootAdmin.php`, `routes/api.php` (availability e activate), `routes/web.php`, `app/Services/TenantUserService.php` (activate), `app/Http/Controllers/Api/V1/TenantUserController.php`, `app/Http/Controllers/Api/V1/Catalog/ServiceController.php` (busca por nome, opcional), `app/Http/Requests/Api/V1/UpdateTenantSettingsRequest.php`, `app/Http/Resources/Api/V1/TenantSettingResource.php`, `app/Models/{Professional,TenantSetting}.php`, `app/Services/TenantSettingsService.php`, `resources/css/app.css`, `resources/js/components/app-sidebar.tsx`, `resources/views/app.blade.php` (favicon, já da etapa anterior).

## 8. Endpoints e telas

**Novos na API (mesmo padrão dos anteriores, sem alterar contratos existentes)**
- `GET|PUT /api/v1/tenant/professionals/{id}/availability`
- `POST /api/v1/tenant/users/{membership}/activate`
- `GET /api/v1/tenant/services?q=` (busca opcional, adicionada)

**Telas web (tenant)**: `/clientes`, `/clientes/novo`, `/clientes/{id}/editar`, `/servicos`, `/servicos/novo`, `/servicos/{id}/editar`, `/profissionais`, `/profissionais/novo`, `/profissionais/{id}/editar`, `/equipe`, `/configuracoes`. Operações: POST/PATCH/DELETE correspondentes, com `ativar` onde aplicável.

## 9. Testes e evidências

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **203 testes: 200 aprovados, 0 falhas, 3 pulados (2FA)** |
| Pint (`--dirty`) | aprovado |
| PHPStan | **0 erros** (19 apontamentos corrigidos na origem, incluindo um erro meu: validação inserida no método errado, corrigida) |
| TypeScript (`tsc --noEmit`) | **0 erros** |
| Build (`npm run build`) | aprovado |

Testes novos desta etapa:
- `Management/TenantScreensTest` (14): isolamento de clientes, serviços, profissionais e equipe nas rotas web; perfil operador; perfil administrador; arquivamento sem exclusão; último proprietário protegido; reativação auditada; equipe sem senha; disponibilidade válida e inválida; profissional de outra empresa; horário de funcionamento; cor da marca restrita; moeda e formato restritos; configurações somente leitura para operador.
- `Tenancy/PurgeHomologDataTest` (6) e `CreateRootAdminCommandTest` (+3).

Cobertura exigida pelo `correio.md` atendida por testes: login e logout (BACKEND-03), cadastro, acesso RootAdmin, CRUD completo de clientes, serviços e profissionais pela API; criação, arquivamento e isolamento pelas telas web, usuários e permissões, configurações, seletor de empresa, paginação e filtros, isolamento entre tenants.

## 10. Não validado (limitações reais)

1. **Navegador**: Chrome não instalado. Nenhuma das validações visuais foi executada: login e cadastro em desktop e mobile, sidebar, modais, responsividade das tabelas, modo escuro e rolagem do cadastro. **Não declaro homologação visual.**
2. **MySQL 8**: não disponível; testes rodaram em MariaDB 11.8.6.
3. **Prévia `[HOMOLOG]`**: executada, remoção não executada (requer autorização).
4. **Rotação de credenciais**: não executada (requer autorização).
5. **RootAdmin real**: não provisionado.

## 11. Pendências

1. Instalar navegador e executar homologação visual desktop e mobile, com evidências.
2. Validar compatibilidade no MySQL 8 (migrations, CHECK, FKs compostas, índices).
3. Autorizar e executar a remoção dos registros `[HOMOLOG]` (`php artisan homolog:purge` → prévia → `--execute`).
4. Autorizar e planejar a rotação de senha MySQL e `APP_KEY` (impactos na seção 2.1).
5. Logotipo próprio da empresa (upload e armazenamento) — não implementado.
6. Edição de intervalos (pausas) nos horários — validação pronta, tela não.
7. Histórico de atendimentos — depende do módulo de atendimentos.
8. Convite por e-mail — depende de mecanismo de e-mail (não configurado).
9. Exclusão definitiva de clientes — a reavaliar quando houver dependências de agenda.
10. Suspensão de acesso de administrador comum também pela regra de último administrador (hoje só o proprietário é protegido por regra explícita).

## 12. Riscos e decisões técnicas

- **Arquivar em vez de excluir** (clientes, serviços, profissionais) preserva histórico e evita quebra de vínculos futuros.
- **Auditoria mantida na limpeza**: registros de auditoria não são apagados mesmo quando a empresa de homologação é removida; a referência vira nula. Decisão para preservar a trilha.
- **`accepted_if` e não `accepted`**: a regra implícita do Laravel exigiria confirmação em toda requisição (já encontrado e corrigido em etapa anterior; recorrente se regredir).
- **Cor da marca restrita à paleta**: evita valores fora dos tokens aprovados.
- **Horários em JSON**: flexível e validado no servidor; se a agenda exigir consulta por dia, migrar para tabela própria.

## 13. Próxima etapa recomendada

1. Homologação visual real (navegador) e MySQL 8, para fechar as validações que hoje faltam.
2. Módulo de agenda e disponibilidade efetiva, reutilizando `professional_availabilities` e os horários da empresa.
