# VETORSMART-PORTAL-02 — Experiência do consumidor e evolução visual

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy, VPS ou DNS.
- Sem Efí.
- Sem MFA.
- Sem dependências novas.
- **Sem migrations.**
- Landing comercial não alterada.
- Nenhuma avaliação, número ou depoimento fictício.

## Parecer

**Concluída.** O consumidor encontra o campo "Qual serviço você precisa?" logo no topo, informa município e UF, toca em "Encontrar profissionais", vê os filtros ativos, compara cartões só com dados públicos reais e segue para "Solicitar atendimento" já com o serviço e a origem preservados até o formulário da empresa.

## 1. Consolidação

- A PORTAL-01 foi consolidada em `92d5afb` (diff revisado; sem `correio.md`, `executed.md`, segredos ou temporários).
- O comportamento operacional aprovado foi preservado, e os 13 testes da PORTAL-01 continuam passando.

## 2. Funcionalidades implementadas

### 2.1 Identidade visual (`portal/index.blade.php` e `portal/partials/cards.blade.php`)

- Paleta da marca VetorSmart, a mesma do painel (azul `#1D4ED8`, navy `#0F172A`, textos `#0B1220`/`#475569`), com tema escuro equilibrado por `prefers-color-scheme`.
- Tipografia do sistema (sem fonte externa), hierarquia h1 → h2 → h3, espaçamento consistente, cantos de 12 a 16 px e sombra apenas no cartão de busca.
- **Ícones padronizados:** traço de linha único (1,8 px) inline, sem biblioteca, para busca, local, filtro, fechar, confirmação e categorias. Os ícones das categorias reaproveitam o `SegmentTheme` da WEBSITES-10.
- Sem gradientes, animações ou elementos decorativos.

### 2.2 Página inicial

1. **Cabeçalho:** logotipo VetorSmart, "Portal Regional" e link "Como funciona".
2. **Busca em destaque:** "Qual serviço você precisa?", **Município** (com sugestões das cidades das empresas participantes) e **UF**, além do botão **"Encontrar profissionais"**.
3. **"Mais filtros"** (recolhível): categoria e nome da empresa. Abre sozinho quando um desses filtros está ativo.
4. **Categorias de serviços:** só as que têm empresas participantes, para que nenhuma leve a uma lista vazia.
5. **Profissionais no portal:** 6 empresas pela ordenação neutra da PORTAL-01 (rotação diária) e o link "Ver todos os profissionais" (`/?pagina=1`, não indexável). Sem participantes, aparece o aviso "As primeiras empresas estão chegando", sem números inventados.
6. **Como funciona:** três passos e o critério de ordenação explicado ao consumidor, deixando claro que o portal não intermedeia pagamentos.
7. **Rodapé institucional.** Não há chamadas de venda de assinatura.

### 2.3 Pesquisa e filtros

- **Busca:** é a mesma da PORTAL-01 (`PortalSearch`), sem lógica duplicada no frontend. Ela recebeu dois acréscimos: tamanho de página opcional (para os 6 da inicial) e `categoriesWithProviders()`.
- **Filtros ativos:** aparecem como chips ("Serviço: …", "Município: …", "UF: …", "Categoria: …", "Empresa: …"). Cada chip remove só o seu filtro (`aria-label="Remover filtro …"`), e o link **"Limpar filtros"** remove todos.
- **URL:** a busca fica no endereço. No envio, campos vazios saem da URL, então o link compartilhado leva só os filtros escolhidos. Uma UF em minúsculas é aceita.
- **Sem resultado:** em vez de beco sem saída, o portal sugere ampliar a busca, removendo uma restrição por vez: "Buscar sem o nome da empresa", "Ver todo o estado (SP)", "Ver todos os profissionais de {categoria}" e "Ver todos os profissionais do portal".
- **Mensagens de erro claras:** "Escolha uma UF da lista.", "Descreva o serviço com até 80 caracteres." e "Refine a busca para ver mais resultados." Os campos inválidos ficam com `aria-invalid` e `aria-describedby`.
- **Carregamento:** o botão mostra "Buscando…", o formulário recebe `aria-busy` e há um aviso para leitor de tela. Sem geolocalização automática.

### 2.4 Cartões dos prestadores

- **Dados exibidos:** logotipo publicado; sem ele, um **placeholder elegante** com a cor e o ícone do segmento. Também nome comercial, atividade, município e UF, resumo, região atendida e até 5 serviços (+N).
- **Serviço correspondente:** quando a busca casa com um serviço publicado, o cartão destaca **"Oferece: {serviço}"**.
- **Botões:** **"Solicitar atendimento"** (principal) e **"Ver profissional"**, com área de toque de 48 px. Ficam empilhados em telas estreitas e em grade de três colunas, e lado a lado em cartões largos.
- **O que nunca aparece:** estrelas, notas, contagens, disponibilidade, telefone e e-mail.

### 2.5 Integração com o fluxo operacional

- **Contexto do serviço:** "Ver profissional" e "Solicitar atendimento" levam `?origem=portal` e, se houver serviço correspondente, `&servico={id}`.
- **Site da empresa:** o botão principal "Solicitar atendimento" do site pré-seleciona o serviço vindo do portal **somente se ele estiver publicado por aquela empresa**. Um `servico` de outra empresa é ignorado e nunca define a empresa (testado). A empresa continua vindo só do endereço.
- **Preservado (testes da PORTAL-01 passando):** host `portal`, elegibilidade, participação voluntária, ordenação neutra, versões imutáveis, isolamento, origem "Portal Regional", e o fluxo de solicitação e agendamento.

### 2.6 SEO e acessibilidade

- **Títulos por estado:** "Encontre profissionais na sua região…", "{Serviço} em {Município} - {UF}…", "{Categoria}…" e "Todos os profissionais…". A descrição e o Open Graph usam o logotipo VetorSmart.
- **Indexação cuidadosa:**

| Página | Indexável | Canonical / sitemap |
|---|---|---|
| Inicial sem filtros | sim | canonical próprio, com JSON-LD `WebSite` e `SearchAction` |
| Só `?categoria=` (1ª página, com empresas) | sim | canonical próprio; entra no sitemap |
| Categoria sem empresas, filtros combinados, busca livre, `/?pagina=N`, erro | **não** (`noindex, follow`) | fora do sitemap |

  O sitemap lista a inicial mais, no máximo, uma URL por segmento com empresas (hoje, até 11).
- **Semântica:** `header`, `nav`, `main`, `section` com `aria-labelledby`, `article` por empresa, `ol` de passos, `footer`, link "Ir para o conteúdo", foco visível de 3 px e uma ordem de tabulação lógica.

### 2.7 Desempenho

- Sem dependências; CSS inline (cerca de 8 KB) e JavaScript inline mínimo (estado de carregamento), com nonce de CSP.
- Logotipos com `loading="lazy"` e `width`/`height`.
- **Consultas:** paginação no servidor, e a proteção contra N+1 da PORTAL-01 continua testada. A inicial faz duas consultas a mais (destaques e categorias com empresas).

## 3. Arquivos modificados

- `app/Http/Controllers/Portal/PortalController.php` (estados da página, filtros ativos, sugestões, indexação por categoria, sitemap, títulos e `/?pagina=N`)
- `app/Services/Portal/PortalSearch.php` (`perPage` e `categoriesWithProviders`)
- `app/Http/Controllers/Site/SiteController.php` e `resources/views/website/site.blade.php` (serviço pré-selecionado vindo do portal)
- `resources/views/portal/index.blade.php` (nova interface)
- `resources/views/portal/partials/cards.blade.php` (novo)
- `tests/Feature/Websites/RegionalPortalTest.php` (+6 testes; textos atualizados em 3)
- `docs/evidencias/portal-02/*.png` (evidências visuais)

## 4. Evidências visuais

Em `docs/evidencias/portal-02/`:

- `m-light-inicio.png`: celular (390 px), inicial com categorias, profissionais e "Como funciona";
- `d-light-resultados.png`: desktop (1440 px), resultados com filtros ativos e "Mais filtros" aberto;
- `d-dark-resultados.png`: desktop em tema escuro;
- `m-light-vazio.png`: celular, nenhum resultado com sugestões.

As capturas foram geradas com a view real renderizada a partir de dados de exemplo, sem criar empresas no banco local.

## 5. Resultados dos testes diretamente afetados

| Verificação | Resultado |
|---|---|
| `RegionalPortalTest` | **19 de 19** (13 da PORTAL-01 e 6 novos, detalhados abaixo) |
| Grupos afetados (`Websites`, `Tenancy`, `Operations`) | **361 testes, 352 aprovados**; os 9 restantes são de concorrência, que exigem `VETORSMART_CONCURRENCY=1` |
| PHPStan / Pint / build | 0 erros / ok / ok |
| Visual: inicial, resultados, nenhum resultado e erro (view real), além do portal ao vivo no servidor local (início e busca) | **36 de 36** (celular 390 px, tablet 820 px e desktop 1440 px; claro e escuro), sem violação de axe WCAG 2.1 AA, sem rolagem horizontal, deslocamento de layout ≤ 0,1, sem erros de JS |
| Teclado | Link de salto → marca → "Como funciona" → serviço → município → UF → "Encontrar profissionais" → "Mais filtros" → categoria |

Os 6 testes novos cobrem:
- inicial com elegíveis, categorias só com empresas, "Como funciona", indexável e JSON-LD;
- 6 em destaque, "Ver todos" e lista completa não indexada;
- filtros ativos removíveis um a um, "Limpar filtros" e "Oferece:";
- sugestões quando não há resultado;
- indexação e sitemap apenas para a página de categoria com empresas;
- placeholder sem logotipo, serviço e origem preservados até o botão do site, e serviço de outra empresa ignorado.

**Cobertura dos itens 1 a 10 do correio:**
- Pesquisa no desktop e no celular, temas, teclado: visual automatizado.
- Filtros, limpeza, ausência de resultados, abertura do site, serviço e origem: testes de feature.
- Elegibilidade e isolamento: testes da PORTAL-01, sem regressão.

A suíte completa não foi executada.

## 6. Problemas identificados e corrigidos

1. **Botões do cartão:** em três colunas no desktop, os dois botões lado a lado quebravam em duas linhas. Agora ficam empilhados e só ficam lado a lado em cartões largos.
2. **Chip de categoria:** "Climatização" estourava a largura mínima do chip. A largura foi aumentada e a quebra de texto, permitida.
3. **Ver site:** antes não levava o serviço buscado. Agora leva, e o botão principal do site o pré-seleciona.

## 7. Limitações

1. **Páginas de categoria:** usam `?categoria=` (URL com parâmetro), estáveis e no sitemap. URLs amigáveis por categoria e cidade ficam para depois, com volume que justifique.
2. **Sugestões de cidade:** vêm só das empresas participantes; não há base oficial de municípios.
3. **Tema escuro:** segue o sistema do visitante, sem botão de troca no portal.
4. **Logotipos reais:** no portal, não foram vistos com empresas reais, porque a única conta local é de homologação e não aparece por regra.
5. **"Ver todos os profissionais":** com centenas de empresas, a lista completa precisará de filtros regionais mais fortes.

## 8. Estado do Git

- `92d5afb`: PORTAL-01.
- **PORTAL-02 sem commit**, conforme o correio.
- `correio.md` e `executed.md` fora dos commits.
- **Sem push e sem deploy.**
- 19 migrations continuam pendentes para a implantação (nenhuma nova nesta etapa).

## 9. Pendências para a PORTAL-03

1. URLs amigáveis e indexáveis por categoria e cidade (`/c/{categoria}/{cidade}`), somente com empresas suficientes e conteúdo próprio.
2. Região atendida estruturada (lista de municípios por empresa no painel), melhorando a busca regional.
3. Métricas para o assinante: visualizações no portal e solicitações com origem "Portal Regional" em "Meu Crescimento".
4. Sistema legítimo de avaliações, com verificação de atendimento, antes de exibir qualquer nota.
5. DNS e certificado de `portal.vetorsmart.com.br` na implantação, com as migrations pendentes.
6. Botão de troca de tema e preferência salva no portal.
