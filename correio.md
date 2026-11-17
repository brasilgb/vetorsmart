# VETORSMART-RELEASE-10.3
## Consolidação Git, Deploy e Capacidade da Hostinger KVM 4

**Ambiente:** VPS de produção `srv1990862`  
**Aplicação:** `gateway/vetorsmart/`  
**Infraestrutura:** `/opt/infra-abrasil/`  
**Banco:** MySQL 8.4  
**Referência:** VETORSMART-RELEASE-10.2

### OBJETIVO

Consolidar o versionamento, corrigir as falhas residuais do deploy, validar o RootAdmin e avaliar os recursos compartilhados da VPS Hostinger KVM 4.

Preservar o funcionamento de VetorOS, VetorSales, VetorLeads e VetorSmart.

### ETAPA 1 — CONSOLIDAÇÃO GIT

1. Verificar branches, commits, submódulos e alterações pendentes.
2. Preservar todas as alterações da RELEASE-10.2.
3. Revisar o diff antes de qualquer commit.
4. Criar commit específico da RELEASE-10.2 na aplicação.
5. Preparar uma branch limpa da infraestrutura, baseada na `main`, contendo somente as configurações necessárias ao VetorSmart.
6. Não incorporar alterações não relacionadas de `feat/vetorsales-infra`.
7. Validar referências e dependências dos quatro SaaS.
8. Integrar somente após os testes e verificações.
9. Realizar push exclusivamente das alterações revisadas e aprovadas no escopo desta release.

Não executar `git reset --hard`, `git clean -fd` ou comandos destrutivos sobre alterações preexistentes.

### ETAPA 2 — DEPLOY SEM INTERRUPÇÃO

Investigar e resolver a requisição sem resposta identificada na RELEASE-10.2.

Verificar:

- Cache e resolução DNS do upstream no Nginx.
- IP dos containers e renovação das conexões.
- Readiness real do PHP-FPM e Laravel.
- Encerramento gracioso dos processos.
- Inicialização da nova instância antes de retirar a antiga.
- Comportamento do scheduler e worker durante a substituição.
- Possibilidade de rollback imediato.

Avaliar e aplicar `fastcgi_connect_timeout 2s` exclusivamente ao VetorSmart, caso a configuração efetiva permita esse ajuste com segurança.

Antes de modificar o Nginx, executar validação de sintaxe e preservar cópia da configuração anterior.

**Critério de aprovação:** nenhuma requisição perdida ou erro 502 provocado pela troca de containers durante o teste controlado.

Não alterar o comportamento dos outros SaaS.

### ETAPA 3 — AUDITORIA HOSTINGER KVM 4

Recursos de referência:

- 4 vCPU
- 16 GB RAM
- 200 GB NVMe

Verificar os recursos efetivamente disponíveis no servidor.

Analisar:

1. CPU total, por processo e por container.
2. Memória utilizada, disponível e cache.
3. Swap e ocorrências de OOM.
4. MySQL: memória, conexões, consultas lentas e utilização.
5. Redis, se estiver em uso.
6. PHP-FPM, workers e schedulers.
7. Consumo individual dos quatro SaaS.
8. Disco, volumes Docker, imagens e cache de build.
9. I/O e possíveis gargalos.
10. Margem de recursos para deploy com instância temporária.

Produzir relatório com consumo atual, picos observados, principais riscos e recomendações de capacidade.

Não alterar limites de CPU/RAM nem recriar containers apenas para realizar a auditoria.

Não excluir imagens, volumes, backups ou cache nesta release.

### ETAPA 4 — HOMOLOGAÇÃO ROOTADMIN E INTERFACE

Validar em navegador:

- Login administrativo.
- Dashboard RootAdmin.
- Empresas e usuários.
- Sites publicados.
- Agendamentos.
- Indicadores.
- Configurações.
- Aparência clara e escura.
- Responsividade mobile.
- Contraste e acessibilidade.

Revisar especialmente textos de baixo contraste, fundos, botões, formulários e cores inconsistentes.

Realizar testes em Chromium e, quando disponíveis, Firefox e WebKit.

Não reintroduzir MFA/TOTP.

Não criar credenciais administrativas artificiais em produção. Caso não haja acesso autorizado, registrar objetivamente o bloqueio.

### ETAPA 5 — SEGURANÇA E RETENÇÃO

Manter a CSP em modo Report-Only.

Inspecionar os relatórios coletados e identificar eventuais violações legítimas.

Manter a exclusão automática de conversas desabilitada.

Verificar a simulação agendada de retenção, sem excluir registros.

Preservar rate limiting, auditoria, controle de sessões, permissões e isolamento entre tenants.

### ETAPA 6 — TESTES E IMPLANTAÇÃO

Executar:

- Suíte automatizada Laravel.
- PHPStan.
- TypeScript.
- Build de produção.
- Testes de isolamento multi-tenant.
- Testes de agendamento concorrente.
- Testes de atendimento.
- Testes de acesso público.
- Testes de deploy e rollback.

Antes de implantar, confirmar backup válido, estado do Git e imagem de rollback.

Não executar migrations destrutivas.

Se algum teste crítico falhar, interromper a implantação e documentar o problema.

### ETAPA 7 — DOCUMENTAÇÃO

Gerar:

`docs/VETORSMART-RELEASE-10.3.md`

Incluir:

- Diagnóstico inicial.
- Commits e SHAs.
- Arquivos alterados.
- Resultados dos testes.
- Evidências do deploy.
- Resultado da auditoria da KVM 4.
- Situação dos demais SaaS.
- Problemas encontrados e resolvidos.
- Pendências remanescentes.
- Parecer final: aprovado, aprovado com restrições ou reprovado.

### RESTRIÇÕES OBRIGATÓRIAS

- Não prejudicar VetorOS, VetorSales ou VetorLeads.
- Não modificar DNS ou certificados.
- Não alterar dados reais dos tenants.
- Não habilitar MFA.
- Não excluir backups.
- Não executar limpeza geral do Docker.
- Não introduzir novas funcionalidades comerciais.
- Não realizar alterações estruturais na infraestrutura sem diagnóstico comprovado.
- Não expor credenciais, tokens ou segredos nos relatórios.
- Não declarar testes aprovados sem evidências.

### RESULTADO ESPERADO

Ao concluir, o VetorSmart deverá apresentar:

1. Git organizado e consistente.
2. Deploy confiável, com rollback validado.
3. RootAdmin homologado ou bloqueios claramente documentados.
4. Capacidade da KVM 4 avaliada com dados reais.
5. Ausência de regressões nos quatro SaaS.
6. Ambiente preparado para continuar o desenvolvimento funcional.

**Após a RELEASE-10.3, retornar à evolução comercial do VetorSmart, priorizando atendimento, conversão, agenda, sites e painel Meu Crescimento.**
