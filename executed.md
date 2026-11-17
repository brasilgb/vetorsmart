# Execução: VETORSMART-RELEASE-10.3 — Consolidação Git, Deploy e Capacidade da Hostinger KVM 4

Data: 17/11/2026, das 10:30 às 11:30 UTC. Local: VPS de produção `srv1990862`, `gateway/vetorsmart/` e `/opt/infra-abrasil/`.

## Análise do correio

O `correio.md` mudou: a versão commitada era a `RELEASE-10.1`, este arquivo registrava a execução da `RELEASE-10.2` e o correio atual é a `RELEASE-10.3`. A release foi executada e o conteúdo anterior deste arquivo foi substituído.

## Parecer

**APROVADO COM RESTRIÇÕES.** O relatório completo está em `docs/VETORSMART-RELEASE-10.3.md`.

Restrições:

1. **RootAdmin não homologado em produção.** Não há credencial de administrador autorizada, e o correio proíbe criar uma. A interface foi validada em navegador real com a aplicação local, banco descartável e dados fictícios: 140 de 140 verificações e 0 violações WCAG.
2. **Os logs do VetorSmart não persistem entre deploys.** Eles ficam dentro do container, inclusive os relatórios de CSP. A correção é um volume para `storage/logs` e depende de autorização.

## 1. Commits

Aplicação (`brasilgb/vetorsmart`, `main`):

- `49e8fcc`: código e testes da RELEASE-10.2.
- `174f230`: relatório da RELEASE-10.2.
- Commit seguinte: `correio.md`, este arquivo e o relatório da 10.3.

Infraestrutura (`brasilgb/infraabrasil`):

- `52988da` (`feat/vetorsmart-infra`, a partir da `origin/main`): só o VetorSmart (serviços, Nginx, MySQL, certificado, submódulo), sem nada do VetorSales. A configuração renderizada é idêntica à de produção, e o `nginx -t` passou.
- A `main` avança por fast-forward até essa branch, e o submódulo vai para o último commit da aplicação.
- `3b3f229` (`feat/vetorsales-infra`, a branch em produção): `fastcgi_connect_timeout 2s` no VetorSmart e, em seguida, o avanço do submódulo. As alterações locais de outros SaaS não foram tocadas.

## 2. Alterações aplicadas em produção

```
backup: backups/vetorsmart_pre_release10.3_174f230_20261117_103418/ (dump + nginx)
docker tag ... :rollback-pre-10.3 (web, worker, scheduler)
nginx/conf.d/apps.conf: fastcgi_connect_timeout 2s no PHP do vetorsmart; nginx -t; nginx -s reload
docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler
4 trocas com instância temporária: mesma imagem, deploy, rollback, reaplicação
```

## 3. Entregas

| Etapa | Resultado |
|---|---|
| 1. Git | RELEASE-10.2 commitada. A branch limpa da infraestrutura foi integrada à `main`. Sem reset, clean ou push forçado |
| 2. Deploy | **1.684 requisições em 4 trocas, todas com 200: 0 com 502 e 0 perdidas**, inclusive no rollback para `rollback-pre-10.3`. O IP antigo é abandonado em 2 s. VetorOS respondeu 338 de 338 |
| 3. KVM 4 | 4 vCPU EPYC, 15,6 GiB e 197 GB. CPU 87,6% ociosa, 11,7 GiB livres, disco em 39%, swap quase sem uso, 0 OOM. MySQL com máximo de 14 conexões e 0 consultas lentas. VetorSmart usa 114 MiB. Recuperáveis: 3,7 GB em imagens e 8,3 GB em cache de build. Nada removido |
| 4. RootAdmin | Bloqueado em produção (restrição 1). Localmente: Chromium 60/60, Firefox 40/40, WebKit 40/40, claro e escuro, 390, 768 e 1366 px |
| 5. Segurança | CSP em Report-Only: nenhum relatório recebido no Nginx e 0 violações nos navegadores. Exclusão de conversas desabilitada; simulação agendada às 06:30 sem remover nada |
| 6. Testes | Suíte com 454 aprovados, 9 pulados e 0 falhas. Concorrência em MySQL real: 9/9. PHPStan e `tsc` sem erros. Build sem erros |

Evidências: `/opt/infra-abrasil/backups/vetorsmart_release10.3_evidencias/` (sondas, capturas, resultados e scripts, sem credenciais).

## 4. Pendências

1. Volume para os logs do VetorSmart: depende de autorização.
2. RootAdmin em produção, quando houver credencial autorizada.
3. CSP em `enforce`, depois de observar os relatórios.
4. Política de retenção, antes de `ASSISTANT_RETENTION_DELETION=true`.
5. VetorSales na `main` da infraestrutura: decisão do proprietário.
6. Capacidade: histórico de métricas, limites de memória, bancos de teste no MySQL de produção, rotação de imagens e do cache de build.
7. Domínios próprios.

## Restrições cumpridas

- VetorOS, VetorSales e VetorLeads sem alteração.
- DNS, certificados e dados reais de tenants intactos.
- Sem MFA, sem migrations, sem limpeza do Docker, sem remoção de backups.
- Nenhuma credencial em relatórios.
