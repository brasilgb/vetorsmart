# VETORSMART-PORTAL-03 — Navegação regional, SEO e refinamento da experiência

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy, VPS ou DNS.
- **Sem migrations.**
- Sem Efí, cobrança, planos ou assinaturas.
- Sem MFA.
- Sem dependências.
- Isolamento entre tenants e agendamentos não alterados.

**Consolidação da PORTAL-02:** commit `5b8c601`.

## Parecer técnico

**Concluída.** Os 10 critérios de aceite foram atendidos:

- o portal tem endereços amigáveis por categoria e região, com uma única URL por lista;
- o SEO é regional e sem páginas artificiais: só existem páginas onde há empresa participante sediada;
- a busca e os filtros da PORTAL-02 continuam funcionando, com a escolha de região mais simples;
- há tema claro, escuro e automático persistido no navegador;
- o encaminhamento ao site preserva serviço e origem;
- a neutralidade foi mantida.

## 1. Funcionalidades implementadas

### 1.1 URLs amigáveis

| Rota | Conteúdo |
|---|---|
| `/` | Página inicial e buscas por query string |
| `/categorias/{categoria}` | Profissionais de uma categoria |
| `/regioes/{uf}` | Profissionais de um estado, com os municípios e categorias desse estado |
| `/regioes/{uf}/{municipio}` | Profissionais de um município |
| `/regioes/{uf}/{municipio}/{categoria}` | Categoria em um município |

**Identificador estável do município, sem migration e sem tabela de municípios:**
- É a **UF mais o nome normalizado** da cidade publicada pela empresa (`sao-paulo`, `sao-joao-del-rei`), com a mesma normalização da busca da PORTAL-01 (`PortalText::citySlug`).
- Grafias que diferem só em acentos, caixa ou pontuação compartilham a página (testado: "São Paulo" e "sao paulo").
- Homônimos de estados diferentes têm páginas distintas (testado: Campinas-SP e Campinas-MG).

**Município existe só onde há empresa participante sediada.** Município citado apenas na região atendida, empresa não participante, categoria, UF ou município inexistentes: **404**, com a página do portal ("Não há empresas participantes neste endereço"), `noindex` e caminhos de volta.

**Compatibilidade e não duplicação**

| Endereço de entrada | Comportamento |
|---|---|
| `/?categoria=x` (links da PORTAL-02) | **301** para `/categorias/x` |
| `/?uf=SP` | **301** para `/regioes/sp` |
| `/?uf=SP&cidade=Campinas&categoria=x&pagina=2` (formulário) | **301** para `/regioes/sp/campinas/x?pagina=2` |
| `/regioes/SP/...` (UF em maiúscula) | **301** para minúsculas |
| `?q=`, `?nome=`, UF + categoria sem município, município sem empresas | Continuam por query string em `/`, sempre `noindex, follow` |

O destino dos redirecionamentos é montado com o host do portal, e não com o `Host` da requisição. Os filtros por query string da PORTAL-01 e da PORTAL-02 continuam aceitos.

**Isolamento de rotas**
- As rotas existem só no host `portal`. No domínio central, `/regioes/sp` e `/categorias/x` respondem 404 (testado).
- `regioes` e `categorias` ficam automaticamente reservados como slug de segmento (`ReservedPaths`, testado).
- O `SiteHostGuard` aceita no host do portal só esses formatos de caminho. Um nível a mais responde 404.

### 1.2 SEO regional

| Item | Implementação |
|---|---|
| Títulos | "Climatização em Campinas - SP", "Profissionais em São Paulo", "Climatização" (mais o sufixo do portal) |
| Descrições | Específicas por categoria e região, sem números |
| Canonical | A própria URL amigável. Buscas por query string apontam para a inicial e são `noindex` |
| Breadcrumbs | `nav` "Você está em" (Início › São Paulo › Campinas › Climatização) com `aria-current`, e JSON-LD `BreadcrumbList` |
| Dados estruturados | Inicial: `WebSite` + `SearchAction`. Páginas amigáveis indexáveis: `CollectionPage` com `ItemList` das empresas da página (nome e site real). Nada em páginas `noindex` |
| Open Graph | Título, descrição e URL coerentes com a página |
| Indexação | Indexável só a primeira página de uma URL amigável **com empresas** e **sem filtros extras**, além da inicial. Todo o resto: `noindex, follow` |
| Sitemap | Inicial; categorias com empresas; UFs, municípios e município + categoria onde há empresa **sediada** (sem duplicatas, testado) |

Não há depoimentos, avaliações nem contagens inventadas. O total exibido é o número real de resultados.

### 1.3 Busca e descoberta regional

- **Ordem dos campos:** a UF vem antes do município. A UF lista só os estados com empresas (mais a UF já escolhida).
- **Sugestões de município:** são as cidades reais das empresas participantes e se filtram pela UF escolhida.
- **Termos preservados:** o serviço digitado e o nome da empresa acompanham os links de município e categoria da região.
- **Filtros removíveis um a um:** remover um filtro leva ao endereço amigável mais amplo (sem o serviço, vai para `/regioes/sp/campinas`; sem o município, para `/regioes/sp?q=…`). Remover a UF remove também o município.
- **Sem resultado:** o portal sugere alternativas que levam aos endereços amigáveis quando existem.
- **Empresas de outro município:** a página de um município mostra **primeiro as empresas sediadas nele** e identifica as demais com "Sediada em Valinhos - SP; atende Campinas conforme a região informada pela empresa." (testado).
- **Página de estado:** "Municípios em {estado}" e "Categorias em {estado}". **Página de município:** "Categorias em {município}". Na **inicial**, a seção "Explorar por região" mostra estados e municípios com empresas.
- **Ordenação e paginação:** sem mudança (neutra, determinística e estável no dia). A paginação mantém a URL da página.

### 1.4 Experiência visual e mobile

- **Desktop:** largura de até 1200 px e coluna lateral fixa (280 px) com municípios e categorias da região ao lado dos resultados. A linha de busca ficou alinhada.
- **Mobile:** busca em destaque, "Mais filtros" recolhível, cartões e botões com área de toque de 44 a 48 px.
- **Espaçamento:** foi corrigido o espaçamento do conteúdo principal, que a classe `.wrap` anulava desde a PORTAL-02.
- **Tema claro, escuro ou automático:**
  - grupo de botões no cabeçalho (`aria-pressed`; só os ícones no celular);
  - a escolha fica em `localStorage` e é aplicada antes da pintura, por script com nonce de CSP, sem piscar o tema;
  - o modo automático segue `prefers-color-scheme`;
  - sem JavaScript, o grupo não aparece e vale o modo automático;
  - as cores específicas do modo escuro passaram para variáveis (`--accent-icon`, `--success`, `--band-link`).

### 1.5 Integração com os sites individuais

**Sem mudança no fluxo da PORTAL-02.**
- Os cartões levam `?origem=portal` e `&servico={id}` apenas quando o serviço buscado é um serviço publicado daquela empresa.
- O site só pré-seleciona o serviço se ele for da própria empresa, e a empresa vem sempre do endereço.
- A confirmação só aparece após o registro (WEBSITES-10).
- Testes de feature confirmam os links nas páginas amigáveis.

### 1.6 Neutralidade

Ordenação, critérios e elegibilidade não foram alterados. Nenhum dado de plano, complemento, créditos ou uso de recursos é lido pelo portal. O texto público agora diz explicitamente que "Nenhuma empresa paga por posição".

## 2. Arquivos modificados

- `routes/web.php`: rotas `portal.category` e `portal.region`.
- `app/Http/Middleware/SiteHostGuard.php`: caminhos amigáveis no host do portal.
- `app/Http/Controllers/Portal/PortalController.php`:
  - URLs amigáveis, redirecionamentos 301 e 404;
  - SEO por página, breadcrumbs e sitemap regional;
  - filtros removíveis com endereço amigável.
- `app/Services/Portal/PortalSearch.php`: `municipality()` e `places()`, uma consulta sobre as empresas elegíveis.
- `app/Services/Portal/PortalText.php`: `STATE_NAMES`, `citySlug()` e `cityFromSlug()`.
- `resources/views/portal/index.blade.php`: tema, breadcrumbs, seleção de região, coluna lateral regional, "Explorar por região", 404 e dados estruturados.
- `resources/views/portal/partials/cards.blade.php`: aviso de empresa sediada em outro município.
- `tests/Feature/Websites/RegionalPortalTest.php`: 8 testes novos e 5 asserções da PORTAL-02 ajustadas ao formato amigável.
- `docs/evidencias/portal-03/*.png`.

## 3. Rotas criadas ou ajustadas

| Método | Rota (host do portal) | Nome |
|---|---|---|
| GET | `/categorias/{categoria}` | `portal.category` (novo) |
| GET | `/regioes/{uf}/{municipio?}/{categoria?}` | `portal.region` (novo) |
| GET | `/` | `portal.home` (passa a redirecionar query strings equivalentes) |
| GET | `/sitemap.xml` | `portal.sitemap` (inclui as páginas regionais) |

Todas usam o limite `portal-search`.

## 4. Resultado das validações direcionadas

| Verificação | Resultado |
|---|---|
| `RegionalPortalTest` | **27 de 27**, 188 asserções (detalhes abaixo) |
| PHPStan (escopo `app/Http/Controllers/Portal`, `app/Services/Portal` e `SiteHostGuard`) | 0 erros (2 apontamentos corrigidos na causa) |
| Pint (arquivos alterados) | ok |
| Visual: inicial com regiões, estado, município com coluna lateral e 404 (view real) e páginas ao vivo `/`, `/regioes/sp` e `/categorias/climatizacao` | **42 de 42** (celular 390 px, tablet 820 px e desktop 1440 px; claro e escuro), sem violação de axe WCAG 2.1 AA, sem rolagem horizontal, deslocamento de layout ≤ 0,1, sem erros de JS |
| Tema (portal ao vivo) | Inicia em "Automático" (`aria-pressed`). "Escuro" aplica e **persiste ao recarregar**. "Automático" remove a escolha. Com o sistema escuro, o automático fica escuro e "Claro" sobrepõe. axe sem violações no escuro manual e no claro manual sobre o sistema escuro. **Teclado** alcança o grupo e ativa o tema |
| Teclado (página de município) | Pular conteúdo → marca → Regiões → Como funciona → Claro, Escuro, Automático → breadcrumbs |

Os 27 testes de `RegionalPortalTest` são 19 da PORTAL-01 e da PORTAL-02, mais 8 novos, que cobrem:
- 301 de query string para endereço amigável;
- 404 para categoria, UF e município inexistentes e para município sem empresa sediada;
- homônimos e grafias;
- empresa sediada em outro município identificada;
- título, descrição, canonical, breadcrumbs e dados estruturados;
- `noindex` com filtros extras e páginas seguintes;
- sitemap exato e sem duplicatas;
- rotas só no host do portal e nomes reservados;
- serviço e origem nos links e presença do seletor de tema.

**Não foram repetidas:** a suíte completa e as suítes de Websites, Tenancy e Operations. Os critérios de elegibilidade e de isolamento do portal continuam cobertos pelos testes da PORTAL-01, que estão nos 27.

Evidências em `docs/evidencias/portal-03/`:
- `d-municipio-topo.png`;
- `d-light-municipio.png`;
- `m-light-estado.png`;
- `m-dark-inicio.png`;
- `d-dark-inicio.png`.

Os estados com resultados foram renderizados a partir de dados de exemplo; nenhuma empresa foi criada no banco local.

## 5. Pendências reais

1. **DNS e certificado** de `portal.vetorsmart.com.br` e as **19 migrations** pendentes, na implantação.
2. **Base oficial de municípios (IBGE):** daria código estável oficial, nomes canônicos e páginas de municípios vizinhos. Exige tabela nova, portanto migration, numa etapa autorizada.
3. **Região atendida estruturada no painel** (lista de municípios por empresa). Hoje é texto livre do Atendimento IA, o que limita a correspondência a menções textuais.
4. **Métricas para o assinante:** visitas vindas do portal e solicitações com origem "Portal Regional" em "Meu Crescimento".
5. **Verificação com empresas reais:** o portal não foi visto no navegador com empresas reais, porque a única conta local é de homologação e não aparece por regra.

## 6. Riscos identificados

1. **Nome de cidade digitado pela empresa:** a página do município depende dele. Um erro de digitação, como "Campinsa", gera uma página própria, válida mas separada. Mitigação futura: lista oficial de municípios no cadastro (pendência 2).
2. **Mudança de cidade ou saída do portal:** quando a última empresa de um município muda de cidade ou sai, a URL passa a responder 404. É o correto para não manter página vazia, mas URLs já indexadas sairão do índice.
3. **Volume do sitemap:** cresce com a combinação de município e categoria das empresas reais. Hoje é pequeno; acima de milhares de URLs, convém dividi-lo em um índice de sitemaps.
4. **Tema:** a preferência é por navegador (`localStorage`). Em modo privado ou com o armazenamento bloqueado, vale sempre o automático, sem erro.
5. **Cache de páginas:** um proxy ou cache que ignore o `Location` absoluto pode servir redirecionamentos antigos após mudanças de domínio do portal.

## 7. Git

- `5b8c601`: consolidação da PORTAL-02.
- **PORTAL-03 sem commit**, aguardando autorização.
- `correio.md` e `executed.md` fora dos commits.
- **Sem push e sem deploy.**

A PORTAL-04 não foi iniciada.
