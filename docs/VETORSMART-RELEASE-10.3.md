# VETORSMART-RELEASE-10.3 — Consolidação Git, Deploy e Capacidade da Hostinger KVM 4

**Data:** 17/11/2026, das 10:30 às 11:30 UTC  
**Ambiente:** VPS de produção `srv1990862` (Hostinger KVM 4), `gateway/vetorsmart/` e `/opt/infra-abrasil/`  
**Referência:** VETORSMART-RELEASE-10.2  
**Parecer:** **APROVADO COM RESTRIÇÕES** (seção 10)

## 1. Diagnóstico inicial

| Item | Situação encontrada |
|---|---|
| Aplicação | `main` em `c99c596`, igual à `origin/main`. As alterações da RELEASE-10.2 estavam em produção, mas não commitadas (21 arquivos alterados e 5 novos) |
| Infraestrutura | Checkout de produção em `feat/vetorsales-infra` (`c867f64`, igual ao remoto), com alterações locais de outros SaaS (`abrasilsistemas`, `vetorsales`, `vetoros`, `correio.md`), que não foram tocadas. A `origin/main` (`f95a866`) não tinha o VetorSmart nem o VetorSales |
| Containers | 24, todos saudáveis. Web do VetorSmart em `infra-abrasil-vetorsmart-2`, resultado do deploy da 10.2 |
| Nginx | `resolver 127.0.0.11 valid=10s`; o PHP do VetorSmart sem `fastcgi_connect_timeout` (valor padrão de 60 s) |
| Backup prévio | `backups/vetorsmart_pre_release10.3_174f230_20261117_103418/`: dump do banco (`Dump completed`, com `.sha256`), cópia de `nginx/conf.d` e `nginx.conf`, e saída de `nginx -T` efetiva |
| Imagens de rollback | `infra-abrasil-vetorsmart{,-worker,-scheduler}:rollback-pre-10.3` (as imagens em execução no início) |

## 2. Consolidação Git (etapa 1)

### Aplicação (`brasilgb/vetorsmart`)

O diff da RELEASE-10.2 foi revisado arquivo por arquivo. Não havia segredos nem alterações fora do escopo do relatório da 10.2.

| Commit | Conteúdo |
|---|---|
| `49e8fcc` | `feat(estabilidade)`: código e testes da RELEASE-10.2 (encerramento gracioso do PHP-FPM, CSP Report-Only, retenção em simulação, proteção dos tenants de homologação, correções visuais) |
| `174f230` | `docs(release)`: relatório da RELEASE-10.2 |
| commit seguinte | `chore(controle)`: `correio.md`, `executed.md` e este relatório |

### Infraestrutura (`brasilgb/infraabrasil`)

Nova branch **`feat/vetorsmart-infra`**, criada a partir da `origin/main` (`f95a866`), com um único commit, **`52988da`**, que contém só o que o VetorSmart precisa:

- `docker-compose.yml`: serviços `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler`, com o healthcheck atual; volume `vetorsmart-build`; montagens no Nginx; `VETORSMART_DB_PASSWORD` no MySQL.
- `nginx/conf.d/apps.conf`: blocos HTTP 80, `www` e apex de `vetorsmart.com.br`, já com `fastcgi_connect_timeout 2s`.
- `mysql-init/01-databases.sh`, `scripts/init-letsencrypt.sh`, `.gitignore` e `.env.example` (novo bloco VetorSmart).
- `.gitmodules` e submódulo `gateway/vetorsmart`.

**Ficou de fora** o que é do VetorSales em `feat/vetorsales-infra`: o serviço `vetorsales`, o vetorpet redirecionado para o VetorSales e o `/storage/` do vetorsales.

Verificações antes da integração:

| Verificação | Resultado |
|---|---|
| `docker compose config` com o `.env` de produção, comparando com o checkout de produção | Serviços `vetorsmart`, `-worker` e `-scheduler`, senha no MySQL, montagens do Nginx e volume **idênticos**. A única diferença é a ausência do VetorSales, que é intencional |
| `nginx -t` da configuração da branch, em container descartável com os certificados reais | `syntax is ok`, `test is successful` |
| `sh -n` dos scripts alterados | Sem erros |
| Ponteiros dos outros submódulos | Iguais aos da `origin/main` (vetoros, vetorleads, abrasilsistemas, desgarrados, vetorpet) |

Integração: a `main` avança por fast-forward até `feat/vetorsmart-infra`, e as duas recebem um commit que leva o submódulo ao último commit da aplicação. Na branch de produção `feat/vetorsales-infra`, foram commitados apenas o timeout do Nginx (**`3b3f229`**) e o avanço do submódulo. As alterações locais de outros SaaS ficaram como estavam.

**Situação depois desta release:** a `main` da infraestrutura passa a descrever o VetorSmart por completo. O servidor continua em `feat/vetorsales-infra`, porque é nela que está o VetorSales em produção. Levar o VetorSales para a `main` é outra decisão, fora do escopo.

Não foi executado `reset --hard`, `clean`, rebase ou push forçado.

## 3. Deploy sem interrupção (etapa 2)

### Causa da requisição sem resposta (RELEASE-10.2)

O Nginx guarda por até 10 s o resultado do DNS do Docker. Quando o container antigo sai da rede, o IP dele não recusa conexões: simplesmente não responde. Sem limite próprio, o Nginx esperava até 60 s (o `fastcgi_connect_timeout` padrão) antes de tentar outro endereço.

### Correções

1. **Nginx** (autorizado pelo correio): `fastcgi_connect_timeout 2s;` apenas no `location ~ \.php$` de `vetorsmart.com.br`. O processo foi: cópia da configuração anterior no backup, `nginx -t` e `nginx -s reload` (gracioso, sem reinício). Logo depois, os cinco sites responderam 200. Os outros SaaS não foram alterados. O limite vale só para abrir a conexão TCP: requisições lentas do PHP não são afetadas.
2. **Procedimento:** depois que a nova instância fica saudável, aguardar 12 s antes de parar a antiga. Assim, o Nginx já conhece os dois IPs quando o antigo some e repassa a requisição em até 2 s (`fastcgi_next_upstream error timeout`, o padrão).

Procedimento validado (script em `backups/vetorsmart_release10.3_evidencias/scripts/swap.sh`):

```sh
cd /opt/infra-abrasil
docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler
docker compose up -d --no-deps --no-recreate --scale vetorsmart=2 vetorsmart   # nova instância
# aguardar "healthy"; se não ficar, remover só a nova e manter a antiga
sleep 12                                                                       # Nginx passa a conhecer os dois IPs
docker stop <web antigo> && docker rm <web antigo>                            # encerramento gracioso (8 s)
sleep 15                                                                       # IP antigo sai do cache antes de ser reaproveitado
docker compose up -d --no-deps vetorsmart-worker vetorsmart-scheduler
```

Rollback: `docker tag infra-abrasil-<serviço>:rollback-pre-10.3 infra-abrasil-<serviço>:latest` nos três serviços e o mesmo procedimento.

### Teste controlado

Durante cada troca, uma sonda fez 5 requisições por segundo ao VetorSmart (`/` e `/login`) e 1 por segundo ao VetorOS, pelo Nginx local:

| Troca (17/11) | Imagem | Requisições VetorSmart | 200 | **502** | Sem resposta | > 1 s |
|---|---|---|---|---|---|---|
| 10:34:58 · mesma imagem (só o timeout) | `d9f7258` → `d9f7258` | 459 | 459 | **0** | 0 | 4 |
| 10:44:59 · deploy (web, worker, scheduler) | `d9f7258` → `eb4f7dc` | 405 | 405 | **0** | 0 | 9 |
| 10:46:34 · **rollback** para `rollback-pre-10.3` | `eb4f7dc` → `d9f7258` | 411 | 411 | **0** | 0 | 3 |
| 10:48:09 · reaplicação da release | `d9f7258` → `eb4f7dc` | 409 | 409 | **0** | 0 | 4 |
| **Total** | | **1.684** | **1.684** | **0** | **0** | 20 |

- VetorOS durante as quatro trocas: 338 requisições, todas com 200.
- Nginx: nenhum 502 no período. As 20 requisições acima de 1 s levaram cerca de 2,03 s: o log mostra `upstream timed out (110) while connecting` para o IP antigo, seguido da resposta da outra instância. É exatamente o comportamento esperado.
- A nova instância ficou saudável em 5 a 6 s. Worker e scheduler, recriados, ficaram saudáveis em cerca de 10 s.
- **Critério de aprovação atingido:** nenhuma requisição perdida e nenhum 502 nas trocas, inclusive no rollback.

A imagem `eb4f7dc` tem o mesmo código da `d9f7258`. A diferença é só documentação e controle (`docs/`, `correio.md`, `executed.md`), que o `COPY . .` leva para a imagem. O web passou a se chamar `infra-abrasil-vetorsmart-6`, porque o número muda a cada troca. Nenhum script depende desse nome.

## 4. Auditoria da Hostinger KVM 4 (etapa 3)

Coleta em 17/11, por volta das 10:39 UTC, com 4 dias e 33 min de uptime. Nada foi alterado ou removido.

### Recursos efetivos

| Recurso | Contratado | Efetivo |
|---|---|---|
| CPU | 4 vCPU | 4 vCPU AMD EPYC 9354P (KVM), 1 thread por núcleo |
| RAM | 16 GB | 15,6 GiB |
| Disco | 200 GB NVMe | 197 GB em `/` (`sda`, não rotacional) |
| Swap | — | 4 GiB em `/swapfile` |

### Consumo

| Item | Valor |
|---|---|
| CPU desde o boot | 6,2% usuário, 3,3% sistema, 0,8% softirq, **87,6% ocioso**, 0,1% iowait, **2,1% steal** |
| Load average | 0,71 / 0,66 / 0,53 (em 4 vCPU) |
| Pressão (PSI) | CPU `some` 1,2% (média de 300 s); memória e I/O `full` = 0 |
| Memória | 3,9 GiB em uso, **11,7 GiB disponíveis**, 10,3 GiB de cache de disco |
| Swap | 16 MiB usados de 4 GiB, sem troca ativa (`si`/`so` = 0) |
| OOM | `oom_kill 0`, nenhum container com `OOMKilled` |
| Disco | 73 GB de 197 GB (**39%**) |
| I/O | iowait de 0 a 0,1%. O MySQL acumula 31 GB gravados em 4 dias (cerca de 7,8 GB/dia, com o binlog incluído) |

### Por container (sem limites de CPU/RAM definidos)

| Grupo | Memória | Observação |
|---|---|---|
| MySQL 8.4 | 855 MiB | buffer pool de 512 MiB, 85% das páginas livres; acerto de cache de 99,99%; máximo de 14 conexões (de 151), em 13/11 às 13:00; `slow_query_log` ligado (1 s) e **0 consultas lentas**; binlog de 354 MB com retenção de 7 dias |
| WAHA | 538 MiB | maior consumidor depois do MySQL |
| n8n | 473 MiB | |
| abrasilsistema (web, worker, scheduler) | 165 MiB | |
| desgarrados | 162 MiB | |
| VetorOS | 158 MiB | |
| VetorLeads | 158 MiB | |
| VetorSales | 119 MiB | |
| **VetorSmart** | **114 MiB** | web 42, worker 36, scheduler 36; PHP-FPM `pm = dynamic`, `max_children = 5` |
| Redis | 18 MiB | 1 MB de dados, sem chaves e sem eviction (praticamente sem uso) |
| Nginx, phpMyAdmin | 27 e 81 MiB | |

Os workers de `vetorleads`, `abrasilsistema` e `desgarrados` têm de 7 a 9 reinícios, todos no boot de 13/11 às 10:40 e com código de saída 0. É a ordem de inicialização, não instabilidade.

### Disco e Docker

| Item | Total | Recuperável |
|---|---|---|
| Imagens | 38,4 GB (46) | 3,7 GB |
| Cache de build | 23,0 GB | 8,3 GB |
| Volumes Docker | 233 MB | 0 |
| `volumes/mysql` | 770 MB | — |
| `backups/` | 523 MB | — |

O VetorSmart guarda 9 gerações de rollback para cada um dos 3 serviços. Elas compartilham camadas, mas são o principal motivo de crescimento das imagens.

### Margem para deploy

A instância temporária custa cerca de 45 MiB por 15 a 30 s, o que equivale a 0,4% da memória disponível. O build da imagem é o momento mais pesado, mas ocupa uma fração pequena de 4 vCPU que estão quase 90% ociosas. **Há folga ampla.**

### Riscos e recomendações

1. **Logs do VetorSmart se perdem a cada deploy** (o risco mais relevante). O Laravel grava em `storage/logs` dentro do container (canal `single`, sem volume), e o scheduler grava na saída do container. Os dois somem quando o container é removido, inclusive os relatórios de CSP e os erros da aplicação. Recomendação: um volume `./volumes/vetorsmart-logs:/var/www/html/storage/logs` nos três serviços, com rotação diária. **Resolvido no mesmo dia, depois da autorização do proprietário (seção 11).**
2. **Não há histórico de métricas.** Sem `sysstat` nem monitoramento, os picos só aparecem em contadores acumulados (máximo de conexões do MySQL, PSI). Recomendação: `sysstat` (`sar`) ou um coletor leve antes de qualquer decisão de capacidade.
3. **Nenhum container tem limite de memória.** Hoje isso não é problema (11,7 GiB livres), mas um vazamento em qualquer SaaS afetaria todos. Recomendação: definir limites depois de algumas semanas de histórico.
4. **Um único MySQL atende todos os SaaS** e também guarda bancos de teste e exportação (`vetorpet_sim`, `vetorsales_vitta_test`, `vetorsales_import_test`, `vetorpet_export_*`, `vetorpet_vitta_src`, cerca de 19 MB). Recomendação: revisar e remover esses bancos com autorização.
5. **Crescimento de imagens e cache de build** (61 GB de 197 GB). Não é urgente com 39% de uso. Recomendação: manter 2 gerações de rollback por serviço e limpar o cache de build mensalmente, sempre com autorização.
6. **Conclusão de capacidade:** a KVM 4 está bem dimensionada para a carga atual, com cerca de 10% de CPU, 25% de memória efetiva e 39% de disco. O fator que mais pesa é o MySQL compartilhado, não a CPU.

## 5. RootAdmin e interface (etapa 4)

### Produção: bloqueado

Não há credencial de administrador da plataforma autorizada para os testes. O correio proíbe criar credenciais artificiais em produção, e nenhuma foi criada. Em produção, o login administrativo e as telas do RootAdmin **não foram homologados em navegador**.

### Validação da interface em navegador real (aplicação local)

Para não deixar a interface sem verificação, o mesmo código da produção (`174f230`, com o build de produção dos assets) rodou localmente:

- banco MySQL descartável (`vetorsmart_homolog_test`, em memória, removido ao final);
- `APP_KEY` próprio e nenhuma variável apontando para a produção;
- administrador e 6 empresas **fictícias** (uma `[HOMOLOG]` e uma suspensa), com 13 usuários e 9 agendamentos.

O roteiro (`scripts/rootadmin.js`) percorreu login, Visão Geral, Empresas (lista, busca e detalhe da empresa de homologação), Usuários e Acessos, Segmentos, Segurança e Auditoria, e Configurações (perfil e aparência). Combinações: temas claro e escuro, larguras de 390 e 1366 px (mais 768 no Chromium).

| Navegador | Verificações | Resultado | Violações WCAG 2 A/AA (axe-core 4.10.2) |
|---|---|---|---|
| Chromium 1.49.1 | 60 | **60/60** | 0 |
| Firefox | 40 | **40/40** | 0 |
| WebKit | 40 | **40/40** | 0 |

Cada verificação inclui: HTTP 200, tema aplicado, ausência de estouro horizontal, de erros de console e de respostas 5xx, ausência de violações de CSP, selo "Homologação" e indicadores sem a empresa de teste ("Empresas cadastradas: 5" com 6 no banco). O axe foi validado com um controle negativo, em que detectou contraste baixo, imagem sem `alt` e título ausente. Há 108 capturas.

Observações:

- **WebKit:** avisa que `frame-ancestors` é ignorado numa política Report-Only. É um aviso do navegador, não uma violação. A proteção contra framing já vem do `X-Frame-Options`, e a diretiva passa a valer quando a CSP for para `enforce`.
- **Itens "EM BREVE" do menu:** estão desabilitados (`aria-disabled`, opacidade 0,5), com contraste efetivo de cerca de 4,5:1 no tema escuro. A WCAG dispensa componentes inativos. Fica como sugestão estética, sem urgência.
- **Coluna Segmento/Status em "Cadastradas recentemente"** (1366 px): nomes longos de segmento ficam encostados no selo de status. Não há sobreposição.

Evidências: `backups/vetorsmart_release10.3_evidencias/rootadmin/`.

## 6. Segurança e retenção (etapa 5)

| Item | Resultado |
|---|---|
| CSP | Continua em `report-only` (`config:show csp`). O Nginx não registrou **nenhum `POST /api/csp-report`** desde que a política entrou na 10.2. Nos testes de navegador, houve 0 violações em Chromium, Firefox e WebKit. Não há violação legítima a corrigir. Desde a seção 11, os relatórios ficam em volume persistente |
| Exclusão automática | Desabilitada (`assistant.retention_deletion_enabled = false`). `assistant:purge --execute` recusa a execução |
| Simulação agendada | `30 6 * * * php artisan assistant:purge` no scheduler; a primeira execução automática é em 18/11 às 06:30 UTC. Simulação manual em 10:50: 0 conversas vencidas, nada removido |
| Proteção de comandos destrutivos | Confirmada na prática: `migrate:fresh` foi recusado num banco local sem o sufixo `_test` |
| Rate limiting, auditoria, sessões, permissões e isolamento | Sem alteração nesta release; cobertos pela suíte (seção 7) |
| MFA | Não reintroduzido |

## 7. Testes (etapa 6)

| Teste | Resultado |
|---|---|
| Suíte Laravel (MySQL 8.4 descartável) | **454 aprovados, 9 pulados, 0 falhas** (463 testes, 2.920 asserções) |
| Concorrência de agendamento em MySQL real (`VETORSMART_CONCURRENCY=1`) | **9/9** (são os 9 pulados na execução padrão) |
| Isolamento multi-tenant (filtro `tenant`/`isolation`) | 121/121 |
| Atendimento (`Assistant`) | 69/69 |
| Acesso público (sites, robots, sitemap) | 51/51 |
| PHPStan | 0 erros |
| TypeScript (`tsc --noEmit`) | 0 erros |
| Build de produção | `vp build` local e `docker compose build` dos três serviços, sem erros |
| Deploy e rollback | 4 trocas, 1.684 requisições, 0 com 502, 0 perdidas (seção 3) |
| Interface | 140/140 em três navegadores (seção 5) |

Antes de implantar, foram confirmados o backup válido (dump com `Dump completed` e checksum), o estado do Git (commits revisados) e as imagens de rollback. Não houve migrations.

## 8. Arquivos alterados

**Aplicação:** os da RELEASE-10.2, agora commitados em `49e8fcc` e `174f230`; `docs/VETORSMART-RELEASE-10.3.md`; `correio.md`; `executed.md`. Nenhum código novo nesta release.

**Infraestrutura:** `nginx/conf.d/apps.conf` (`fastcgi_connect_timeout 2s` no VetorSmart) e o ponteiro `gateway/vetorsmart`, além dos arquivos da branch `feat/vetorsmart-infra` listados na seção 2.

## 9. Situação dos demais SaaS

VetorOS, VetorSales, VetorLeads, abrasilsistemas, desgarrados, n8n e WAHA seguem saudáveis e sem alteração de configuração, imagem ou dados. A única ação comum foi o `nginx -s reload`, que é gracioso: logo depois, os cinco sites responderam 200, e o VetorOS respondeu 338 de 338 durante as trocas. Ao final, os 24 containers estavam `healthy`.

## 10. Pendências e parecer

### Pendências

1. ~~Persistir os logs do VetorSmart~~: **concluído** (seção 11).
2. **RootAdmin em produção:** homologar quando houver credencial de administrador autorizada.
3. **CSP em `enforce`:** reavaliar com os logs persistidos e algumas semanas de observação.
4. **Política de retenção:** decidir os prazos antes de habilitar `ASSISTANT_RETENTION_DELETION`.
5. **Infraestrutura:** levar o VetorSales para a `main` e o servidor de volta à `main`. É decisão do proprietário, fora do escopo.
6. **Capacidade:** histórico de métricas, limites de memória por container, limpeza de bancos de teste, rotação de imagens de rollback e do cache de build. Tudo isso depende de autorização.
7. Domínios próprios (Nginx e certificados), mantido da 10.2.

### Parecer final

**APROVADO COM RESTRIÇÕES.**

- **Aprovado:** Git consolidado (RELEASE-10.2 commitada e `main` da infraestrutura com o VetorSmart isolado do VetorSales); deploy sem 502 nem requisições perdidas, com rollback real validado; capacidade da KVM 4 avaliada com dados reais; testes sem falhas; nenhuma regressão nos demais SaaS.
- **Restrição:** RootAdmin não homologado em produção (falta credencial; a interface foi validada localmente). A outra restrição, logs sem persistência, foi resolvida na seção 11.

Restrições do correio cumpridas: DNS, certificados e dados reais de tenants intactos; sem MFA, sem migrations, sem limpeza do Docker, sem remoção de backups e sem credenciais nos relatórios.

## 11. Complemento: volume de logs (autorizado pelo proprietário em 17/11/2026)

**Problema:** os logs do Laravel ficavam em `storage/logs` dentro de cada container e se perdiam a cada troca, inclusive os relatórios de CSP e o resultado diário da simulação de retenção.

**Solução:**

- **Infraestrutura:** cada serviço monta o próprio diretório: `volumes/vetorsmart-logs/web`, `worker` e `scheduler` → `/var/www/html/storage/logs`. Os diretórios são separados porque worker e scheduler rodam como root e o PHP-FPM do web roda como `www-data`. Um arquivo comum, criado pelo root, ficaria sem permissão de escrita para o web e faria as requisições falharem. O log passou a ser diário (`LOG_STACK=daily`), com 14 dias de retenção (`VETORSMART_LOG_DAILY_DAYS`). A pasta entrou no `.gitignore`.
- **Aplicação** (`b2dea42`): o relatório diário de `assistant:purge` passa a ser gravado em `storage/logs/assistant-retention.log`, e não mais na saída do container.

**Execução:**

- Backup do compose em `backups/vetorsmart_pre_logs_20261117_110906/`.
- Imagens de rollback `:rollback-pre-logs`.
- Suíte com 454 aprovados, 9 pulados e 0 falhas; PHPStan e Pint sem erros.
- Deploy pelo procedimento da seção 3 às 11:12: **411 de 411 com 200, 0 erro 502**; VetorOS com 83 de 83.

**Comprovação:**

| Verificação | Resultado |
|---|---|
| Web (relatório de CSP de teste) | `web/laravel-2026-11-17.log`, dono `www-data` |
| Worker e scheduler (aviso de teste) | Um arquivo em cada diretório |
| Simulação de retenção pelo agendador | `scheduler/assistant-retention.log`, sem remover nada |
| Recriação de web, worker e scheduler | Os 4 arquivos ficaram intactos (`md5sum`). O novo web (`infra-abrasil-vetorsmart-8`) continuou gravando no mesmo arquivo. A troca teve 339 de 339 com 200 |

**Commits:**

- Infraestrutura: `03a5636` em `feat/vetorsales-infra` e `2bb5f4a` em `feat/vetorsmart-infra`/`main`. Na `main`, o compose renderizado do VetorSmart é idêntico ao de produção.
- Aplicação: `b2dea42` e o registro deste complemento.

As entradas de teste nos logs estão identificadas: `teste-persistencia-logs` e os documentos `/teste-release-10.3-logs` e `/teste-apos-troca`.

## 12. Complemento: limpeza do cache de build (autorizada pelo proprietário em 17/11/2026)

| Item | Antes | Depois do `docker builder prune -f` | Depois do build de teste |
|---|---|---|---|
| Cache de build | 28,1 GB (114 entradas) | 14,7 GB (60) | 18,3 GB (97) |
| Disco `/` | 81 GB (43%) | 72 GB (39%) | 78 GB (41%) |

- A limpeza foi sem `--all`. Imagens, volumes, containers e backups não foram tocados. Os 24 containers seguiram saudáveis.
- O build de teste do VetorSmart não aproveitou nenhuma etapa do cache (230 s): a limpeza também removeu o cache das etapas de dependências. Cada SaaS terá um primeiro build mais lento e depois volta ao normal.
- Com o armazenamento de imagens do containerd, o build de teste tirou da lista a imagem do web em execução (`ef2e5b7`), que perdeu a tag `latest`. O container não foi afetado. A nova `latest` (`df25adb`) tem o mesmo código, porque de `b2dea42` para `aa572cd` só mudou documentação. Worker e scheduler não mudaram.
- **Lição para as próximas limpezas:** marcar com uma tag de rollback a imagem em execução antes de qualquer build.
