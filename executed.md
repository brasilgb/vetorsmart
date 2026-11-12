# Execução — VETORSMART-SERVICES-01.4 (Isolamento de testes e homologação na VPS)

Data: 12/11/2026. Diretório: `gateway/vetorsmart/`. Servidor: VPS de produção. Banco: MySQL 8.4.11.

## Instrução

- `correio.md` difere da versão executada anteriormente (SERVICES-01.3). Esta execução seguiu a versão atual (SERVICES-01.4).

## Resultado

**TÉCNICO (testes, Pint, tsc, build): APROVADO.** 332 de 337 testes aprovados e 5 pulados (concorrência, rodada à parte: 5 de 5). Única falha anterior corrigida no teste de auditoria (ordem de chaves JSON no MySQL 8.4).

**HOMOLOGAÇÃO NO NAVEGADOR (desktop, 390 px, multi-tenant, atendimento público): NÃO EXECUTADA.** Credenciais das contas de homologação indisponíveis; a busca foi negada pelo classificador de permissões e não foi contornada.

**LIBERAÇÃO: NÃO APROVADA.**

Atualização: banco `vetorsmart_test` removido pelo responsável; deploy realizado (ver abaixo). Detalhes em `docs/VETORSMART-SERVICES-01.4.md`.

## Ambiente de testes

| Item | Resultado |
|---|---|
| Container de teste | `vetorsmart-test-mysql` (MySQL 8.4.11), rede `vetorsmart-test-net`, sem portas publicadas. Parado ao final. |
| Banco e usuário | `vetorsmart_test`; usuário `vs_test` com privilégio só nesse banco. `root` não usado. |
| Código testado | Cópia isolada em scratchpad, com `.env` de teste. Nada gravado em `public/build` do repositório. |
| Configuração de teste | `APP_URL=http://localhost`, padrões de `config/platform.php` (esperados pelos testes). |
| Senhas | Fora do repositório e fora dos relatórios. |

## Verificações (resultados reais)

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | 337 testes: 332 aprovados, 5 pulados, 0 falhas |
| Correção | `tests/Feature/Tenancy/AuditLogTest.php`: `toBe` → `toEqualCanonicalizing` (MySQL 8.4 reordena chaves JSON; asserções mantidas). |
| `VETORSMART_CONCURRENCY=1 … MysqlConcurrencyTest.php` | 5 de 5 aprovados (MySQL 8.4.11) |
| `vendor/bin/pint --test` | Reprovado em 7 arquivos; corrigidos com `pint` (somente formatação); agora aprovado |
| `npx tsc --noEmit` | 0 erros (Wayfinder gerado na cópia isolada) |
| `npm run build` | Aprovado (cópia isolada) |

Arquivos corrigidos pelo Pint: `app/Policies/ServiceCatalogPolicy.php`, `app/Models/Customer.php`, `app/Http/Resources/Api/V1/{ServiceResource,ServiceCategoryResource,ProfessionalResource,CustomerResource}.php`, `app/Http/Controllers/Api/V1/CustomerController.php`.

## Homologação

- Site respondendo: `https://vetorsmart.com.br/` → 200; `/login` → 200.
- Não realizados: desktop, 390 px, isolamento das empresas A e B, site público e assistente, fluxo completo até a conclusão.
- Resultado: **não verificado**. Nada declarado como aprovado.

## Não realizado / restrições

- Homologação no navegador (acima).
- Nenhuma alteração no `.env` de produção, nos containers `vetorsmart`/`worker`/`scheduler`, em migrations ou em credenciais.
- Nenhuma conta, solicitação ou dado criado na produção.
- Deploy realizado com autorização: imagens `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler` reconstruídas e reiniciadas (`docker compose up -d --no-deps`). Sem migrations (nenhuma pendente). Rollback: tags `rollback-pre-services-01.4`.
- Nenhum commit, push ou merge.

## Pendências e riscos

1. Fornecer, por canal autorizado, as credenciais das contas de homologação A/B (ou outro método autorizado) para executar a homologação.
2. Pendências herdadas: verificadas em 12/11/2026. O banco `vetorsmart` de produção tem 0 usuários e 0 tenants; não existem contas `srv.owner.a/b@example.test` nem dados `[HOMOLOG-SRV]`. Nada foi removido. Confirmar se o homologação anterior ocorreu em outro banco.

**Atualização:** `php artisan db:seed --force` executado em produção. Seeder revisado antes: apenas `SegmentSeeder` (10 segmentos de referência, `updateOrCreate`, sem contas nem senhas). Resultado: `segments`=10; `users`=0; `tenants`=0.

## Critério final

Não atendido. O módulo não está validado nem homologado no ambiente real.
