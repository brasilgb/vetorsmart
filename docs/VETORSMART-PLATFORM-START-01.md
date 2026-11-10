# VETORSMART-PLATFORM-START-01 — Consolidação do Produto e Início da Plataforma de Crescimento

**Status: CONCLUÍDO COM RESSALVAS.** Esta etapa consolidou a identidade comercial, publicou a landing page real, preparou a arquitetura documental de sites multi-tenant e iniciou a fundação de eventos do Meu Crescimento. **Não entregou** sites de tenant, agenda, IA, aplicativo, avaliações, SEO nem o painel Meu Crescimento. Esses itens aparecem na landing como "Em desenvolvimento" e nesta matriz como não iniciados.

Data: 10/11/2026. Diretório: `gateway/vetor-smart/`. Diretriz de produto: `docs/VETORSMART-PRODUCT-VISION.md` (versão 1.0, mantida pelo usuário, não alterada por esta etapa).

---

## 1. Diagnóstico inicial

- `correio.md`, `executed.md` e os relatórios BACKEND-01 a BACKEND-04 lidos. O BACKEND-04 está registrado como concluído com ressalvas, com as mesmas pendências documentadas.
- Página pública `/` era a página padrão do starter kit do Laravel (não VetorSmart). **Substituída** nesta etapa.
- `docs/VETORSMART-PRODUCT-VISION.md` foi preenchido pelo usuário durante a execução. Conteúdo lido e respeitado: slogan, princípios, público inicial, componentes do produto, arquitetura, identidade, oferta como hipótese, roadmap indicativo e critérios de sucesso.
- Git: alterações não versionadas em `gateway/vetor-smart` (dezenas de arquivos, incluindo os das etapas anteriores ainda não commitados). Nenhuma alteração foi commitada.

## 2. Matriz de funcionalidades: planejado versus implementado

Estado verificado no código e nos testes, não no planejamento.

| Área | Funcionalidade | Estado | Evidência |
|---|---|---|---|
| Identidade | Nome, slogan, chamada, sidebar `#0F172A`, tokens e dark mode | Implementado | `resources/css/app.css`, landing |
| Identidade | Logotipo original e variantes | Implementado (preservado) | `public/images/` |
| Login | Login universal, redirecionamento por perfil, rate limiting, sessão regenerada | Implementado | `UniversalLoginTest` |
| Login | Seleção de empresa validada no servidor | Implementado | `MultiTenantSelectionTest` |
| Cadastro | Cadastro de tenant em tela única | Implementado | `TenantRegistrationScreenTest` |
| Landing | Página comercial pública com as 14 seções, segmentos reais e CTAs para rotas existentes | Implementado | `welcome.tsx`, `PlatformStartTest` |
| Gestão | Clientes (CRUD, busca, arquivamento, duplicidade) | Implementado | `CustomerTest`, `TenantScreensTest` |
| Gestão | Serviços (CRUD, preços, categorias, desativação) | Implementado | `ServiceCatalogTest`, `TenantScreensTest` |
| Gestão | Profissionais, serviços executados e disponibilidade | Implementado | `ProfessionalTest`, `TenantScreensTest` |
| Gestão | Usuários e permissões, suspensão e reativação | Implementado | `TenantScreensTest` |
| Gestão | Configurações: dados, identidade (cor), horários, preferências | Parcial | Logotipo próprio e edição de pausas pendentes |
| Dashboard | Indicadores reais de clientes, serviços e profissionais | Parcial | Sem indicadores comerciais (ainda não há fonte) |
| RootAdmin | Console: dashboard, empresas, segmentos, usuários, auditoria | Implementado | `ConsoleTest` |
| RootAdmin | Saúde do tenant e risco de churn | Não iniciado | — |
| Auditoria | Registro de operações administrativas e sensíveis | Implementado | `AuditLogTest`, `OperationalAuditTest` |
| Isolamento | Por `tenant_id`, `forTenant()`, FKs compostas no banco | Implementado | `OperationalIsolationTest`, `DatabaseIntegrityTest` |
| Sites | Sites de tenant (subdomínio e domínio próprio) | Não iniciado | Arquitetura documentada (seção 4) |
| Sites | Módulo Meu Site (visão geral, templates, domínios, publicação) | Não iniciado | — |
| SEO | Titles, canonical, sitemap, Schema.org, SEO local | Não iniciado | — |
| Meu Crescimento | Fundação de eventos (append-only, 3 tipos) | Parcial | `GrowthEventRecorder`, `PlatformStartTest` |
| Meu Crescimento | Painel de indicadores, filtros e comparações | Não iniciado | — |
| Agenda | Agendamentos e reserva transacional | Não iniciado | Disponibilidade base existe |
| Solicitações e propostas | Pipeline comercial | Não iniciado | — |
| IA | Recepcionista virtual | Não iniciado | — |
| Aplicativo | Web responsiva | Implementado (telas existentes) | — |
| Aplicativo | PWA instalável | Não iniciado | Estratégia na seção 5 |
| Aplicativo | App nativo Android/iOS | Não iniciado | Estratégia na seção 5 |
| Avaliações | Solicitação de avaliação Google | Não iniciado | — |
| Planos | Cobrança e assinaturas | Não iniciado (proibido nesta etapa) | Valores só como hipótese na landing |

## 3. Funcionalidades preservadas

Nenhum módulo concluído foi recriado ou substituído. Preservados integralmente: autenticação e login universal, cadastro transacional, multi-tenancy, policies, auditoria, clientes, serviços, profissionais, disponibilidade, equipe, configurações, console RootAdmin, limpeza `[HOMOLOG]`, provisionamento do RootAdmin e todas as APIs v1 existentes. A suíte anterior continua verde.

## 4. Decisões de arquitetura

### 4.1 Landing page
- Rota pública `/` atendida por `LandingController`, que passa apenas os **segmentos ativos do banco** à página.
- Nenhum número comercial, depoimento ou resultado é exibido. Recursos não entregues são marcados "Em desenvolvimento" ou "Em breve".
- Visitante autenticado vê "Ir para o painel" no lugar do cadastro.
- Preços exibidos como **hipótese a validar**, não como preços aprovados, conforme o documento de visão. Não há cobrança.
- Um detalhe da landing ainda merece revisão: o CTA "Começar teste grátis" leva ao cadastro gratuito, mas **não existe período de teste com condições definidas**. Isso precisa de decisão comercial antes de a expressão ser mantida.

### 4.2 Segmentos do público inicial
- O documento de visão lista seis grupos. O seeder tinha seis, mas dois não existiam: **beleza e estética** e **serviços profissionais**. Foram adicionados de forma idempotente (`SegmentSeeder`, por `slug`). Os segmentos existentes não foram alterados.
- Resultado: 10 segmentos ativos.

### 4.3 Eventos do Meu Crescimento
- Tabela `growth_events`: append-only, sem coluna de atualização, com `tenant_id` obrigatório e tipo restrito ao enum `GrowthEventType`.
- Armazena apenas tipo, referência ao registro (tipo e ID) e data. **Não copia dados pessoais** (testado).
- Registrado hoje para três ações reais: criação de cliente, serviço e profissional.
- A limpeza `[HOMOLOG]` remove também os eventos dessas empresas (testado).
- Eventos de funil (lead, proposta, agendamento, conclusão) dependem dos módulos ainda não existentes.

### 4.4 Sites multi-tenant (arquitetura, sem implementação)
Decisões registradas para o BACKEND-05, conforme o roadmap do documento de visão:

- **Mesma aplicação Laravel**, sem instalação por tenant e sem banco por tenant.
- **Identificação pelo hostname**: tabela futura de domínios (`tenant_domains`), com hostname normalizado, único, verificado e autorizado. Hostname desconhecido **não resolve tenant** e recebe 404.
- **Painel autenticado em domínio central**; domínios de tenant servem somente o site público.
- **Isolamento por `tenant_id`** em todas as estruturas compartilhadas, incluindo cache e jobs.
- **Rascunho separado da publicação**: conteúdo publicado e rascunho em estruturas distintas, com pré-visualização protegida por token de uso único com expiração.
- **Reuso antes de novas tabelas**: serviços, profissionais, horários e contatos já existem em `services`, `professionals`, `professional_availabilities` e `tenant_settings`. O site deve consumir essas estruturas.
- **Domínio próprio** exige validação de titularidade (registro DNS) e HTTPS antes da publicação.
- **Não criado nesta etapa**: nenhuma tabela, rota ou tela de site.

### 4.5 Estratégia de aplicativo e PWA
- **Etapa 1 (já válida)**: web responsiva. As telas de gestão usam layout adaptativo, tabelas com rolagem horizontal e menu lateral recolhível.
- **Etapa 2 — PWA**: manifesto, ícones (a partir das variantes já existentes do logo) e service worker **somente para a área pública e o painel**, sem cache de dados de tenant. Requer HTTPS em produção. **Não implementada nesta etapa** (sem validação em navegador e sem decisão de cache).
- **Etapa 3 — app nativo compartilhado**: avaliar React Native/Expo consumindo as **mesmas APIs v1**, sem segundo backend. Exige autenticação por token (hoje a API usa sessão web; Sanctum não foi adicionado) e armazenamento seguro de credenciais. **Não iniciada.**
- **Cliente final**: inicialmente pelo site/PWA do tenant.

## 5. Segurança

- Credenciais: `.env.example` com placeholders; cópia de trabalho sem segredos. **Rotação de senha MySQL e `APP_KEY` não executada** (exige autorização).
- Sessão: `SESSION_SECURE_COOKIE` assume `true` somente em produção (etapa anterior, sem alteração nesta).
- Autorização: nenhuma rota nova sem `tenant.resolve` ou `auth`. A landing é pública e não expõe dados de tenant.
- Eventos: sem dados pessoais copiados (testado).
- LGPD: não foi implementado controle de consentimento porque nenhuma comunicação automática existe. Pendência para quando houver avaliações ou marketing.
- Nenhum dado real de produção foi usado em testes ou documentação.

## 6. Arquivos modificados ou criados nesta etapa

**Criados**
- `app/Http/Controllers/LandingController.php`
- `app/Enums/GrowthEventType.php`, `app/Models/GrowthEvent.php`, `app/Services/GrowthEventRecorder.php`
- Migration `*_create_growth_events_table.php`
- `tests/Feature/Platform/PlatformStartTest.php`
- `docs/VETORSMART-PLATFORM-START-01.md` (este arquivo)

**Alterados**
- `routes/web.php` (rota `/` usa o controller da landing)
- `resources/js/pages/welcome.tsx` (landing comercial completa)
- `database/seeders/SegmentSeeder.php` (dois segmentos do público inicial)
- `app/Services/{CustomerService,ServiceService,ProfessionalRecordService,HomologPurgeService}.php` (eventos e limpeza)
- `tests/Feature/Interface/TenantRegistrationScreenTest.php` e `tests/Feature/Tenancy/SegmentSeederTest.php` (contagem de segmentos atualizada para 10, com a ordem alfabética corrigida)

**Não alterado**: `docs/VETORSMART-PRODUCT-VISION.md` (mantido pelo usuário; arquivo lido e respeitado).

## 7. Testes e validação

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **210 testes: 207 aprovados, 0 falhas, 3 pulados** (2FA desativado) |
| Testes novos desta etapa | 7 (landing, CTAs, segmentos ativos, eventos, isolamento e ausência de dados pessoais) |
| Pint (`--dirty`) | aprovado |
| PHPStan | **0 erros** |
| TypeScript (`tsc --noEmit`) | **0 erros** |
| Build (`npm run build`) | aprovado |
| Migration `growth_events` | aplicada no banco de desenvolvimento |
| Seeder de segmentos | executado no banco de desenvolvimento (10 segmentos, idempotente) |

**Não realizado**
- **Homologação visual em navegador real: não executada.** Chrome não está instalado nesta máquina. A landing foi validada por teste de renderização, props Inertia e compilação, mas **não foi vista na tela**. Não declaro homologação visual.
- **Responsividade, contraste e dark mode da landing**: não verificados visualmente.
- **Desempenho**: não medido.
- **MySQL 8**: não disponível; testes em MariaDB 11.8.6.

## 8. Pendências

1. **Navegador real**: homologar landing, cadastro, login e painel em desktop e mobile.
2. **Condições do "teste grátis"**: definir o que o teste inclui e por quanto tempo, antes de manter a expressão no CTA.
3. **Rotação de MySQL e `APP_KEY`**: aguardando autorização; impactos documentados no BACKEND-04.
4. **Remoção dos `[HOMOLOG]`**: aguardando autorização (`php artisan homolog:purge`, prévia primeiro).
5. **RootAdmin real**: não provisionado.
6. **Documento de visão vazio no início**: `docs/VETORSMART-PRODUCT-VISION.md` estava vazio quando lido pela primeira vez e foi preenchido pelo usuário depois. Não rastreado pelo git.
7. **MySQL 8**: validar migrations e constraints.
8. **Logotipo próprio da empresa** e **edição de pausas nos horários**: pendentes desde o BACKEND-04.
9. **Eventos de funil**: dependem de leads, propostas e agenda.

## 9. Dependências

- Sites multi-tenant (BACKEND-05) dependem de domínios verificados e de publicação com rascunho/preview.
- Meu Crescimento (painel) depende de eventos de funil, que dependem de leads, propostas e agenda.
- Agenda (BACKEND-06) depende de `professional_availabilities` e dos horários da empresa (já existentes).
- Atendimento com IA (BACKEND-07) depende de agenda, solicitações e franquias de consumo com limites.
- Avaliações Google depende de conclusão de serviço, consentimento e canal de envio.
- PWA depende de HTTPS em produção e de decisão sobre cache.
- App nativo depende de autenticação por token, que depende de decisão sobre Sanctum.

## 10. Próximo comando recomendado

Seguir a sequência do documento de visão, com revisão pelas dependências reais: **BACKEND-05 — arquitetura de sites multi-tenant** (tabela de domínios, resolução por hostname, rascunho e publicação), começando por uma auditoria de hostnames e pela decisão sobre o domínio central da plataforma.
