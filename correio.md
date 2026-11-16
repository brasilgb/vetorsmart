# VETORSMART-DEPLOY-08 — Implantação Controlada em Produção

**Projeto:** VetorSmart  
**Ambiente obrigatório:** VPS de produção  
**Diretório:** `gateway/vetorsmart/`  
**Versão de destino:** `ce8404b`  
**Versão anteriormente identificada:** `d2aec27`

## 1. Objetivo

Implantar a release operacional homologada, preservando dados, infraestrutura compartilhada e serviços dos demais SaaS.

A autorização é exclusiva para o VetorSmart e condicionada às verificações de segurança e compatibilidade descritas abaixo.

## 2. Pré-deploy

Antes de modificar o ambiente:

- Confirmar que a execução ocorre na VPS de produção correta.
- Conferir o commit atual e a integridade do repositório.
- Confirmar que `origin/main` contém `ce8404b`.
- Identificar arquivos locais modificados e preservá-los.
- Verificar estado dos containers.
- Conferir a configuração efetiva do Laravel e o timezone da conexão MySQL.
- Executar `php artisan migrate:status` sem aplicar migrações.
- Comparar arquivos de configuração e dependências com a versão de destino.
- Verificar se existem mudanças de schema ou configurações obrigatórias.

**Se houver divergência inesperada, interromper antes do deploy.**

## 3. Backup e recuperação

Antes da implantação:

- Criar backup consistente do banco de dados exclusivo do VetorSmart.
- Verificar a integridade e a possibilidade de restauração do backup.
- Registrar a versão anterior das imagens e do código.
- Documentar o procedimento de rollback.
- Não incluir bancos ou arquivos de outros SaaS.

Se não for possível garantir recuperação segura, interromper.

## 4. Implantação

Após aprovação dos pré-requisitos:

- Atualizar exclusivamente o código do VetorSmart até `ce8404b`.
- Usar o procedimento de build e implantação já existente no projeto.
- Atualizar somente os serviços necessários ao VetorSmart.
- Não executar comandos globais de infraestrutura.
- Não executar `docker compose down` sobre o ambiente compartilhado.
- Não recriar MySQL, Redis ou Nginx compartilhados.
- Não alterar volumes persistentes.
- Não executar migrações automaticamente sem verificar seu conteúdo e necessidade.
- Preservar variáveis de ambiente e credenciais.

## 5. Verificação pós-deploy

Confirmar:

- Aplicação acessível por HTTPS.
- Autenticação funcional.
- Dashboard carregando corretamente.
- Clientes, serviços, profissionais e agenda acessíveis.
- Atendimento público operacional.
- Isolamento multi-tenant preservado.
- Permissões administrativas corretas.
- Modo claro e escuro legíveis.
- Worker e scheduler saudáveis.
- Ausência de erros críticos em logs.
- Versão implantada correspondente a `ce8404b`.

Utilizar testes não destrutivos e evitar criar registros artificiais em produção.

## 6. Falhas e rollback

Caso haja falha crítica:

- Interromper a implantação.
- Preservar logs e evidências.
- Retornar à versão anterior do VetorSmart utilizando o procedimento validado.
- Não restaurar o banco automaticamente se isso puder eliminar transações legítimas posteriores.
- Confirmar a recuperação do serviço.

Não realizar rollback de infraestrutura compartilhada.

## 7. Restrições

- Não modificar outros SaaS.
- Não executar limpeza de dados.
- Não alterar planos ou cobranças.
- Não alterar configurações globais do MySQL.
- Não modificar o histórico de auditoria.
- Não adicionar funcionalidades.
- Não realizar mudanças estruturais fora do escopo da release.

## 8. Entrega

Gerar `docs/VETORSMART-DEPLOY-08.md` e atualizar `executed.md`.

Registrar:

1. Versão anterior e nova versão.
2. Backup e verificação de recuperação.
3. Comandos efetivamente executados.
4. Serviços atualizados.
5. Resultados de saúde e smoke tests.
6. Migrações executadas ou justificativa da ausência.
7. Problemas identificados.
8. Situação final da produção.
9. Parecer: APROVADO ou BLOQUEADO.

Caso qualquer requisito crítico não seja atendido, interromper sem forçar a implantação.
