# VETORSMART-SERVICES-01.4 — Isolamento de testes e homologação na VPS

Data: 12/11/2026. Diretório: `gateway/vetorsmart/`. Servidor: VPS de produção. Banco de produção: MySQL 8.4.11.

## Resumo

| Item | Resultado |
|---|---|
| Ambiente de testes isolado | Concluído |
| Pint (formatação) | Aprovado após correção de 7 arquivos |
| TypeScript (`tsc --noEmit`) | Aprovado (0 erros) |
| Build de front-end | Aprovado, executado em cópia isolada |
| Testes automatizados | 332 aprovados, 5 pulados, 0 falhas (de 337) |
| Concorrência MySQL 8.4 | 5 de 5 aprovados |
| Homologação no navegador (desktop, 390 px, multi-tenant, atendimento público) | **Não executada** (sem credenciais autorizadas disponíveis) |

**Status técnico:** aprovado (ver seção 3).
**Status operacional:** homologação remota **não realizada**. Não declarar aprovação.

## 1. Ambiente de testes isolado

- Container dedicado `vetorsmart-test-mysql` (imagem `mysql:8.4`, MySQL 8.4.11), na rede Docker `vetorsmart-test-net`, **sem portas publicadas**. Container parado ao final da execução; pode ser reiniciado para reexecução.
- Banco `vetorsmart_test` nesse container, com usuário `vs_test` com privilégio apenas sobre `vetorsmart_test.*`. Não foi usado o `root`.
- Senhas guardadas fora do repositório, em arquivos de sessão com permissão 600. Nenhuma senha foi escrita em relatórios ou no repositório.
- Testes e build executados em **cópia isolada** do código (tracked files + `vendor/` + `node_modules/`), com `.env` de teste gerado a partir de `.env.example`. A cópia não grava em `public/build` nem em `storage/` do repositório.
- Configuração de teste: `APP_URL=http://localhost` (padrão esperado pela suíte, cujos testes usam hosts fixos `clima-total.vetorsmart.test` e `localhost`). Esta configuração foi escolhida porque a suíte foi escrita para ela; a configuração de produção (`vetorsmart.com.br`) fez 263 testes falharem por roteamento de host, sem relação com código.

**Não alterado nesta execução:** `.env` de produção, containers `vetorsmart`, `worker` e `scheduler`, `public/build` do repositório, migrations, credenciais de produção.

**Pendência de limpeza:** o banco `vetorsmart_test` criado no MySQL de produção na execução 01.3 continua existindo, vazio e sem uso. Não foi removido, pois a remoção depende de autorização.

## 2. Correções técnicas

### Pint

Sete arquivos reprovados foram corrigidos com `vendor/bin/pint` (somente formatação e imports; nenhuma regra de negócio alterada):

- `app/Policies/ServiceCatalogPolicy.php`
- `app/Models/Customer.php`
- `app/Http/Resources/Api/V1/ServiceResource.php`
- `app/Http/Resources/Api/V1/ServiceCategoryResource.php`
- `app/Http/Resources/Api/V1/ProfessionalResource.php`
- `app/Http/Resources/Api/V1/CustomerResource.php`
- `app/Http/Controllers/Api/V1/CustomerController.php`

`vendor/bin/pint --test` passa após a correção.

### Wayfinder e TypeScript

Rotas Wayfinder geradas no build da cópia isolada. `npx tsc --noEmit` retorna 0 erros.

### Build

`npm run build` executado na cópia isolada: concluído (exit 0). Nada foi gravado em `public/build` do repositório nem do volume de produção.

## 3. Testes automatizados

Comando: `php artisan test --compact`, na cópia isolada, contra o MySQL 8.4.11 de teste.

Resultado inicial: 331 aprovados, 1 reprovado, 5 pulados. Após a correção do teste (abaixo): **337 testes: 332 aprovados, 5 pulados, 0 falhas.**

Falha: `Tenancy/AuditLogTest` — "sensitive values are never stored in audit metadata".

- **Causa:** falha de teste dependente de ordenação, não de regra de aplicação. O teste compara o metadado com `toBe`, que exige a mesma ordem de chaves. O MySQL 8.4 armazena colunas JSON com as chaves reordenadas (confirmado: `CAST('{"nested":…,"fields":…}' AS JSON)` retorna `fields` antes de `nested`). Versões anteriores (MariaDB) preservavam a ordem de inserção, por isso a execução 01.2 passou com 332 aprovados.
- **Classificação:** falha de configuração/infraestrutura (diferença de SGBD). A sanitização de segredos funciona: as chaves `password` e `api_token` não são gravadas.
- **Correção aplicada** (autorizada): `toBe` → `toEqualCanonicalizing` em `tests/Feature/Tenancy/AuditLogTest.php`, mantendo as mesmas asserções. Suíte completa passa.

Concorrência: `VETORSMART_CONCURRENCY=1 php artisan test --compact tests/Feature/Operations/MysqlConcurrencyTest.php` — **5 de 5 aprovados** no MySQL 8.4.11.

## 4. Homologação no navegador

**Não executada.**

- O site está respondendo: `https://vetorsmart.com.br/` → 200; `https://vetorsmart.com.br/login` → 200.
- O roteiro exige login nas contas de homologação das empresas A e B. As credenciais estão em arquivos protegidos fora do repositório. Uma busca por essas credenciais foi negada pelo classificador de permissões e **não foi contornada**.
- Não foram criados dados, contas ou solicitações de homologação na produção.

Não executados: cadastro de serviços, profissionais, clientes, solicitações, triagem, agenda, remarcação com conflito, execução, cancelamento, conclusão e histórico em desktop; o mesmo fluxo em 390 px; isolamento das empresas A e B; site público e assistente com o fluxo Visitante → Atendimento → Solicitação → Triagem → Agendamento → Execução → Conclusão.

## 5. Segurança operacional

- Nenhuma operação destrutiva.
- Nenhuma alteração em cobrança, assinaturas, MFA, credenciais existentes ou dados de clientes.
- Nenhuma migration no banco operacional.
- Nenhum container de produção reiniciado.
- Nenhuma conta de homologação apagada ou desativada.
- Nenhum commit, push, merge ou deploy.

## 6. Estado final da produção

- Deploy autorizado realizado: imagens `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler` reconstruídas e reiniciadas (`docker compose up -d --no-deps`). Containers healthy; `https://vetorsmart.com.br/` e `/login` → 200; sem migrations pendentes; sem erros nos logs do app.
- Rollback: imagens com tag `rollback-pre-services-01.4` (estado anterior ao deploy).
- Banco `vetorsmart_test` do MySQL de produção: removido pelo responsável (não foi usado).

## 7. Pendências e riscos

1. **Homologação no navegador:** fornecer, por canal autorizado, as credenciais das contas de homologação A/B, ou indicar outro método autorizado. Sem isso, o módulo não pode ser declarado homologado.
2. **Pendências herdadas:** verificadas em 12/11/2026. O banco `vetorsmart` de produção tem 0 usuários e 0 tenants; não existem contas `srv.owner.a/b@example.test` nem dados `[HOMOLOG-SRV]`. Nada foi removido. Confirmar se a homologação anterior ocorreu em outro banco.

**Atualização:** `php artisan db:seed --force` executado em produção. Seeder revisado antes: apenas `SegmentSeeder` (10 segmentos de referência, `updateOrCreate`, sem contas nem senhas). Resultado: `segments`=10; `users`=0; `tenants`=0.

**Critério final do correio:** não atendido. O módulo não está validado nem homologado no ambiente real.
