# Execução: VETORSMART-RELEASE-10.3 — Consolidação Git, Deploy e Capacidade da Hostinger KVM 4

Data: 17/11/2026, das 10:30 às 11:30 UTC. Local: VPS de produção `srv1990862`, `gateway/vetorsmart/` e `/opt/infra-abrasil/`.

## Análise do correio

O `correio.md` mudou: a versão commitada era a `RELEASE-10.1`, este arquivo registrava a execução da `RELEASE-10.2` e o correio atual é a `RELEASE-10.3`. A release foi executada e o conteúdo anterior deste arquivo foi substituído.

## Parecer

**APROVADO COM RESTRIÇÕES.** O relatório completo está em `docs/VETORSMART-RELEASE-10.3.md`.

Restrições:

1. **RootAdmin não homologado em produção.** Não há credencial de administrador autorizada, e o correio proíbe criar uma. A interface foi validada em navegador real com a aplicação local, banco descartável e dados fictícios: 140 de 140 verificações e 0 violações WCAG.
2. ~~Os logs do VetorSmart não persistem entre deploys~~: **resolvido** depois da autorização do proprietário (seção 5).

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

1. ~~Volume para os logs do VetorSmart~~: concluído (seção 5).
2. RootAdmin em produção, quando houver credencial autorizada.
3. CSP em `enforce`, depois de observar os relatórios.
4. Política de retenção, antes de `ASSISTANT_RETENTION_DELETION=true`.
5. VetorSales na `main` da infraestrutura: decisão do proprietário.
6. Capacidade: histórico de métricas, limites de memória, bancos de teste no MySQL de produção, rotação de imagens e do cache de build.
7. Domínios próprios.

## 5. Complemento: volume de logs (autorizado em 17/11/2026)

- `volumes/vetorsmart-logs/{web,worker,scheduler}` montados em `storage/logs`, com log diário mantido por 14 dias. Os diretórios são separados porque worker e scheduler rodam como root e o web como `www-data`.
- O relatório diário de retenção vai para `storage/logs/assistant-retention.log` (`b2dea42`).
- Deploy às 11:12: 411 de 411 com 200, 0 erro 502.
- Os logs sobreviveram à recriação dos três containers, e o novo web continuou gravando.
- Commits da infraestrutura: `03a5636` (`feat/vetorsales-infra`) e `2bb5f4a` (`main`). Detalhes na seção 11 do relatório.

## 6. Complemento: limpeza do cache de build (autorizada em 17/11/2026)

- `docker builder prune -f` (sem `--all`) liberou **13,4 GB**: o cache caiu de 28,1 GB para 14,7 GB e o disco de 43% para 39%. Imagens, volumes, containers e backups não foram tocados, e os 24 containers seguiram saudáveis.
- Num build de teste do VetorSmart, nenhuma etapa veio do cache (230 s). A limpeza também levou o cache das dependências, então o primeiro build de cada SaaS vai ser mais lento. O cache já se recompôs em parte (18,3 GB) e o disco está em 41%.
- Efeito colateral: o build de teste moveu a `latest` do web para `df25adb`. Com o armazenamento containerd, a imagem anterior em execução (`ef2e5b7`) saiu da lista. O container segue saudável, e a `df25adb` tem o mesmo código (de `b2dea42` para `aa572cd` só mudou documentação).

## 7. Complemento: limpeza das imagens antigas de rollback (autorizada em 17/11/2026)

- Foram mantidas as 2 gerações mais recentes por serviço: `rollback-pre-logs` (código da 10.3) e `rollback-pre-10.3`. As 6 imagens mantidas foram testadas e executam normalmente.
- Foram removidas 7 gerações × 3 serviços = **21 tags**: `rollback-pre-10.2`, `-10.1-fixes`, `-10.1`, `-fd7fa5f`, `-ce8404b`, `-d2aec27` e `-bb29f39`. Antes de cada remoção, conferi que a imagem não estava em uso e não era compartilhada com uma tag mantida.
- Disco: de 78 GB para **65 GB (35%)**. Imagens: de 46,4 GB para 28,2 GB. Os 24 containers seguiram saudáveis e os cinco sites responderam 200.
- Não foram tocados os 6,6 GB recuperáveis restantes: imagens do VetorPet (desativado), Playwright (usada na homologação) e certbot (usada na renovação de certificados).

## 8. Complemento: remoção das imagens do VetorPet e do Playwright (autorizada em 17/11/2026)

- Removidos os 3 containers parados do VetorPet (eram eles que prendiam as imagens) e as imagens `infra-abrasil-vetorpet{,-worker,-scheduler}:latest`, além de `mcr.microsoft.com/playwright:v1.49.1-noble`.
- Antes, os logs e a configuração dos containers foram copiados para `backups/vetorpet_containers_pre_remocao_20261117_112758/`, sem variáveis de ambiente.
- Os dados do VetorPet ficaram intactos: `volumes/vetorpet`, volume `vetorpet-build` e banco no MySQL.
- Disco: de 65 GB para **61 GB (33%)**. Os 24 containers seguiram saudáveis; os cinco sites responderam 200 e o `vetorpet.com.br` continua redirecionando para o VetorSales (302).
- Atenção: o compose ainda declara o VetorPet, e o Nginx depende dele. Um `docker compose up -d` sem nome de serviço, ou sem `--no-deps`, vai reconstruir e subir o VetorPet a partir do código-fonte. Tirá-lo do compose é uma decisão à parte.

## 9. Complemento: VetorPet retirado do compose e do Nginx (autorizado em 17/11/2026)

- Na branch de produção (`2de969c`), saíram os serviços `vetorpet`, `-worker` e `-scheduler`, a declaração do volume `vetorpet-build`, a dependência e as montagens no Nginx, e o bloco `vetorpet.localhost`. Fiz `nginx -t` e um `reload` gracioso.
- Ficaram, de propósito: os redirecionamentos de `vetorpet.com.br` para o VetorSales, o certificado, o banco e a senha no MySQL (mudar o ambiente do MySQL o recriaria). Os dados em `volumes/vetorpet` e no volume Docker `vetorpet-build` estão intactos.
- A `main` não foi alterada: lá o VetorPet ainda é um serviço completo, e o redirecionamento faz parte das mudanças do VetorSales.
- Um *dry-run* de `docker compose up -d` não constrói nem sobe mais o VetorPet.
- O web do VetorSmart foi sincronizado com a `latest` (`df25adb`, mesmo código) pela troca sem interrupção: 383 de 383 com 200, nenhum 502.
- **Pendente:** recriar o Nginx para que o container perca as montagens antigas. São poucos segundos fora do ar para todos os sites. Enquanto não acontecer, as montagens antigas não têm efeito. Backup em `backups/pre_remocao_vetorpet_compose_20261117_112935/`.

## 10. Complemento: remoção dos bancos de teste do MySQL (autorizada em 17/11/2026)

- Removidos 6 bancos: `vetorpet_sim`, `vetorsales_vitta_test`, `vetorsales_import_test`, `vetorpet_export_20261109_181512`, `vetorpet_export_20261109_181827` e `vetorpet_vitta_src`. Eram cópias de simulação das migrations do VetorSales, criadas e alteradas pela última vez em 09/11.
- Antes da remoção, conferi que nenhum tinha usuário próprio ou conexão e que nenhuma configuração ou script os referenciava (só a documentação do VetorSales).
- Cada banco tem um dump completo (`Dump completed`, número de tabelas conferido) em `backups/bancos_teste_pre_remocao_20261117_113510/`, com `SHA256SUMS`.
- Depois da remoção: sites com 200, containers saudáveis e nenhum erro de banco nos logs dos SaaS.
- Não removidos (fora da autorização): os usuários `chat129_restore` e `chat130_restore`, com permissões em bancos que já não existem, e a permissão de `vetoros_user` em `vetoros_restore_test`, também inexistente.

## 11. Complemento: remoção dos usuários e permissões órfãos do MySQL (autorizada em 17/11/2026)

- `DROP USER` em `chat129_restore@%` e `chat130_restore@%`. Estavam desbloqueados, aceitavam conexão de qualquer host e só tinham permissão em bancos que já não existem.
- `REVOKE` da permissão de `vetoros_user` em `vetoros_restore_test`, um banco inexistente. A permissão em `vetoros` foi mantida.
- Antes, conferi que nenhuma configuração ou script usava esses usuários e que não havia conexões abertas. As permissões anteriores, sem hashes de senha, estão em `backups/mysql_usuarios_orfaos_pre_remocao_20261117_114554/`.
- Depois: cada usuário de aplicação tem permissão só no próprio banco. O VetorOS acessa o banco normalmente (194 migrations lidas, `/login` com 200, sem erro de acesso). Os sites responderam 200.

## 12. Complemento: VetorSales levado para a main da infraestrutura (autorizado em 17/11/2026)

- Merge `11ac9f0` na `main`: contém toda a `feat/vetorsales-infra` (44 commits) e toda a `main` anterior. A árvore resultante é a de produção, mais o bloco VetorSmart do `.env.example`.
- Os 7 conflitos vinham do VetorSmart adicionado nas duas branches, em posições diferentes. Resolvi tomando a árvore de produção inteira, para não duplicar blocos no compose e no Nginx.
- Publicado por fast-forward, sem força.
- **O servidor passou para a `main`.** Compose, Nginx, `.env`, submódulos e as alterações locais dos outros SaaS ficaram idênticos, e os 24 containers seguiram saudáveis.
- A partir de agora, os commits de infraestrutura vão para a `main`. As branches `feat/vetorsales-infra` e `feat/vetorsmart-infra` continuam no remoto, sem uso; removê-las depende de autorização.

## 13. Complemento: branches feat antigas apagadas do GitHub (autorizado em 17/11/2026)

- Apagadas de `brasilgb/infraabrasil`: `feat/vetorsales-infra` (`c3b6906`) e `feat/vetorsmart-infra` (`5b9eba5`). Antes, conferi que as duas estavam inteiramente contidas na `main`, sem nenhum commit fora dela. A remoção foi feita com `--force-with-lease`, para só acontecer se as branches ainda estivessem nesses commits.
- No remoto ficaram `main`, `abrasil-auth-ux-01` e `abrasil-auth-ux-03`, que não foram tocadas.
- As cópias locais também foram removidas (`git branch -d`, que só apaga branch já integrada). O checkout do servidor tem apenas a `main`.

## Restrições cumpridas

- VetorOS, VetorSales e VetorLeads sem alteração.
- DNS, certificados e dados reais de tenants intactos.
- Sem MFA, sem migrations, sem limpeza do Docker, sem remoção de backups.
- Nenhuma credencial em relatórios.
