# VETORSMART-LANDING-REDESIGN-05
## Repaginação Completa da Landing Page — SaaS Premium Minimalista

**Projeto:** VetorSmart — ABrasilSistemas  
**Diretório:** `gateway/vetor-smart/`  
**Referência obrigatória:** `docs/VETORSMART-PRODUCT-VISION.md`

### DECISÃO DO USUÁRIO

A landing atual foi rejeitada visualmente.

O resultado das execuções anteriores não atingiu o padrão esperado.

Os principais problemas identificados são:

- Elementos visualmente espalhados.
- Falta de unidade entre seções.
- Hero sem composição satisfatória.
- Cards pouco elegantes.
- Carrossel visualmente inadequado.
- Excesso de elementos decorativos.
- Hierarquia visual inconsistente.
- Aparência inferior ao padrão esperado de um SaaS profissional.

**Esta tarefa não é uma correção de cores, imagens ou margens. É uma repaginação estrutural completa.**

Não preservar o layout anterior por conveniência.

Preservar somente as funcionalidades, contratos, integrações, rotas e dados que já estejam corretos.

---

# 1. DIREÇÃO CRIATIVA

Adotar uma estética:

**Premium Minimalist SaaS / Modern Enterprise**

Características:

- Moderna.
- Elegante.
- Profissional.
- Organizada.
- Visualmente consistente.
- Com personalidade própria.
- Forte sem ser exagerada.
- Fácil de navegar.
- Focada em conversão comercial.

Utilizar referências de qualidade de interfaces SaaS modernas, sem copiar identidades de concorrentes.

A landing deve transmitir uma empresa tecnológica confiável e madura.

Evitar aparência de template genérico montado com componentes desconectados.

---

# 2. DESIGN SYSTEM

Antes de reconstruir a página, definir um sistema visual consistente.

### Cores

Paleta principal:

- Azul-marinho: `#0F172A`.
- Azul de ação: `#2563EB`.
- Branco: `#FFFFFF`.
- Fundo alternativo: `#F8FAFC`.
- Texto secundário: `#64748B`.

Utilizar cores complementares suaves nos cards de funcionalidades.

Sugestões:

- Azul-claro: `#DBEAFE`.
- Verde-claro: `#DCFCE7`.
- Violeta-claro: `#EDE9FE`.
- Laranja-claro: `#FFEDD5`.
- Ciano-claro: `#CFFAFE`.

As cores complementares devem ter finalidade de identificação e diferenciação, não apenas decoração.

Manter tokens centralizados e respeitar os componentes existentes quando apropriado.

### Tipografia

Utilizar fonte profissional com excelente legibilidade.

Definir escala consistente para:

- Título principal.
- Títulos das seções.
- Subtítulos.
- Descrições.
- Botões.
- Legendas.

Não misturar tamanhos arbitrários.

### Layout

Utilizar container central consistente, com largura máxima aproximada de 1200 a 1280 px.

Aplicar:

- Grid responsivo.
- Alinhamento rigoroso.
- Espaçamento vertical consistente.
- Bordas e raios padronizados.
- Sombras discretas.
- Hierarquia tipográfica clara.

Evitar seções com larguras e alinhamentos incoerentes.

---

# 3. NOVO HEADER

Reconstruir o cabeçalho público.

Estrutura:

**Esquerda:** logotipo VetorSmart.

**Centro:** navegação simplificada.

- Plataforma.
- Funcionalidades.
- Segmentos.
- Planos.

**Direita:**

- Entrar.
- Começar grátis.

Requisitos:

- Header elegante.
- Altura equilibrada.
- Excelente espaçamento.
- CTA principal destacado.
- Navegação mobile acessível.
- Comportamento sticky discreto, se adequado.

Não adicionar menus desnecessários.

---

# 4. NOVO HERO

Refazer completamente o Hero.

Não reutilizar a composição anterior se estiver visualmente comprometida.

### Estrutura recomendada

Desktop:

- Coluna esquerda: mensagem comercial.
- Coluna direita: demonstração visual do VetorSmart.

Mobile:

- Mensagem principal primeiro.
- CTAs.
- Visual do produto abaixo.

### Conteúdo

Pequeno destaque:

**A plataforma inteligente para sua empresa crescer**

Título principal:

**Seu crescimento é o nosso propósito.**

Descrição:

"Conquiste oportunidades, organize seus atendimentos e acompanhe seus resultados com site, gestão e ferramentas inteligentes em uma única plataforma."

CTA principal:

**Venha crescer com a gente**

CTA secundário:

**Conheça a plataforma**

### Apresentação visual

Utilizar uma composição moderna baseada em:

- Interface real do dashboard.
- Prévia real do sistema.
- Mockup de dispositivo, quando adequado.
- Elementos discretos que mostrem os recursos.

Não fabricar resultados comerciais ou screenshots apresentados como reais.

Não usar imagens de fundo que prejudiquem o contraste.

Não utilizar grandes quantidades de cards sobrepostos.

A prioridade é uma primeira dobra elegante e memorável.

---

# 5. CARROSSEL DE PROFISSIONAIS E SEGMENTOS

Eliminar a dependência de fotografias externas.

**Utilizar ícones profissionais padronizados, preferencialmente Lucide.**

O carrossel deve apresentar segmentos com clareza, simplicidade e elegância.

### Direção visual

Cards compactos contendo:

- Ícone principal.
- Nome do segmento.
- Estado de hover discreto.
- Área de clique funcional.

Duas possibilidades visuais aceitáveis:

**Opção A — Monocromática**

Cards brancos ou cinza-claro, ícones azul-marinho e tipografia escura.

**Opção B — Cores suaves**

Cada card recebe fundo pastel suave, com ícone e texto de contraste adequado.

Escolher a alternativa que melhor se integrar à composição geral.

Não utilizar cores fortes aleatórias.

Não misturar fotografias e ícones no mesmo carrossel.

### Segmentos

Utilizar os dez segmentos existentes no banco.

Mapear cada segmento para um ícone coerente.

Criar fallback para segmentos futuros.

### Comportamento

- Rolagem horizontal.
- Botões anterior/próximo.
- Arraste em dispositivos touch.
- Navegação por teclado.
- Indicadores somente quando úteis.
- Cards com altura uniforme.
- Espaçamentos consistentes.

Não criar cartões excessivamente largos.

Não deixar itens cortados de forma visualmente desorganizada.

---

# 6. SEÇÃO DE FUNCIONALIDADES

Criar uma das seções visualmente mais importantes da landing.

Título sugerido:

**Tudo o que sua empresa precisa para crescer.**

Subtítulo:

"Da presença digital ao relacionamento com seus clientes, uma plataforma para simplificar sua rotina e impulsionar seus resultados."

### Cards coloridos

Criar um grid organizado com seis cards.

**1. Site profissional e SEO**

Cor suave azul.

Ícone: globo.

Apresentar presença digital, domínio próprio e otimização para buscas.

**2. Atendimento inteligente**

Cor suave violeta.

Ícone: bot ou mensagens.

Apresentar recepcionista IA e qualificação de contatos.

**3. Agenda e agendamentos**

Cor suave ciano.

Ícone: calendário.

Apresentar organização de horários e profissionais.

**4. Gestão de serviços**

Cor suave laranja.

Ícone: ferramentas ou clipboard.

Apresentar clientes, serviços, profissionais e operação.

**5. Meu Crescimento**

Cor suave verde.

Ícone: gráfico de crescimento.

Apresentar indicadores e resultados mensuráveis.

**6. Aplicativo VetorSmart**

Cor suave azul-acinzentado.

Ícone: smartphone.

Apresentar mobilidade e acesso à operação.

### Regras dos cards

- Alturas equilibradas.
- Ícones grandes e consistentes.
- Títulos curtos.
- Descrições objetivas.
- Espaçamento interno generoso.
- Bordas discretas.
- Hover suave.
- Hierarquia clara.
- Layout de três colunas em desktop, duas quando adequado e uma no mobile.

Identificar funcionalidades futuras de maneira discreta e honesta.

Não inventar módulos concluídos.

---

# 7. JORNADA DE CRESCIMENTO

Criar uma seção que mostre claramente o diferencial estratégico do produto.

Título:

**Do primeiro contato ao crescimento do seu negócio.**

Representar visualmente a jornada:

Atrair → Atender → Converter → Organizar → Fidelizar → Crescer.

Usar uma composição linear, timeline ou sequência de etapas.

Manter textos curtos e fáceis de entender.

Essa seção deve mostrar que o VetorSmart não é apenas um sistema administrativo, mas uma plataforma para ajudar empresas a crescer.

---

# 8. MEU CRESCIMENTO

Criar uma seção visualmente forte para apresentar o conceito do painel Meu Crescimento.

Utilizar dados reais quando o módulo estiver disponível.

Se não existir implementação concluída, utilizar uma apresentação conceitual claramente identificada como ilustração, sem apresentar números inventados como resultados verdadeiros.

Mostrar o benefício:

**Saiba de onde vêm suas oportunidades e acompanhe a evolução da sua empresa.**

Evitar dashboards enormes ou imagens de baixa qualidade.

---

# 9. PLANOS E PREÇOS

Redesenhar os cards comerciais.

Planos propostos:

- Essencial — R$ 99,90/mês.
- Profissional — R$ 199,90/mês.
- Premium — R$ 349,90/mês.

Os preços continuam sujeitos à validação comercial.

Destacar o Profissional como plano principal.

Manter os cards alinhados, com comparações claras e sem excesso de informação.

Distinguir os recursos disponíveis daqueles em desenvolvimento.

Não anunciar franquias ou condições como definitivas sem validação.

---

# 10. FAQ E CTA FINAL

Simplificar o FAQ.

Utilizar accordion acessível e visualmente discreto.

O CTA final deve possuir uma composição forte, mas simples.

Título:

**Pronto para dar o próximo passo?**

Descrição:

"Sua empresa merece uma plataforma que acompanhe seu crescimento."

Botão:

**Venha crescer com a gente!**

Utilizar o fluxo de cadastro existente.

---

# 11. FOOTER

Criar um rodapé profissional e compacto.

Incluir:

- Logotipo VetorSmart.
- Descrição institucional curta.
- Links relevantes.
- Planos.
- Login.
- Informações da ABrasilSistemas.
- Política de privacidade e termos, quando disponíveis.

Não criar links quebrados.

---

# 12. COMPONENTIZAÇÃO

Revisar os componentes atuais:

- `HeroSection`
- `SegmentCarousel`
- `FeatureVisualGrid`
- `PricingSection`
- `FaqSection`
- `FinalCtaSection`
- `SectionShell`

Refatorar, substituir ou reorganizar conforme necessário.

Evitar duplicação de código.

Não carregar componentes antigos sem uso.

Não modificar estruturas administrativas que não fazem parte da landing.

---

# 13. EXPERIÊNCIA E PERFORMANCE

Obrigatório:

- Layout responsivo.
- Excelente experiência mobile.
- Tipografia fluida.
- Contraste acessível.
- Navegação por teclado.
- Estados de foco visíveis.
- Suporte a movimento reduzido.
- Carregamento eficiente.
- Evitar layout shifts.
- Nenhum overflow horizontal indevido.
- Nenhuma animação excessiva.

A landing deve funcionar bem mesmo sem imagens externas.

---

# 14. HOMOLOGAÇÃO VISUAL OBRIGATÓRIA

Esta etapa não deve ser validada apenas com testes automatizados e build.

É necessário visualizar o resultado.

Verificar se existe navegador disponível no ambiente.

Se possível, configurar navegador de testes utilizando ferramentas já suportadas pelo projeto, como Playwright.

Validar ao menos:

- Desktop 1440 px.
- Notebook 1280 px.
- Tablet 768 px.
- Mobile 390 px.

Capturar screenshots para comparação.

Inspecionar:

- Alinhamento.
- Espaçamento.
- Quebras de texto.
- Proporções.
- Cards.
- Hero.
- Header.
- Carrossel.
- Rodapé.

Corrigir os problemas encontrados antes de encerrar.

Se a instalação do navegador estiver bloqueada, registrar a limitação e não declarar homologação visual concluída.

---

# 15. DOCUMENTAÇÃO

Criar:

`docs/VETORSMART-LANDING-REDESIGN-05.md`

Registrar:

- Estrutura anterior e nova.
- Componentes alterados.
- Decisões de design.
- Screenshots obtidos.
- Validações realizadas.
- Pendências.
- Resultado final.

Atualizar `executed.md` com o resultado desta execução.

Preservar os relatórios anteriores.

---

# 16. RESTRIÇÕES

Não executar:

- Commit.
- Push.
- Merge.
- Deploy.
- Alterações de produção.
- Exclusão de dados.
- Mudanças desnecessárias no backend.

Preservar:

- Landing pública.
- Cadastro funcional.
- Autenticação.
- CTAs reais.
- Segmentos vindos do banco.
- SEO existente.
- Identidade administrativa.
- Visão oficial do produto.

---

# CRITÉRIO FINAL

Não considerar a tarefa concluída apenas porque os componentes renderizam.

A landing deve demonstrar melhora significativa em:

- Organização.
- Hierarquia.
- Coerência visual.
- Profissionalismo.
- Usabilidade.
- Experiência mobile.
- Clareza comercial.

**A prioridade é qualidade visual, não quantidade de componentes.**

O resultado esperado é uma landing SaaS premium, bonita, funcional e visualmente organizada.

**VetorSmart — Seu crescimento é o nosso propósito.**

**Venha crescer com a gente!**