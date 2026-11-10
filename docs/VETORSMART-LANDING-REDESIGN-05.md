# VETORSMART-LANDING-REDESIGN-05 — Repaginação da landing

Data: 10/11/2026. Diretório: `gateway/vetor-smart/`. Referência: `docs/VETORSMART-PRODUCT-VISION.md`.

## Estrutura anterior e nova

| Anterior | Nova |
|---|---|
| Header com "Plataforma" e "Planos" e CTA "Começar teste grátis" | Header com Plataforma, Funcionalidades, Segmentos e Planos; CTA "Começar grátis"; menu em disclosure abaixo de 1024 px |
| Hero com fundo fotográfico (`hero-plumber.webp`, sem licença registrada) | Hero com a foto de serviço (`hero-plumber.webp`) sob película azul-marinho, apenas com a mensagem e os CTAs (prévia do painel removida a pedido do usuário); CTAs "Venha crescer com a gente" e "Conheça a plataforma" |
| Carrossel com gradientes e fotos reservadas | Mosaico estático de 10 cards (2 colunas no celular, 3 no tablet, 5 no desktop), com ícone Lucide por segmento e link para o cadastro |
| 5 blocos de funcionalidades com gradientes escuros | 6 cards com tom pastel por área, status em texto em cada item |
| Sem jornada | Seção "Jornada de crescimento": Atrair → Atender → Converter → Organizar → Fidelizar → Crescer |
| Sem apresentação do Meu Crescimento | Seção conceitual rotulada como ilustração, com indicadores previstos e sem números |
| 3 planos + card "Personalizado" | 3 planos; Profissional destacado como "Recomendado"; cada recurso com status |
| FAQ com 5 itens | FAQ com 5 itens, accordion nativo com foco visível |
| CTA final "Venha crescer com a gente!" | CTA "Pronto para dar o próximo passo?" com o botão pedido, usando o cadastro existente |
| Rodapé com links e "ABrasilSistemas" | Rodapé em três colunas, sem links para páginas inexistentes |

## Design system

- Tokens em `resources/css/app.css` (`@theme inline`): `vs-line`, tons `vs-tint-*` (azul, violeta, ciano, laranja, verde, ardósia) e tintas de texto `vs-ink-*` para cada tom.
- Escala tipográfica fluida: `vs-title-hero` e `vs-title-section`, em `@utility`.
- `landing-layout.ts` centraliza o container (máx. 1280 px), o espaçamento vertical, o anel de foco e as três variantes de botão.
- `section-shell.tsx` concentra `SectionShell`, `Eyebrow` e `StatusBadge`. O status é texto, nunca só cor.

## Componentes

Criados: `landing-layout.ts`, `landing-header.tsx`, `landing-footer.tsx`, `segment-icons.ts`, `journey-section.tsx`, `growth-section.tsx`.

Reescritos: `welcome.tsx`, `hero-section.tsx`, `segment-mosaic.tsx` (substitui `segment-carousel.tsx`), `feature-visual-grid.tsx`, `section-shell.tsx`, `pricing-section.tsx`, `faq-section.tsx`, `final-cta-section.tsx`.

Removido: `photo-slots.ts`. Nenhum componente o importa mais, e não há fotografia registrada. Os nomes `HeroSection`, `SegmentMosaic` (antigo `SegmentCarousel`), `FeatureVisualGrid`, `PricingSection`, `FaqSection`, `FinalCtaSection` e `SectionShell` foram mantidos.

Preservados sem alteração de contrato: `LandingController`, props `segments` do banco (10 segmentos ativos), `Head` com o título, rotas de cadastro e login, lógica "Ir para o painel" para usuário autenticado, logo, `@/routes`.

## Decisões de design

- Fundo do hero: foto de serviço (`hero-plumber.webp`) a pedido do usuário, sob película `#0F172A` mais forte à esquerda, onde fica o texto. A licença ainda não foi verificada nem registrada em `docs/VETORSMART-IMAGE-CREDITS.md`; a regra do projeto exige isso antes da publicação.
- Segmentos em mosaico estático, sem carrossel, para que os dez apareçam de uma vez. Cards brancos com ícones azul-marinho, em linha com as cores pastel das funcionalidades.
- Cards de funcionalidades em seis tons pastel, cada um identificando uma área. Cada item traz seu status por extenso.
- Prévia do painel no hero: foi removida a pedido do usuário. Não há mais imagem de dashboard na landing.
- Meu Crescimento: gráfico de linha sem eixos e sem valores, legendado como ilustração conceitual.
- Preços: os valores continuam como hipótese. Foi removido "Suporte prioritário", que não consta como entregue, e o card "Personalizado", que não tem canal de contratação.
- Sem links para política de privacidade ou termos: essas páginas não existem nas rotas.

## Validações realizadas

- `npx tsc --noEmit`: 0 erros.
- `npm run build`: aprovado (após as duas correções de cabeçalho).
- `php artisan test --compact`: **231 testes, 228 aprovados, 0 falhas, 3 pulados** (2FA desativado). Mesmo resultado da execução anterior.
- Navegador real: Chromium 1243 instalado em `~/.cache/ms-playwright`, acionado por Playwright. Não foi necessário instalar nada.
- Captura em 1440, 1280, 768 e 390 px, com `reducedMotion`. Em todas as larguras: `scrollWidth` igual à largura da janela (sem overflow horizontal), zero erros de console, 10 cards de segmento e 6 cards de funcionalidade.
- Inspeção visual: 1440, 768 e 390 px. O cabeçalho foi corrigido em duas rodadas: botão "Começar grátis" em uma linha e navegação inline só a partir de 1024 px, para evitar colisão com o logo.
- Medição de sobreposição do cabeçalho em 768, 1024 e 1280 px: sem colisão.

## Screenshots

Em `/tmp/claude-1000/-home-anderson-projects-laravel-infra-abrasil-gateway-vetor-smart/eb928afc-efe2-46ed-bce4-b9d131353956/scratchpad/shots/`:

- `landing-1440.png`
- `landing-1280.png`
- `landing-768.png`
- `landing-390.png`
- `header-390-view.png`

O 1280 foi capturado e verificado por métricas, mas não inspecionado visualmente.

## Pendências

1. **Licença da foto do hero:** `public/images/landing/hero-plumber.webp` está em uso no hero, por pedido seu, mas não tem origem nem licença registradas em `docs/VETORSMART-IMAGE-CREDITS.md`. Antes de publicar, é preciso verificar a licença e registrar a entrada, ou trocar a imagem.
2. **Validação visual humana:** a inspeção foi feita por mim em capturas. Vale uma olhada no navegador real em 1440 e 390 px antes de publicar.
3. **Condições do "teste grátis"** continuam pendentes, como já estava em REFINE-02. A landing usa "Começar", sem prometer condições.
4. **Pendências anteriores sem alteração:** `public/robots.txt` estático, rotação de MySQL e `APP_KEY`, remoção dos registros `[HOMOLOG]`, RootAdmin real, validação em MySQL 8.
5. **Páginas de privacidade e termos** ainda não existem; o rodapé não as referencia.

## Resultado final

**CONCLUÍDO, com pendências 1 a 5.** A landing foi reconstruída com a estrutura pedida, sem alterar backend, rotas, contratos de dados, autenticação ou painel administrativo. Não houve commit, push, merge ou deploy.
