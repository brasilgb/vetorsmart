VetorSmart — Visão Oficial do Produto
Organização: ABrasilSistemas
Documento: docs/VETORSMART-PRODUCT-VISION.md
Versão: 1.0
Data: 10/11/2026
Estado: Diretriz permanente de produto; funcionalidades sujeitas ao estágio real de implementação.
1. Essência e posicionamento
Slogan institucional: Seu crescimento é o nosso propósito.
Chamada comercial: Venha crescer com a gente!

O VetorSmart é uma plataforma SaaS multi-tenant para empresas prestadoras de serviços, que reúne presença digital, gestão operacional, atendimento inteligente, relacionamento e análise de resultados. Seu propósito vai além de organizar a rotina: ajudar cada empresa a conquistar mais oportunidades, converter negócios, atender melhor, fidelizar clientes e enxergar o valor produzido.

Promessa responsável: oferecer ferramentas e indicadores para apoiar o crescimento; não garantir faturamento, posição no Google, número de clientes ou retorno financeiro.
2. Princípios obrigatórios
1. Crescimento mensurável: toda funcionalidade deve contribuir para atrair, converter, atender, fidelizar ou demonstrar resultados.
2. Simplicidade: fluxos curtos, linguagem clara, evitar campos e etapas redundantes.
3. Automação com controle: automações auditáveis, configuráveis, com limites e intervenção humana.
4. Uma plataforma, muitos tenants: isolamento rigoroso dos dados, permissões e domínios.
5. Dados reais: distinguir observação, estimativa, simulação e atribuição comprovada.
6. Resultados antes de vaidade: priorizar leads úteis, propostas aprovadas, serviços e receita, não somente visitas ou cliques.
7. Evolução incremental: reutilizar módulos existentes e preservar compatibilidade; não duplicar CRM e cadastros.
8. Retenção por valor: reduzir churn mediante resultados percebidos, confiabilidade e atendimento, nunca por aprisionamento do cliente.
3. Público inicial
Empresas e profissionais prestadores de serviços: climatização, elétrica, oficinas e manutenção, assistência técnica, beleza e estética e serviços profissionais. Funcionalidades específicas por segmento devem ser configuráveis sem multiplicar aplicações separadas.
4. Jornada de valor
Descoberta → site e SEO → contato → atendimento/IA → qualificação → proposta → agendamento → execução → conclusão → avaliação e relacionamento → recompra/indicação.

Cada etapa deve reutilizar o mesmo cadastro de cliente e registrar eventos relevantes para análise do funil. O sistema deve permitir operação humana mesmo quando a IA ou integrações estiverem indisponíveis.
5. Componentes do produto
5.1 Site profissional por tenant
- Mesma aplicação Laravel, com sites distintos por domínio/subdomínio autorizado.
- Templates configuráveis por segmento; logo, cores, textos, imagens, serviços, equipe, horário, contatos e páginas.
- Subdomínio da plataforma e opção de domínio próprio, com validação de titularidade e HTTPS.
- Rascunho, prévia protegida e publicação explícita.
- Painel Meu Site: visão geral, aparência, conteúdo, serviços, domínios, SEO e publicação.
- Manter o painel autenticado em domínio central; domínios personalizados atendem os sites públicos.
5.2 SEO integrado
- Títulos, descrições, URLs amigáveis, canonical, Open Graph, sitemap, robots e dados estruturados apropriados.
- SEO local (localidade, região atendida, endereço e horário quando informado).
- Evitar indexação duplicada de domínio próprio e subdomínio.
- Integração futura com Google Search Console e sugestões de IA sob limites de consumo.
- Nunca inventar tráfego, rankings ou desempenho.
5.3 Gestão operacional
- Clientes, serviços, profissionais, usuários, permissões e configurações.
- Agenda, disponibilidade e agendamentos.
- Solicitações, propostas, aprovação, execução e encerramento dos serviços.
- Dados e acesso segregados por tenant.
5.4 IA e atendimento
- Recepcionista virtual contextualizada com dados autorizados do tenant.
- Qualificação de interessados, encaminhamento humano e preparação de propostas/agendamentos.
- IA não inventa preço, diagnóstico ou prazo; não confirma horário sem consulta e reserva transacional.
- Custos controlados por franquias, limites e registros de consumo.
5.5 Aplicativo
- Primeiro: interface web responsiva e PWA por tenant, onde fizer sentido.
- Depois: aplicativo VetorSmart compartilhado para Android/iOS, com login universal e seleção de empresa.
- Recursos previstos: dashboard, agenda, clientes, serviços, solicitações, propostas, notificações e Meu Crescimento.
- Experiência do cliente final inicialmente pelo site/PWA; aplicativo com marca exclusiva somente sob oferta específica.
- Não criar um backend paralelo para o aplicativo.
5.6 Reputação e avaliações no Google
- Link de avaliação configurado por tenant.
- Solicitação manual após serviço concluído e automação opcional por canal habilitado e consentimento apropriado.
- Registro de envio, prevenção de duplicidades e respeito às preferências de comunicação.
- Solicitação neutra, sem incentivos em troca de avaliações ou filtragem de clientes por satisfação.
- Envio não significa avaliação realizada; métricas de avaliações só quando verificáveis.
5.7 Meu Crescimento
Painel voltado a resultados e oportunidades. Indicadores esperados, conforme fontes disponíveis:
- Visitas e canais de origem do site.
- Leads captados, tempo de resposta, propostas e conversões.
- Agendamentos, serviços concluídos e novos clientes.
- Receita registrada versus recebida; receita atribuível apenas quando rastreável.
- Recorrência, retenção de clientes e evolução por período.
- Solicitações de avaliação e resultados confirmados por integração.
- Recomendações acionáveis para contatos sem retorno, propostas pendentes e lacunas de SEO.
Regras de medição: definir origem, janela temporal, denominador, fuso horário, deduplicação e nível de confiabilidade; marcar métricas indisponíveis sem substituí-las por números fictícios. Evitar dupla contagem e alegações causais sem evidências.
5.8 Retenção e saúde do tenant
RootAdmin poderá acompanhar ativação, uso de funcionalidades, falhas recorrentes, integrações, resultados observáveis e sinais de risco de churn. Um tenant que entra pouco no painel pode estar obtendo bons resultados por automações: frequência de login não define valor isoladamente. Relatórios periódicos devem demonstrar o que foi entregue e quais oportunidades ainda existem.
6. Arquitetura e segurança
- Stack existente: Laravel, Inertia, Vue, TypeScript, Tailwind e MySQL 8; não utilizar recursos exclusivos de PostgreSQL.
- Reutilizar estruturas e convenções dos BACKEND-01 a BACKEND-04 conforme realmente implementadas.
- Multi-tenancy com isolamento em consultas, uploads, cache, jobs, eventos e métricas.
- Domínios normalizados, únicos, autorizados e verificados; desconhecidos não resolvem tenants.
- Autorização no servidor para cada ação; auditoria de operações sensíveis.
- Segurança de sessões, credenciais, chaves e fluxos de publicação.
- LGPD: minimização, base legal, direitos dos titulares, retenção e consentimento quando exigível.
- Sem dados reais de produção em testes ou exemplos de documentação.
7. Identidade visual e experiência
- Nome: VetorSmart.
- Sidebar administrativa: #0F172A; cores em tokens centralizados, contrastes acessíveis e dark mode coerente.
- Login universal com fundo profissional e película rgba(15,23,42,0.72); cartão de até 460 px.
- Cadastro em uma tela no desktop quando espaço permitir; responsividade sem comprometer acessibilidade no mobile.
- RootAdmin separado do painel tenant; menu por perfil, somente itens implementados.
- Interfaces limpas, sem excesso de cards ou imagens sobrepostas; ausência de botões flutuantes de WhatsApp em áreas privadas.
- Cada site de tenant pode ter identidade própria, independente da identidade do painel.
8. Oferta comercial — hipótese a validar
Valores discutidos, não considerados preços aprovados para cobrança automática:
Plano	Mensalidade sugerida	Direção de oferta
Essencial	R$ 99,90	Site, SEO técnico, gestão, agenda, PWA, até 2 usuários
Profissional	R$ 199,90	Domínio próprio, até 5 usuários, IA limitada, Meu Crescimento e app compartilhado quando disponível
Premium	R$ 349,90	Até 15 usuários, franquias ampliadas, automações e relatórios avançados
Personalizado	Sob orçamento	Escala, integrações e eventual aplicativo de marca exclusiva


A cobrança, franquias de IA e WhatsApp, taxas de implantação, limites contratuais e custos de app/domínio exigem validação de custo e decisão comercial posterior. Nunca anunciar recurso não entregue como disponível.
9. Roadmap indicativo
1. BACKEND-04: gestão operacional do tenant e pendências críticas do BACKEND-03.
2. Arquitetura Web / BACKEND-05: sites multi-tenant, templates, domínio, SEO e publicação.
3. BACKEND-06: agenda e reservas transacionais.
4. BACKEND-07: atendimento inteligente e funil comercial.
5. BACKEND-08: pós-atendimento, avaliações, automações e demonstração de resultados.
6. Aplicativo: desenvolvimento PWA e, depois, app nativo compartilhado em etapas compatíveis com o backend.
Essa sequência deve ser revista segundo dependências e código real, não pela numeração isolada.
10. Critérios de sucesso do produto
- Tempo para o tenant publicar seu site e receber o primeiro contato útil.
- Percentual de tenants ativados que completam a primeira jornada de valor.
- Conversão de contatos em propostas, propostas em vendas e agendamentos em serviços concluídos.
- Indicadores de resultados rastreáveis, engajamento útil e retenção de clientes finais.
- Churn, retenção de receita, satisfação e causas de cancelamento dos próprios tenants.
- Margem saudável por plano, levando em conta IA, infraestrutura, mensagens e suporte.
Não atribuir crescimento ao software sem dados suficientes; diferenciar contribuição possível de causalidade demonstrada.
11. Regras para Claude e demais agentes
1. Ler este documento antes de planejar ou implementar novos módulos.
2. Conferir executed.md, correio.md e os relatórios de docs/ para estabelecer o estado real do projeto.
3. Priorizar implementações utilizáveis, testes de isolamento e dados reais; não criar telas simuladas como entregas finais.
4. Preservar os layouts, tokens, login universal e arquitetura já aprovados.
5. Registrar decisões e dependências técnicas nos relatórios da etapa.
6. Não executar commit, push, merge, deploy, exclusão de dados reais nem rotação de credenciais sem autorização explícita.
7. Antes de aceitar uma nova funcionalidade, perguntar: ajuda o tenant a crescer? simplifica a operação? permite demonstrar resultados?
VetorSmart — Seu crescimento é o nosso propósito.
Venha crescer com a gente!