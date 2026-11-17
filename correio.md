# VETORSMART-WILDCARD-01.1 — Homologação, Git e preparação SSL

**Projeto:** VetorSmart  
**Ambiente:** Desenvolvimento WSL e VPS Hostinger KVM 4  
**Diretório:** `gateway/vetorsmart/`  
**Domínio:** `vetorsmart.com.br`

## 1. Objetivo

Consolidar a implementação WILDCARD-01, homologar os sites públicos por subdomínio e preparar a infraestrutura HTTPS wildcard com renovação automática.

Preservar os sistemas VetorOS, VetorSales, VetorLeads e ABrasilSistemas.

Não ativar subdomínios em produção antes da validação DNS e SSL.

## 2. Auditoria inicial

Antes de qualquer alteração:

- Ler `correio.md`, `executed.md` e `docs/VETORSMART-WILDCARD-01.md`.
- Verificar Git, branch, HEAD e arquivos modificados.
- Confirmar o estado dos testes e migrations.
- Verificar se a infraestrutura já contém suporte parcial ao wildcard.
- Identificar conflitos e alterações de outros projetos.
- Não executar `reset --hard`, `clean`, operações destrutivas nem resolver conflitos fora do VetorSmart.

## 3. Homologação funcional e visual

Validar em Chromium, Firefox e WebKit:

- Desktop e mobile.
- Aparência clara e escura.
- Painel Meu Site → Domínios.
- Cadastro automático de subdomínios.
- Alteração do subdomínio.
- Subdomínios reservados.
- Redirecionamento dos nomes anteriores.
- Domínio próprio prioritário.
- Atendimento IA.
- Agendamento.
- SEO, canônica e sitemap.
- Isolamento multi-tenant.

Corrigir problemas encontrados e registrar evidências.

## 4. Segurança e concorrência

Verificar:

- Unicidade dos subdomínios no MySQL.
- Cadastro simultâneo de empresas com nomes iguais.
- Resolução correta por hostname.
- Isolamento dos dados.
- Tratamento de hosts desconhecidos.
- Cookies e sessões administrativas.
- CSRF, CORS e TrustedHosts.
- Ausência de exposição de rotas administrativas nos subdomínios.

Manter `SESSION_DOMAIN` vazio para sessões host-only, salvo necessidade técnica comprovada.

## 5. SSL wildcard — Hostinger

Investigar a integração do Certbot com a API DNS da Hostinger.

Objetivo:

- Certificado para `vetorsmart.com.br`.
- Certificado para `*.vetorsmart.com.br`.
- Validação DNS-01.
- Renovação automática.
- Credenciais protegidas.
- Não expor tokens em código, logs ou relatórios.
- Não depender de procedimentos manuais recorrentes.

Utilizar integração oficial ou solução compatível e auditável.

Validar previamente permissões, disponibilidade da API e comportamento da renovação.

Não instalar plugins comunitários sem avaliação de segurança e compatibilidade.

Se a automação não for comprovadamente viável, documentar alternativas e não habilitar o wildcard em produção.

## 6. Preparação do Nginx

Preparar configuração específica do VetorSmart para aceitar:

`vetorsmart.com.br`

`*.vetorsmart.com.br`

Requisitos:

- Compartilhar a aplicação Laravel existente.
- Não criar containers por tenant.
- Não modificar os vhosts dos outros sistemas.
- Preservar HTTPS do domínio principal.
- Manter rotas administrativas centralizadas.
- Validar `nginx -t` antes de qualquer aplicação.
- Preparar rollback da configuração.

Não aplicar alterações no Nginx de produção nesta fase sem autorização explícita.

## 7. Consolidação Git

No repositório da aplicação VetorSmart:

- Revisar todas as alterações da WILDCARD-01.
- Verificar arquivos sensíveis.
- Executar testes.
- Criar commits organizados.
- Publicar somente commits aprovados e relacionados ao VetorSmart.
- Confirmar sincronização com `origin/main`.

Não alterar o repositório de infraestrutura que apresenta conflito no VetorLeads.

## 8. Testes finais

Executar:

- Suíte Laravel.
- Concorrência com MySQL real e isolado.
- PHPStan.
- Pint.
- TypeScript.
- Build frontend.
- Testes de navegador.
- Verificações de acessibilidade.
- Verificação de credenciais.

Não considerar testes pulados como aprovados.

## 9. Implantação

Manter inicialmente:

`PLATFORM_SUBDOMAIN_SITES=false`

A ativação depende de:

1. Delegação DNS concluída.
2. Registro wildcard respondendo corretamente.
3. Certificado wildcard válido.
4. Renovação automática comprovada.
5. Homologação de tenant de teste em HTTPS.
6. Aprovação explícita para implantação.

Não executar migrations de produção nem ativar flags nesta etapa sem autorização.

## 10. Relatório

Criar:

`docs/VETORSMART-WILDCARD-01.1.md`

Documentar:

- Diagnóstico inicial.
- Arquivos modificados.
- Commits criados.
- Testes realizados.
- Resultado da homologação visual.
- Estratégia de SSL e renovação.
- Situação do DNS.
- Riscos e pendências.
- Procedimento de implantação e rollback.

Atualizar `executed.md`.

**Parecer esperado:** APROVADO, APROVADO COM RESTRIÇÕES ou REPROVADO, conforme evidências reais.

Não declarar implantação ou homologação de produção sem execução efetiva.
