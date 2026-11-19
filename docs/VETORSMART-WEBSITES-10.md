# VETORSMART-WEBSITES-10 — Evolução do site público dos prestadores e integração operacional

Data: 19/11/2026. Local: desenvolvimento (WSL), `gateway/vetorsmart/`.

- Sem push, deploy ou migrations na VPS.
- Sem alteração de DNS.
- Sem alterações financeiras.
- Sem MFA.
- Sem dependências novas.
- Portal regional e landing comercial não foram alterados.

## Parecer

**Concluída.** Os 10 critérios de aceite foram atendidos:

| # | Critério | Situação |
|---|---|---|
| 1 | PANELS-08 consolidada | `b6ab5a7` (diff conferido, sem arquivos sensíveis; `correio.md` e `executed.md` fora do commit) |
| 2 | Assinante configura o site | "Meu Site": conteúdo, identidade visual (cor, tipografia, logotipo, imagem principal), serviços publicados, exibição de preços, SEO, prévia, publicação explícita, estado e link do site |
| 3 | Logotipo e imagens com isolamento | Upload validado, recodificado e guardado na pasta da empresa no disco privado; servido só quando referenciado (seção 2.1) |
| 4 | Prévia não publica | As imagens do rascunho só aparecem na prévia autenticada; o endereço público responde 404 para elas (testado) |
| 5 | Visitante vê só o que é público | O site, o formulário e as imagens leem apenas a versão publicada |
| 6 | Serviços e atendimento integrados | "Solicitar este serviço" em cada serviço, "Solicitar atendimento" e "Agendar online" na página inicial |
| 7 | Fluxo de solicitação | Visitante → site → serviço → solicitação → triagem → agendamento (testado de ponta a ponta) |
| 8 | SEO coerente | Canonical, `og:image` real, `areaServed`; formulário `noindex` e fora do sitemap |
| 9 | Testes direcionados | 11 novos; 409 nos grupos afetados |
| 10 | Nenhuma regra financeira ou de produção alterada | Confirmado |

## 1. Inspeção: o que já existia e foi reaproveitado

| Estrutura | Existente | Uso nesta etapa |
|---|---|---|
| Sites por empresa | `TenantWebsite` + `WebsiteVersion` (versões imutáveis, publicação atômica, cache por versão) | Mantido. Imagens e novos campos entram no mesmo rascunho JSON |
| Template | `website/site.blade.php` único; o modelo só tinha o rótulo do segmento | Ganhou a variação visual por segmento, sem Blade por atividade |
| Rotas e domínio | `/{segmento}/{empresa}`, subdomínio e domínio próprio (`SiteHostGuard`, `WebsiteResolver`, `PublicSiteAddress`) | Preservados. Só foram acrescentados `/solicitar` e `/midia/{arquivo}` nas duas formas de endereço |
| Rascunho, prévia e publicação | `saveDraft`, `preview`, `publish`, `unpublish` | Preservados |
| Informações comerciais | Configurações (contatos, endereço, horários, link do Google) | Lidas pelo site, sem duplicação |
| Serviços públicos | Seleção dos serviços ativos em "Meu Site" | Ganharam o `id` na versão publicada (para o botão de solicitar) e a opção de ocultar valores |
| Atendimento IA | Chat público com agendamento após persistência, limite por visitante e empresa | Ligado pelo botão "Agendar online" quando habilitado. A região atendida (`service_region`) passa a aparecer no site |
| Formulário de solicitação | **Não havia formulário público.** A origem `public_site` existia no enum sem uso | Implementado (seção 2.4) |
| Agenda pública | Não existe agenda aberta; o agendamento público é feito pelo Atendimento IA | Mantido. Não foi criado outro agendador |
| SEO técnico e sitemap | Título, descrição, canonical, Open Graph, JSON-LD, sitemap e robots por host | Estendidos (seção 2.5) |
| Armazenamento | **Nenhum upload existia.** Disco `local` privado (`storage/app/private`); `public/storage` não está ligado | Usado o disco `local` privado, sem serviço externo |

**Divergência de URL:** nenhuma. As rotas e a estratégia de domínio foram preservadas.

## 2. Implementações

### 2.1 Logotipo e imagem principal (`WebsiteMedia`)

**Envio pelo painel**
- Aba **Aparência**, em `POST /meu-site/imagens/{logo|hero}`. Também é possível remover a imagem.
- Exige a permissão `manageSettings`: proprietário e administrador. O operador recebe 403 (testado).
- Limite de 20 envios por minuto.

**Validação**
- Aceita JPG, PNG ou WebP, com até 2 MB e entre 64 e 8000 px.
- **SVG é recusado**, porque pode carregar script (testado).

**Recodificação com GD** (extensão já instalada)
- Toda imagem é decodificada e regravada em WebP com qualidade 82.
- Metadados (EXIF e localização) são descartados.
- Um arquivo que não é imagem de verdade nunca chega ao disco.
- A largura é reduzida para no máximo 600 px no logotipo e 1600 px na imagem principal (testado: 1200 px vira 600 px).

**Isolamento**
- Os arquivos ficam em `storage/app/private/websites/{tenant_id}/`, no disco local privado.
- A pasta vem da empresa da sessão, nunca do formulário.
- O nome é aleatório (40 caracteres) e segue o formato `(logo|hero)-xxxx.webp`.

**Entrega**
- **Público:** `/{segmento}/{empresa}/midia/{arquivo}` ou `/midia/{arquivo}` no domínio da empresa. O arquivo só é servido se a **versão publicada** dessa empresa o referenciar. Uma imagem do rascunho, de outra empresa ou removida responde 404 (testado). Cabeçalhos: `Cache-Control: immutable` e `nosniff`.
- **Prévia:** `/meu-site/previa/midia/{arquivo}`, só para usuários autenticados da empresa e só para arquivos referenciados no rascunho. A prévia de outra empresa não acessa o arquivo (testado).

**Limpeza**
- Ao trocar ou remover uma imagem, e depois de cada publicação, saem do disco os arquivos que nem o rascunho nem a versão publicada usam.
- A imagem publicada permanece até a próxima publicação (testado).

**Banco:** nada muda. As referências (`{file, width, height}`) ficam no `draft_content`, que já existe.

### 2.2 Modelo-base com variação por segmento (`SegmentTheme`)

- Um único template recebe, conforme o segmento, a cor de fundo da área principal e um ícone de linha desenhado no próprio HTML, sem imagem externa nem requisição extra.
- Há variações para os 11 segmentos cadastrados (os 10 do seeder mais "Beleza e estética", presente na base local).
- Um segmento sem entrada própria, ou uma versão publicada antes desta etapa, recebe o tema padrão.
- Nenhum texto é inventado para a empresa: o tema altera só a aparência.

### 2.3 Página inicial e serviços

**Página inicial**
- Logotipo ao lado do nome.
- Área principal com o ícone do segmento, título, "Atendemos: {região}" e a imagem principal com `width`/`height` (sem deslocamento de layout).
- Ações: **Solicitar atendimento**, **Agendar online** (só com o Atendimento IA ativo) e **Falar no WhatsApp** (só com WhatsApp cadastrado).
- A seção de contato mantém o link "Avalie-nos no Google".

**Serviços**
- Cada serviço publicado tem o link **"Solicitar este serviço"**, que leva ao formulário já com o serviço escolhido.
- Nova opção **"Mostrar valores dos serviços no site"** (padrão: ligada, como antes). Desligada, oculta os preços e mantém os serviços (testado).
- Serviços desativados ou não selecionados continuam fora do site.

**Região atendida**
- Vem do Atendimento IA (`service_region`), sem duplicação. Aparece na página inicial, na página de serviços e no JSON-LD como `areaServed`.
- O painel informa de onde vem.

**Contraste**
- A cor "ciano" (`#06B6D4`) não alcançava 4,5:1 em textos e botões. O problema já existia antes desta etapa.
- Nos textos e botões do site e do formulário, a cor passa a ser exibida no tom escuro `#0E7490` (5,4:1).

### 2.4 Formulário de solicitação (`/solicitar`)

**Campos:** nome, telefone com DDD, e-mail (opcional), serviço (opcional, só entre os **publicados**), descrição e autorização de contato.

**Ao enviar:**
- O cliente é localizado pelo telefone na **própria empresa** ou criado. Reutiliza `AssistantOperations::findOrCreateCustomer`.
- A solicitação nasce com a origem **"Site público"** (`RequestOrigin::PublicSite`), o que avisa a equipe pelo fluxo existente (`requestReceived`).
- Com um serviço válido, ela já é triada para esse serviço. Um serviço não publicado ou de outra empresa é ignorado, e o pedido fica para a triagem manual (testado).

**Empresa e sessão**
- A empresa vem **só do endereço**.
- A confirmação "Solicitação recebida" aparece **apenas depois de gravar**, após o redirecionamento.

**Proteção contra abuso**
- Limite `site-request`: 3 envios por minuto e 10 por hora, por visitante e empresa (testado: o 4º envio no mesmo minuto recebe 429).
- Campo-armadilha oculto: quando preenchido, o robô vê a mesma confirmação e nada é gravado (testado).
- CSRF.
- Validação do telefone (`AssistantText::phone`).

**Visibilidade**
- `noindex` no HTML e no cabeçalho `X-Robots-Tag`. Fora do sitemap (testado).
- Em site não publicado, a página responde 404 e nada é gravado (testado).
- No domínio próprio, `SiteHostGuard` libera só `/solicitar` e `/midia/{arquivo válido}`. O painel continua 404 nesses hosts (testado).

**Agenda:** a solicitação do site segue a triagem e o agendamento do painel, respeitando a disponibilidade do profissional. Um horário fora da disponibilidade é recusado e o pedido continua "Triada"; um horário válido cria o agendamento da própria empresa (testado de ponta a ponta). Os bloqueios e a prevenção de conflito continuam com `AppointmentScheduler` e seus testes existentes.

### 2.5 SEO

- **Imagem de compartilhamento:** `og:image` usa a imagem principal (ou, sem ela, o logotipo) com o endereço **canônico**.
- **JSON-LD:** ganhou `image` e `areaServed`, só com dados reais, e continua fora de páginas `noindex` e da prévia.
- **Sitemap:** continua listando só a página inicial e a de serviços de sites publicados e indexáveis (a de serviços só se houver serviços). Nunca lista o formulário nem as imagens (testado).
- **Sem alteração:** título e descrição configuráveis, canonical único, `noindex` para empresas de homologação e prévias.
- Este relatório não afirma que o Google indexou qualquer site.

### 2.6 Painel "Meu Site"

- **Aparência:** campos de logotipo e de imagem principal, cada um com a imagem atual (pela prévia), envio, remoção com confirmação e mensagens de erro acessíveis. A frase "Logotipo ainda não está disponível" foi retirada.
- **Serviços:** opção de exibir os valores e explicação do botão "Solicitar este serviço".
- **Conteúdo:** mostra a região atendida e de onde ela vem.
- **Prévia:** mostra o botão "Solicitar atendimento" (inerte), para refletir o layout publicado.
- **Mantidos:** estado publicado, versão, link do site, prévia e publicação explícita.

## 3. Alterações de banco

**Nenhuma.** Imagens, opção de valores e tema usam o JSON `draft_content` e as versões publicadas, que já existem. Continuam **18 migrations** pendentes para a próxima implantação.

## 4. Arquivos

**Criados:**
- `app/Services/Websites/WebsiteMedia.php`
- `app/Services/Websites/SegmentTheme.php`
- `app/Services/Websites/PublicSiteContext.php`
- `app/Http/Controllers/Site/SiteMediaController.php`
- `app/Http/Controllers/Site/SiteRequestController.php`
- `app/Http/Requests/Site/StoreSiteServiceRequest.php`
- `app/Http/Requests/Tenant/StoreWebsiteImageRequest.php`
- `resources/views/website/solicitar.blade.php`
- `tests/Feature/Websites/PublicSiteEvolutionTest.php`

**Alterados:**
- `WebsiteService` (imagens e limpeza)
- `WebsiteSnapshotBuilder` (imagens, região, `id` dos serviços, valores, `segment_slug`)
- `WebsiteDraft` (padrões)
- `Tenant/WebsiteController` (envio, remoção, imagem da prévia, região)
- `UpdateWebsiteDraftRequest` (`show_prices`)
- `SiteController` (tema, imagens, `og:image`, link de solicitação)
- `SiteHostGuard` (novos caminhos)
- `AppServiceProvider` (limite `site-request`)
- `RequestOrigin` (comentário)
- `routes/web.php` e `routes/tenant.php`
- `website/site.blade.php`
- `tenant/website/index.tsx`

## 5. Testes e evidências

| Verificação | Resultado |
|---|---|
| `PublicSiteEvolutionTest` (novo) | **11 de 11**, 128 asserções: recodificação e pasta da empresa; rascunho invisível ao público; isolamento entre empresas no público e na prévia; tipos, tamanho e permissão no envio; limpeza preservando a imagem publicada; tema, região, ações, preço, `og:image` e `areaServed`; ocultar valores; solicitação na empresa certa com serviço publicado; serviço não publicado ignorado; validação, armadilha e limite; 404 sem publicação e fora do sitemap; domínio próprio; solicitação → triagem → agenda respeitando a disponibilidade |
| Grupos afetados (`Websites`, `Assistant`, `Operations`, `Tenancy`) | **409 testes, 400 aprovados**; os 9 restantes são de concorrência, que exigem `VETORSMART_CONCURRENCY=1` |
| PHPStan | 0 erros (5 apontamentos corrigidos na causa) |
| Pint, TypeScript e build | ok |
| Visual dos modelos (views reais renderizadas com conteúdo de exemplo: Climatização e Jardinagem com imagens; Eletricistas, Beleza e estética e um segmento sem tema, sem imagens; página inicial e serviços de cada um, além do formulário) | **44 de 44** (desktop e celular, claro e escuro): sem violação de axe WCAG 2.1 AA, sem rolagem horizontal, deslocamento de layout ≤ 0,1, sem erros de JS. Navegação por teclado chega a "Solicitar atendimento" na 6ª tabulação |
| "Meu Site" no painel (conta local de homologação) | **4 de 4**, sem problemas |

A suíte completa não foi executada, conforme o correio. A verificação visual usou renderização estática das views, para não publicar o site da conta local nem alterar dados.

## 6. Limitações

1. **Armazenamento em produção:** as imagens ficam em `storage/app/private/websites`. Na implantação, essa pasta precisa ser persistente entre deploys, como o restante de `storage/`, e entrar no backup.
2. **Tema escuro no site público:** o site permanece em tema claro único, para garantir o contraste das cores da marca. O painel continua com claro e escuro.
3. **Versões já publicadas:** só ganham o botão "Solicitar este serviço" com o serviço escolhido, o tema por segmento e a região depois de uma nova publicação. Até lá, o botão geral de solicitação já funciona.
4. **Página de atendimento IA:** continua com a cor ciano original (fora do escopo).
5. **Agendamento direto pelo visitante:** só pelo Atendimento IA. O formulário gera a solicitação para triagem, e não há uma agenda aberta separada.
6. **Formulários na prévia:** os botões ficam inertes.
7. **Testes de integração:** o envio real de e-mail do aviso à equipe e o acesso por subdomínio no navegador não foram verificados; os testes automatizados cobrem a lógica.

## 7. Recomendações

1. Incluir `storage/app/private/websites` no backup e conferir as permissões na implantação.
2. Na próxima homologação, publicar o site de uma empresa de teste e conferir o compartilhamento (`og:image`) com um validador de Open Graph.
3. Avaliar um aviso no painel quando houver solicitações com origem "Site público" ainda sem triagem.
4. Corrigir o contraste da cor ciano na página de atendimento IA, do mesmo modo que no site.
5. Avaliar galeria de fotos de trabalhos, com o mesmo mecanismo de mídia (limite por empresa).

## 8. Git

- `b6ab5a7`: PANELS-08.
- **WEBSITES-10 sem commit**, aguardando autorização.
- **Sem push e sem deploy.**
