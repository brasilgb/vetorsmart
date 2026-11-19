# VETORSMART-PORTAL-01 — Núcleo funcional do Portal Regional

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy, VPS ou DNS.
- Sem Efí nem chamadas financeiras.
- Sem MFA.
- Sem dependências novas.
- Sem marketplace nem pagamentos.

## Parecer

**Concluída.** Os 10 critérios de aceite foram atendidos:

| # | Critério | Situação |
|---|---|---|
| 1 | Portal responde localmente | `portal.vetorsmart.test` (configurável) responde 200 no servidor local; testes por host, sem DNS |
| 2 | Busca por serviço e região | Serviço, município, UF, categoria e nome, com normalização de acentos, caixa e espaços |
| 3 | Só prestadores elegíveis | Elegibilidade conferida em toda consulta (seção 3) |
| 4 | Participação controlada pelo assinante | Aba "Portal Regional" em "Meu Site", desligada por padrão e com efeito imediato |
| 5 | Ordenação sem privilégio comercial | Critérios transparentes e desempate neutro com rotação diária (seção 4) |
| 6 | Consumidor abre o site do prestador | "Ver site" e "Solicitar atendimento" levam ao site da empresa |
| 7 | Solicitação com origem Portal Regional | `RequestOrigin::Portal` ("Portal Regional"), pelo formulário da WEBSITES-10 |
| 8 | Isolamento multi-tenant | Empresa vem sempre do endereço; o portal lê só a versão publicada; testes de exclusão |
| 9 | Desktop e celular | 30 de 30 verificações visuais sem problemas (celular, tablet e desktop; claro e escuro) |
| 10 | Testes aprovados e limitações documentadas | 13 novos, 441 nos grupos afetados; limitações na seção 9 |

## 1. Consolidação anterior

- A WEBSITES-10 foi consolidada em `3c7fd7c` (diff conferido; sem `correio.md`, `executed.md`, credenciais ou temporários).
- Os testes dela não foram repetidos à parte; rodaram apenas nos grupos afetados por esta etapa.

## 2. Inspeção e arquitetura

| Item | Encontrado | Decisão |
|---|---|---|
| Resolução de hosts | `TrustedHosts` aceita `*.{tenant_base_domain}`. O grupo `Route::domain('{siteHost}')` tratava **todo** host não central como possível empresa. `SiteHostGuard` responde 404 fora das páginas do site | O portal ganhou **host próprio** (`platform.portal_host`, padrão `portal.{tenant_base_domain}`), com rotas registradas **antes** das empresas. O padrão `{siteHost}` passou a excluí-lo, e o `SiteHostGuard` aceita nele só `/`, `/robots.txt` e `/sitemap.xml` |
| Reserva de `portal` | **Não estava reservado:** uma empresa poderia reivindicar `portal.{domínio}` | Incluído em `reserved_subdomains`, o que cobre a escolha manual e a geração automática (testado). Não havia domínio `portal.*` na base local |
| `TenantResolver` | Resolve a empresa do **usuário logado**; não participa do portal | Mantido. O portal não usa sessão de empresa |
| Segmentos e categorias | `segments` (11 ativos localmente); `service_categories` é por empresa | As categorias do portal são os **segmentos ativos** |
| Municípios e regiões | Não existe tabela de municípios. Há cidade e UF em `tenant_settings` e região em texto livre no Atendimento IA | O portal usa as cidades e UFs **publicadas** e a região atendida. A sugestão de cidades vem das empresas listadas. Sem geolocalização paga e sem distância em km |
| Sites e versões | `website_versions` imutáveis; a versão publicada é a fonte pública | O índice do portal é montado **só** da versão publicada |
| Solicitações do site | Formulário `/solicitar` (WEBSITES-10), origem `public_site` | Reutilizado. A visita vinda do portal leva `?origem=portal`, e a solicitação recebe a origem `portal` |

Não há cadastro duplicado nem arquitetura paralela. O índice é derivado e recriável.

## 3. Elegibilidade e visibilidade

Uma empresa só aparece quando, **na hora da consulta** (`PortalSearch::eligible`):

1. a empresa está ativa e **não é de homologação**;
2. o segmento está ativo;
3. o site está publicado e a versão indexada é a **atual**: uma versão antiga nunca aparece;
4. ela **autorizou** a participação (`tenant_websites.portal_listed`, desligado por padrão);
5. com a cobrança em vigor (`BillingEnforcement`), a assinatura permite acesso. A regra é a mesma do painel: uma empresa sem assinatura mantém o acesso. Com a cobrança desligada, como hoje, nenhuma empresa é excluída por isso (testado nos dois estados);
6. a versão publicada tem nome comercial, cidade e UF válida (sem isso, não há entrada no índice);
7. oferece o serviço buscado (palavras nos serviços publicados ou no segmento) e atende a região buscada (cidade igual à da empresa ou citada na região atendida; UF igual).

**Fora do portal:** rascunhos, telefones, e-mails, histórico, dados internos e financeiros. O índice não guarda telefone nem e-mail (testado). O painel explica por que a empresa ainda não aparece; por exemplo, "Publique o site", "Informe a cidade e a UF" ou "Empresas de teste não aparecem".

## 4. Ordenação justa (algoritmo)

Nenhum critério comercial é lido: nem plano, nem créditos, nem pagamentos, nem anúncios.

| Ordem | Critério | Pontos |
|---|---|---|
| 1 | Compatibilidade com o serviço | 2 se todas as palavras buscadas estão nos **nomes dos serviços publicados**; 1 se só no segmento; 0 sem busca por serviço |
| 2 | Região | 2 se a cidade buscada é a da empresa; 1 se aparece só na região atendida; 0 sem cidade |
| 3 | Informações públicas | 0 a 3: logotipo, descrição e pelo menos um serviço (limitado a 3, sem vantagem por quantidade) |
| 4 | Desempate | `CRC32(tenant_id + ":" + data do dia)`: estável no mesmo dia (a paginação não embaralha) e diferente a cada dia |
| 5 | Final | `portal_listings.id`, só para tornar a ordem determinística |

Os testes comprovam que a ordem é estável no mesmo dia, que muda entre dias, que o primeiro lugar alterna e que a ordem não é alfabética. O texto "Sobre o portal" explica o critério ao visitante.

## 5. Funcionalidades implementadas

**Página do portal** (`portal/index.blade.php`, Blade sem JavaScript de framework)
- Identidade VetorSmart, campo "Qual serviço você precisa?", município (com sugestões), UF, nome da empresa (opcional), categorias, resultados em cartões, paginação, estado inicial, "nenhum resultado" e "revise a busca".
- Estado de carregamento ("Buscando…", `aria-busy` e aviso para leitor de tela).
- Tema claro e escuro, link "Ir para os resultados" e navegação por teclado.

**Cartões de resultado**
- Nome comercial, logotipo publicado (ou inicial), segmento, cidade e UF, resumo, região atendida e até 6 serviços (+N).
- **Ver site** e **Solicitar atendimento**, que já escolhe o serviço buscado quando ele está entre os publicados.
- Sem avaliações, notas, contagens ou disponibilidade inventadas.

**Paginação e limites**
- No servidor, 12 por página, no máximo 50 páginas.
- Parâmetros validados (texto até 80 caracteres, UF da lista, categoria em formato de slug). Um valor inválido responde 422 com a mensagem e sem listar nada.
- Limite de 60 consultas por minuto por IP (`portal-search`).

**SEO**
- A página inicial sem filtros é indexável, com canonical, Open Graph, `robots.txt` e `sitemap.xml` próprios do host do portal.
- Páginas com filtros saem com `noindex, follow`, para não gerar páginas finas indexáveis.

**Participação em "Meu Site"**
- Nova aba **Portal Regional**: o que aparece e o que nunca aparece, situação, motivos de não aparecer, "Participar do portal" e "Sair do portal" (com confirmação), e link para o portal.
- Efeito imediato e registro em auditoria (`website.portal_listed` / `website.portal_unlisted`).
- Só proprietário e administrador alteram; o operador recebe 403.

**Sincronização do índice:** `PortalDirectory::sync` roda após publicar (depois do commit), retirar do ar e mudar a participação.

**Origem Portal Regional**
- `?origem=portal` segue do cartão para o site, para os links internos (início, serviços e solicitar) e para o campo oculto do formulário.
- A solicitação é gravada com `RequestOrigin::Portal`; sem isso, continua `public_site`, sem quebrar relatórios anteriores. Um valor de origem fora de `portal` é recusado (testado).
- A empresa vem **só do endereço**. CSRF, limite, campo-armadilha, validações e confirmação após gravar são os da WEBSITES-10.

**CSP:** `img-src` passou a aceitar também a origem pública da plataforma (`platform.public_url`), porque o portal, em outro host, exibe os logotipos publicados servidos por ela.

## 6. Rotas criadas

| Método | Rota | Nome |
|---|---|---|
| GET | `{portal_host}/` | `portal.home` (throttle `portal-search`) |
| GET | `{portal_host}/robots.txt` | `portal.robots` |
| GET | `{portal_host}/sitemap.xml` | `portal.sitemap` |
| PATCH | `/meu-site/portal` | `tenant.website.portal` |

## 7. Estrutura de dados e migration

**Nova migration (executada apenas no banco local):** `2026_11_19_144905_create_portal_listings_table`.

1. **`tenant_websites`:** recebe `portal_listed` (boolean, padrão `false`) e `portal_listed_at`. Nenhuma empresa existente passa a aparecer sem escolher.
2. **`portal_listings`:** índice de busca, uma linha por empresa que aderiu, montada só da versão publicada.
   - Colunas: `tenant_id` (único), `website_version_id`, `segment_id`, nome, cidade, UF, região, resumo, serviços (`{id, name}`), logotipo e completude, além das colunas normalizadas `*_search`.
   - Índices: (`state`, `city_search`) e `segment_id`.
   - Chaves estrangeiras com `cascadeOnDelete`, e `nullOnDelete` no segmento.

**Impacto:** a tabela é derivada e recriada pela adesão e pela publicação, sem preenchimento retroativo; as empresas aparecem só depois de aderir. Na implantação, passam a ser **19 migrations pendentes**.

## 8. Arquivos

**Criados:**
- `app/Http/Controllers/Portal/PortalController.php`
- `app/Services/Portal/PortalText.php`
- `app/Services/Portal/PortalDirectory.php`
- `app/Services/Portal/PortalSearch.php`
- `app/Models/PortalListing.php`
- `database/migrations/2026_11_19_144905_create_portal_listings_table.php`
- `resources/views/portal/index.blade.php`
- `tests/Feature/Websites/RegionalPortalTest.php`

**Alterados:**
- `config/platform.php` (`portal_host`, `portal` reservado)
- `routes/web.php` (grupo do portal; `{siteHost}` sem o portal)
- `routes/tenant.php`
- `SiteHostGuard`
- `TrustedHosts`
- `ContentSecurityPolicy` (`img-src`)
- `AppServiceProvider` (`portal-search`)
- `TenantWebsite`
- `WebsiteService` (`setPortalListing`, sincronização)
- `Tenant/WebsiteController` (ação e dados da aba)
- `RequestOrigin` (`Portal`)
- `SiteController` (propagação de `origem`)
- `SiteRequestController` e `StoreSiteServiceRequest` (origem)
- `website/site.blade.php` e `website/solicitar.blade.php`
- `tenant/website/index.tsx` (aba Portal Regional)

## 9. Evidências de testes

| Verificação | Resultado |
|---|---|
| `RegionalPortalTest` (novo) | **13 de 13**, 81 asserções (detalhes abaixo) |
| Grupos afetados (`Websites`, `Assistant`, `Operations`, `Tenancy`, `Platform`) | **441 testes, 432 aprovados**; os 9 restantes são de concorrência, que exigem `VETORSMART_CONCURRENCY=1` |
| PHPStan / Pint / TypeScript / build | 0 erros / ok / ok / ok |
| Portal no servidor local (host mapeado só no navegador do teste) | `/` → 200; `/meu-site` → 404 |
| Visual: estados com resultados, nenhum resultado e erro (view real com dados de exemplo) e portal real ao vivo (início e busca) | **30 de 30** (celular 390 px, tablet 820 px e desktop 1440 px; claro e escuro): sem violação de axe WCAG 2.1 AA, sem rolagem horizontal, deslocamento de layout ≤ 0,1, sem erros de JS. Teclado: link de salto, busca, filtros e categorias em sequência. Um erro de contraste da mensagem de campo foi encontrado e corrigido |
| "Meu Site" no painel (conta local de homologação) | **4 de 4** sem problemas |

O que os 13 testes de `RegionalPortalTest` cobrem:
- host próprio e paths fechados, além de robots, sitemap e a reserva de `portal`;
- busca por serviço com acentos, caixa, espaços e plural;
- cidade e UF: a cidade vem antes da região e outra UF não aparece;
- filtros combinados;
- elegibilidade: sem adesão, rascunho, suspensa, homologação, sem cidade e retirada do ar;
- assinatura com a cobrança ligada e desligada;
- só a versão publicada, sem telefone nem e-mail;
- ordem neutra, estável no dia e com rotação entre dias;
- paginação, limite de página e validação;
- `noindex` nos resultados e links com `origem=portal`;
- solicitação com origem Portal Regional na empresa e no serviço certos, com origem forjada recusada;
- adesão em "Meu Site" com efeito imediato e operador sem permissão;
- mesmo número de consultas para 1 ou 12 resultados (sem N+1).

**Não foram executadas** a suíte completa e as homologações antigas. Também não criei empresas nem dados de demonstração no banco local: a verificação visual com resultados usou a view real renderizada com dados de exemplo.

## 10. Limitações

1. **DNS e certificado:** `portal.vetorsmart.com.br` exige registro DNS e certificado na implantação (fora desta etapa). Hoje o host responde só localmente.
2. **Região:** sem tabela oficial de municípios, a correspondência usa a cidade publicada e o texto livre da região atendida. Uma grafia diferente na região, como abreviações, pode não casar.
3. **Busca:** usa `LIKE` sobre colunas normalizadas, adequado ao volume atual. Com milhares de empresas, convém índice de texto completo.
4. **Origem:** `origem=portal` é atribuição declarada pelo visitante, que pode copiar o link. Ela nunca altera a empresa nem o serviço.
5. **Sites publicados antes da WEBSITES-10:** não têm o `id` dos serviços, então o botão do cartão não escolhe o serviço automaticamente até a empresa republicar.
6. **Rede real:** o portal não foi aberto com empresas reais no navegador, porque a única conta local é de homologação, e empresas de teste não aparecem no portal por regra.
7. **Aba "Portal Regional":** coberta por testes de feature; a varredura visual automática do painel abre só a visão geral de "Meu Site".

## 11. Estado do Git

- `3c7fd7c`: WEBSITES-10.
- **PORTAL-01 sem commit**, conforme o correio.
- `correio.md` e `executed.md` fora dos commits.
- **Sem push e sem deploy.**

## 12. Pendências para a PORTAL-02

1. Redesign do portal (hero, categorias com ícones, cartões e estados), mantendo as regras desta etapa.
2. Páginas indexáveis por categoria e cidade (`/{categoria}/{cidade}`), só quando houver empresas suficientes, para evitar páginas vazias.
3. Normalização de regiões atendidas, com lista estruturada de municípios por empresa no painel em vez de só texto livre.
4. Métricas para o assinante: visitas e solicitações vindas do portal em "Meu Crescimento".
5. DNS e certificado de `portal.vetorsmart.com.br` na implantação, com as 19 migrations pendentes.
6. Avaliar índice de texto completo quando o volume crescer.
