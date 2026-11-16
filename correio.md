# VETORSMART-RELEASE-10.1 — Estabilização e Homologação de Produção

**Data:** 16/11/2026  
**Ambiente:** VPS de produção `srv1990862`  
**Diretório:** `gateway/vetorsmart/`  
**Branch:** `main`  
**HEAD inicial esperado:** `fd7fa5f`

## Objetivo

Consolidar a implantação dos sites públicos do VetorSmart, corrigir a inicialização dos caches Laravel e homologar a experiência completa em produção.

Priorizar estabilidade, segurança, usabilidade e isolamento multi-tenant.

**Não implementar MFA ou autenticação em duas etapas.**

---

## ETAPA 1 — Inspeção inicial

1. Conferir Git, branch, HEAD e alterações locais.
2. Verificar os 24 containers e seus estados.
3. Identificar entrypoints e configurações de inicialização do VetorSmart.
4. Conferir logs, banco MySQL, Redis e filas.
5. Inspecionar a configuração atual dos caches Laravel.
6. Verificar se existem alterações ainda não consolidadas da RELEASE-10.

Não sobrescrever arquivos locais ou executar `git reset --hard`.

## ETAPA 2 — Correção dos caches Laravel

A RELEASE-10 identificou que os caches são perdidos quando o container é recriado.

Corrigir essa condição de maneira permanente.

Requisitos:

- Gerar `config:cache`, `route:cache` e, quando aplicável, `view:cache` na inicialização.
- Utilizar as variáveis reais de runtime.
- Garantir comportamento consistente no container web, worker e scheduler.
- Evitar concorrência desnecessária na escrita de caches compartilhados.
- Impedir que uma falha de configuração deixe o serviço aparentemente saudável.
- Preservar logs úteis sem expor credenciais.

Testar a recriação controlada dos containers específicos do VetorSmart.

Não reiniciar MySQL, Redis, Nginx ou outros SaaS.

## ETAPA 3 — Empresa de homologação

Verificar se existe um tenant específico para testes.

Caso não exista, preparar um tenant de homologação isolado, sem cobrança real, integrações externas ou envio de notificações a clientes reais.

Utilizar uma identificação inequívoca de teste, por exemplo:

`vetorsmart.com.br/assistencia-tecnica/homologacao-vetorsmart`

Requisitos:

- Não modificar a Clima Total.
- Não utilizar clientes reais.
- Não gerar pagamentos.
- Não executar comunicações externas.
- Restringir acesso administrativo ao tenant de teste.
- Verificar que suas informações não aparecem em relatórios comerciais reais.

Se a criação exigir alterações com efeitos comerciais ou administrativos não previstos, registrar a necessidade de autorização e continuar as demais verificações.

## ETAPA 4 — Homologação dos sites

Validar em produção:

1. Site publicado em `/segmento/empresa`.
2. Página inicial.
3. Lista e detalhes de serviços.
4. Responsividade em desktop e mobile.
5. Navegação e botões.
6. Formulários e validações.
7. Atendimento web.
8. Encaminhamento humano.
9. Solicitação de serviço.
10. Agendamento com horário disponível.
11. Confirmação do agendamento.
12. Visualização no painel da empresa.
13. Alteração de status.
14. Conclusão do atendimento.
15. Isolamento dos dados por tenant.
16. SEO, canonical, sitemap e robots.

Se o servidor não possuir navegador, executar os testes de interface por um ambiente externo autorizado com acesso HTTPS à produção. Não declarar homologação visual com base apenas em respostas HTTP.

## ETAPA 5 — Segurança e desempenho

Conferir:

- Rate limiting no login e no atendimento.
- Sessões e cookies seguros.
- CSRF.
- Autorização de rotas.
- Isolamento de tenants.
- Logs de ações administrativas.
- Disponibilidade do worker e scheduler.
- Consultas lentas.
- Uso de memória e CPU.
- Erros HTTP.
- Ausência de dados sensíveis em respostas públicas.

Não reintroduzir MFA.

## ETAPA 6 — Domínios próprios

Inspecionar a arquitetura necessária para domínios personalizados.

Validar:

- Verificação de propriedade.
- Resolução de tenants por domínio.
- Certificados TLS.
- Configuração futura do Nginx.
- Redirecionamentos canônicos.
- Tratamento de domínios pendentes ou desconhecidos.

Não criar certificados nem modificar DNS nesta execução.

## ETAPA 7 — Manutenção Docker

Identificar imagens antigas e espaço ocupado.

Gerar inventário indicando:

- Imagens atualmente utilizadas.
- Imagens de rollback.
- Imagens antigas sem referência.
- Espaço potencialmente recuperável.

Não executar `docker system prune -a`, `docker volume prune` ou remover backups e imagens de rollback necessárias.

## ETAPA 8 — Relatório

Criar:

`docs/VETORSMART-RELEASE-10.1.md`

Documentar:

- Diagnóstico inicial.
- Arquivos modificados.
- Correção dos caches.
- Resultado da recriação dos containers.
- Tenant utilizado para homologação.
- Evidências funcionais e visuais.
- Testes de segurança.
- Uso de recursos.
- Pendências.
- Resultado final: APROVADO, APROVADO COM RESTRIÇÕES ou BLOQUEADO.

Atualizar `executed.md`.

## Restrições de execução

- Não alterar outros SaaS.
- Não modificar dados da Clima Total.
- Não implementar MFA.
- Não executar migrations destrutivas.
- Não realizar push automaticamente.
- Não alterar DNS.
- Não remover volumes ou backups.
- Preservar possibilidade de rollback.

**Critério de conclusão:** sites públicos e atendimento funcionando em produção, com isolamento correto, caches regenerados automaticamente e experiência desktop/mobile validada por navegador real.
