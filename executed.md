# Execução: VETORSMART-SMTP-01 e VETORSMART-GROWTH-03.1

Data: 20/11/2026, das 17:50 às 18:06 UTC. Local: VPS de produção `srv1990862`, `gateway/vetorsmart/` (branch `main`, remoto `origin` = `brasilgb/vetorsmart`).

## Análise do correio

O `correio.md` mudou desde a última execução (DEPLOY-GROWTH-03, cuja cópia está em `backups/vetorsmart_growth03_20261120_170639/correio.md`). Agora traz duas etapas: SMTP-01 e GROWTH-03.1. As duas foram executadas, e o conteúdo anterior deste arquivo foi substituído.

## Parecer

- **SMTP-01: BLOQUEADA, sem nenhuma alteração.** O `.env` da infra-abrasil não tem o SMTP que o correio dá como configurado.
- **GROWTH-03.1: CONCLUÍDA.** A correção do filtro Canal e o item 7 estão em produção (`514e738`), sem indisponibilidade.

## 1. SMTP-01: bloqueio

- **Como o VetorSmart recebe o e-mail:**
  - `config/mail.php` lê `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME`.
  - No `docker-compose.yml` da infra, os serviços `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler` (estes dois por `extends`) recebem só `MAIL_MAILER: ${VETORSMART_MAIL_MAILER:-log}`.
  - As demais variáveis vêm do `.env` do app, que está com os valores de exemplo (`log`, porta 2525, remetente `hello@example.com`).
- **O que falta:**
  - O `.env` da infra-abrasil não tem nenhuma variável de e-mail. Só aparece `CERTBOT_EMAIL`, e não há `ABRASIL_MAIL_HOST`, `_PORT`, `_ENCRYPTION`, `_USERNAME` nem `_PASSWORD`.
  - VetorOS e VetorLeads usam, no compose, valores padrão para host, porta e usuário (`smtp.hostinger.com`, 465/ssl, `contato@abrasilsistemas.com.br`). Para a senha, o padrão é **vazio** (`${ABRASIL_MAIL_PASSWORD:-}`).
  - Não encontrei uma senha para transferir. A conferência do ambiente dos containers dos outros SaaS foi negada pela política de acesso a credenciais, e não insisti.
- **Nada foi alterado:** `MAIL_MAILER` continua `log`, os serviços não foram recriados por essa etapa e a confirmação por e-mail não foi homologada.
- **Para liberar:** grave `ABRASIL_MAIL_PASSWORD` (e, se quiser, as outras `ABRASIL_MAIL_*`) no `.env` da infra-abrasil, ou diga de onde a senha deve ser lida. Depois disso a etapa pode ser executada como está no correio.

## 2. GROWTH-03.1: correção do filtro Canal

- **Defeito confirmado:** no `GrowthController` (linha 106), `byChannel()` era chamado sem o filtro de canal, enquanto `byCommercialOrigin()` o recebia.
- **Correção (commit `514e738`):**
  - `GrowthMetrics::byChannel()` passou a receber `?RequestOrigin $origin` e usa o mesmo `scoped()` da origem comercial: empresa, período, canal e responsável.
  - O agrupamento em `RequestOrigin::channel()` não mudou: o registro histórico `portal` continua somado em "Site — formulário".
  - São 2 arquivos de app (+5/−4).
- **Teste de regressão:** novo teste em `GrowthCommercialTest`, com solicitações de painel, IA, formulário do site e portal histórico, mais uma de painel fora do período e uma de outra empresa. Ele confere:
  - sem filtro: as duas visões somam 4, e o formulário do site soma 2 (com o portal histórico);
  - com Painel em 30 dias: as duas visões somam 1, e a visão de canal mostra só Painel;
  - com Painel em 90 dias (período combinado com canal): as duas somam 2, só Painel;
  - com Formulário do site: as duas somam 1.
  - Sem a correção, o teste falha ("4 is identical to 1").

## 3. Revisão do item 7 (`4d13d1f`)

- **Implementação preservada sem mudanças:** 7 arquivos, +321/−13, sem migration.
  - `ServiceRequest::traceability()` reúne Origem comercial, Canal de entrada e Atendimento.
  - O detalhe da solicitação e o histórico da ficha do cliente mostram os três campos, e a ficha mostra também o Responsável.
- **O que a revisão conferiu:**
  - `attendanceRelations()` carrega só as conversas da empresa da solicitação, e `attendance()` filtra de novo por `tenant_id`;
  - a IA sem conversa (retenção) aparece como "Atendimento virtual · conversa não disponível";
  - o encaminhamento à equipe é reconhecido pelo motivo, pela situação ou pela resposta da equipe;
  - as permissões (`view`) não mudaram.
- **Validação** (MySQL 8.4 descartável):
  - `GrowthCommercialTest` + `RequestTraceabilityTest`: 20/20;
  - suíte completa: 889 testes, 874 aprovados, 15 pulados, 0 falhas;
  - PHPStan 0 erros, Pint ok, `tsc` ok, `vp build` ok;
  - `vp lint`: só 2 avisos antigos em `agenda/index.tsx`, que não foi alterado.
- **Telas em desktop e mobile:** não foram refeitas nesta etapa. A correção não altera o front, e as telas do `4d13d1f` foram verificadas com Playwright em 1366px e 390px na execução anterior (capturas em `backups/vetorsmart_growth03_20261120_170639/evidencias/`). Em produção, os três campos foram conferidos pelos dados da página (seção 4).

## 4. Publicação e homologação

- **Intervalo de deploy:** de `00e028a` a `514e738`, só os 2 commits do escopo (10 arquivos, +365/−17). Sem migrations e sem mudança em `composer.lock`, `package.json`, `yarn.lock` ou `Dockerfile`.
- **Integração:**
  - push da branch `feat/growth-03-item7`;
  - fast-forward da `main` para `514e738` e push. A `main` local e a `origin/main` estão iguais.
- **Ponto de retorno:**
  - as imagens em execução foram marcadas como `rollback-pre-growth031` nos três repositórios (web `9653bf0031e8`, worker `c49cd77d276d`, scheduler `cdacacb8b7ea`);
  - a pasta `backups/vetorsmart_growth031_20261120_180143/` guarda o mysqldump (permissão 600) com o `.sha256`, as cópias do `correio.md` e do `executed.md`, e o `probe.log`.
- **Troca sem 502** (`swap.sh` da RELEASE-10.3):
  - `vetorsmart-10` → `vetorsmart-11` (imagem `a32521f2edda`);
  - worker (`be9e7fd6c9c4`) e scheduler (`90745c32253c`) recriados;
  - os três healthy;
  - a sonda registrou **154/154 respostas 200**.
- **Meu Crescimento na empresa #10** (`[HOMOLOG] Homologação VetorSmart`, login por HTTP):

  | Consulta | Origem comercial | Canal de entrada |
  |---|---|---|
  | 30 dias | 2 (Não identificada 2) | 2 (Painel 1, Site — atendimento virtual 1) |
  | 30 dias + Painel | **1** (Não identificada 1) | **1** (só Painel) |
  | 90 dias | 2 | 2 |
  | 90 dias + Painel | 1 | 1 (só Painel) |
  | 90 dias + IA | 1 | 1 (só Site — atendimento virtual) |

  Antes da correção, 30 dias + Painel dava 1 × 2.
- **Solicitação #5:** Origem comercial "Não identificada", Canal "Painel (registro manual)", Atendimento "Equipe", sem responsável.
- **Ficha do cliente:** as duas solicitações do histórico mostram os campos de cada uma:
  - #5: Não identificada · Painel · Equipe;
  - #4: Não identificada · Site — atendimento virtual · Atendimento virtual.
- **Saúde depois da troca:**
  - web, worker e scheduler healthy;
  - 0 `ERROR` ou `CRITICAL` nos logs desde 18:03;
  - `migrate:status` sem pendências;
  - agenda do scheduler ativa;
  - vetorsmart.com.br, vetoros.com.br, abrasilsistemas.com.br e vetorleads.com.br respondem 200.

## Relatório final

| Item | Valor |
|---|---|
| SHA anterior (produção) | `00e028a` (imagem `9653bf0031e8`, tag `rollback-pre-growth031`) |
| SHA da correção | `514e738` |
| SHA do item 7 integrado | `4d13d1f` (na `main`, por fast-forward) |
| SHA em produção | `514e738` (imagem `a32521f2edda`) |
| Migrations | nenhuma |
| Totais observados (#10, 30 dias + Painel) | Origem 1 = Canal 1 |
| Testes | 889 (874 aprovados, 15 pulados, 0 falhas), PHPStan 0, tsc ok, build ok |

## Pendências

1. **SMTP-01:** bloqueada até haver a senha do SMTP institucional no `.env` da infra-abrasil. O `MAIL_MAILER` continua `log`, e a confirmação por e-mail das 4 contas legadas segue sem entrega.
2. **Dado de homologação:** a solicitação #5 continua na empresa #10. Sai com o `homolog:purge` quando quiser.
3. **Imagens `rollback-pre-*`:** foi somada a `rollback-pre-growth031`. Nenhuma foi removida.
4. **`correio.md` e `executed.md`:** continuam alterados localmente e sem commit.

## Restrições cumpridas

- Cobrança, Efí e autenticação: nenhuma configuração alterada.
- `.env`, compose, Nginx, MySQL e Redis da infra: não foram alterados.
- E-mail e serviços dos outros sistemas: intactos.
- Recursos temporários removidos: o MySQL descartável dos testes.
- Nenhuma credencial neste relatório.
