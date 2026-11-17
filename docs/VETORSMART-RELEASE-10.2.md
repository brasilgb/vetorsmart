# VETORSMART-RELEASE-10.2 — Estabilidade e Consolidação

**Data:** 16 e 17/11/2026  
**Ambiente:** VPS de produção `srv1990862`, `gateway/vetorsmart/`  
**HEAD da aplicação:** `c99c596` (alterações desta release ainda não commitadas; push só a pedido)  
**Parecer:** **APROVADO COM RESTRIÇÕES** (seção 10)

Executada em duas sessões. A primeira fez diagnóstico, código, testes e build. A segunda, depois da autorização do proprietário para a instância temporária, fez o deploy, a homologação em navegador e este relatório.

## 1. Diagnóstico

| Item | Situação encontrada |
|---|---|
| Aplicação | `main` em `c99c596`, limpa antes desta release |
| Infraestrutura | `/opt/infra-abrasil` na branch `feat/vetorsales-infra` (`c867f64`, igual ao remoto). Alterações locais de outros SaaS (`abrasilsistemas`, `vetorsales`, `vetoros`, `correio.md`) não foram tocadas |
| Containers | `vetorsmart`, `vetorsmart-worker`, `vetorsmart-scheduler` saudáveis; demais SaaS sem alteração |
| Scheduler | Nenhuma tarefa agendada |
| Backup prévio | `backups/vetorsmart_pre_release10.2_c99c596_20261116_202333.sql.gz` (+ `.sha256`) |
| Imagens de rollback | `infra-abrasil-vetorsmart{,-worker,-scheduler}:rollback-pre-10.2` |

## 2. Consolidação do Git (item 1): interrompida e documentada

- `origin/main` da infraestrutura (`f95a866`) **não contém o serviço `vetorsmart`** no `docker-compose.yml` nem o submódulo `gateway/vetorsmart`. A `main` local (`9754bd0`) está um commit atrás da `origin/main`.
- Tudo o que se refere ao VetorSmart está apenas em `feat/vetorsales-infra`, em 31 commits que não estão na `main`. Entre eles:
  - `aa3b619`: adiciona o serviço vetorsmart (app, worker, scheduler) **e move código para `gateway/`**;
  - `42c2b55`: adiciona o vetorsmart ao Nginx **e encaminha o vetorpet para o vetorsales**;
  - `eb8a026` (MySQL), `d9a2ef1` (certificado), `e398dc8`, `4ff9ad2` (submódulo);
  - `33baf62`: troca o healthcheck (4 linhas no `docker-compose.yml` e o ponteiro do submódulo).
- `33baf62` altera um serviço que não existe na `main`. Não há como levar "só o healthcheck": seria preciso levar antes o serviço inteiro, e os commits que o criam misturam mudanças do VetorSales (Nginx do vetorpet, movimentação de código).
- Pela regra do correio ("se houver conflitos com alterações relevantes, documentar e interromper somente a consolidação Git"), nada foi transportado. Nenhum reset, rebase ou sobrescrita foi feito.
- Submódulo: `feat/vetorsales-infra` aponta `gateway/vetorsmart` para `c99c596`, que é o HEAD da aplicação.

**Decisão pendente do proprietário:** mesclar `feat/vetorsales-infra` inteira na `main`, já que o VetorSales está em produção com ela, ou montar uma branch a partir da `origin/main` com os commits do VetorSmart separados à mão, incluindo o trecho do vetorsmart em `42c2b55`.

## 3. Indisponibilidade durante deploy (item 2)

### Causa dos 502 da RELEASE-10.1

1. O `docker compose up -d` recria o container web parando o antigo antes de criar o novo. Com um único container, sempre existe uma janela sem PHP-FPM.
2. O Nginx resolve `vetorsmart` pelo DNS do Docker (`resolver 127.0.0.11 valid=10s`, `fastcgi_pass $php_upstream`) e guarda o IP por até 10 s. O IP antigo ficou livre e foi reaproveitado pelo scheduler recriado em seguida. Nesse intervalo, o Nginx falava com um container sem PHP-FPM na porta 9000.
3. O PHP-FPM recebia o sinal de parada sem tempo para terminar as requisições em andamento.
4. Os caches do Laravel são gerados na inicialização (cerca de 2 s) e não causavam os 502: o healthcheck só passa depois deles.

### Solução

- **Imagem** (`Dockerfile`): `process_control_timeout = 8s`. Com o `SIGQUIT` de parada, o PHP-FPM conclui o que está em andamento antes de sair, dentro dos 10 s que o Docker espera.
- **Procedimento de deploy com instância temporária**, autorizado pelo proprietário. Não muda o Nginx, o compose nem os outros SaaS:

```sh
cd /opt/infra-abrasil
docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler
docker compose up -d --no-deps --no-recreate --scale vetorsmart=2 vetorsmart   # nova instância, imagem nova
# aguardar a nova ficar "healthy" (docker inspect -f '{{.State.Health.Status}}' ...)
docker stop <web antigo> && docker rm <web antigo>                            # encerramento gracioso
sleep 15                                                                       # cache DNS do Nginx expira
docker compose up -d --no-deps vetorsmart-worker vetorsmart-scheduler
```

A instância extra usou cerca de 45 MiB por 15 s. Depois da troca, o web passou a se chamar `infra-abrasil-vetorsmart-2`. Nenhum script ou cron usa o nome antigo; só o relatório da RELEASE-10 o menciona. Na próxima troca, o nome volta a alternar.

### Comprovação (deploy de 17/11, 10:11 a 10:12 UTC)

Sonda com 5 requisições por segundo em `/` e `/login`, pelo Nginx local, durante toda a troca:

| Métrica | Resultado |
|---|---|
| Requisições | 298 |
| HTTP 200 | 297 |
| **HTTP 502** | **0** (eram cerca de 5 s de 502 na RELEASE-10.1) |
| Sem resposta | 1 |
| Tempo médio | 82 ms |

Linha do tempo: a nova instância ficou saudável em 7 s. O web antigo foi parado às 10:11:25. Worker e scheduler foram recriados às 10:11:41 e estavam saudáveis às 10:11:49.

**Restrição remanescente:** a requisição sem resposta saiu às 10:11:26, um segundo depois da remoção do container antigo. O Nginx ainda tinha o IP antigo em cache e ficou esperando a conexão (`urt=14.990`) até a sonda desistir aos 15 s, com 499 no log. Um IP que sumiu da rede não recusa a conexão, apenas não responde. Sem limite próprio, o Nginx espera até 60 s (`fastcgi_connect_timeout` padrão) antes de tentar a outra instância. Na prática, durante os até 10 s seguintes à remoção, uma parte das requisições pode ficar lenta em vez de falhar.

Correção proposta, **que depende de autorização por alterar o Nginx**: acrescentar `fastcgi_connect_timeout 2s;` apenas no `location ~ \.php$` do `vetorsmart.com.br`. A requisição presa passaria à outra instância em até 2 s (`fastcgi_next_upstream error timeout` já é o padrão). Basta um `nginx -s reload`, sem reinício. Não foi aplicada.

## 4. Proteção dos tenants de homologação (item 3)

Empresas `[HOMOLOG] Homologação VetorSmart` (id 10) e `[HOMOLOG] Empresa B Isolamento` (id 11) mantidas com as credenciais. A regra é `Tenant::isHomolog()`: nome iniciado por `[HOMOLOG]`, a mesma convenção do `homolog:purge`.

| Requisito | Implementação | Verificação |
|---|---|---|
| 1–2. Não indexável, `noindex`, fora do sitemap | `SiteController`: `noindex, nofollow` no `<meta>` e no `X-Robots-Tag` da página inicial, da página de serviços e do atendimento, qualquer que seja a configuração de SEO. Fora de `indexableLocations()`, então não entra no sitemap | Navegador, com o site publicado por alguns minutos: meta e cabeçalho `noindex, nofollow` nas 3 páginas e 3 larguras; sitemap só com `https://vetorsmart.com.br/`. Teste automatizado |
| 3. Fora dos indicadores | Indicadores comerciais já excluíam as empresas de teste desde a RELEASE-10.1 | Teste do RootAdmin |
| 4. Sem comunicações, cobranças ou integrações reais | Não há código de cobrança, e-mail transacional ou WhatsApp por empresa (`MAIL_MAILER=log`, `ASSISTANT_PROVIDER=none`; WAHA configurado, sem uso no código). Nada a bloquear hoje | Busca no código |
| 5. Identificação no RootAdmin | Selo "Homologação" na lista, no detalhe e no painel do RootAdmin (`is_homolog`) | Teste automatizado. Sem navegador: não há credencial de administrador disponível |
| 6. Não confundir visitantes | Faixa "Ambiente de teste da VetorSmart. Esta não é uma empresa real…" no site e no atendimento | Navegador (capturas `site-*`) |

O site de homologação foi publicado apenas durante a verificação e retirado ao final. Depois da retirada, o site voltou a responder 404 e o painel mostra "Não publicado".

## 5. Correções visuais (item 4)

As três observações registradas na RELEASE-10.1:

| Observação | Correção | Validação |
|---|---|---|
| Atendimento no celular: nome longo espremido ao lado do indicador | Cabeçalho com `flex-wrap`; nome em linha própria, indicador e "Voltar ao site" abaixo | Captura `site-atendimento-390.png`; sem rolagem horizontal em 390/768/1366 |
| Detalhe da solicitação: conversa da mais recente para a mais antiga | `AssistantConversations`: `latest('id')` somava-se à ordenação da relação; trocado por `reorder('id','desc')` e reversão, então ficam as últimas 12 mensagens em ordem cronológica | Teste automatizado. Resumos já gravados antes da correção mantêm a ordem antiga |
| Página inicial: texto institucional repetido | Removida a segunda ocorrência em "Sobre a empresa" | Teste automatizado e capturas `site-home-*` |

Sem redesign. Páginas públicas (landing, login, cadastro) e painel (dashboard, Meu Site, serviços, agenda, atendimento IA, clientes, solicitações, configurações, perfil) foram conferidos nas larguras de 390, 768 e 1366 px, nos modos **claro e escuro**: sem rolagem horizontal e sem erros de JavaScript ou console.

## 6. CSP e retenção (item 5)

### CSP

- Middleware `ContentSecurityPolicy`, config `config/csp.php`, modo `report-only` por padrão (`CSP_MODE=off|report-only|enforce`).
- Política: `default-src 'self'`; `script-src 'self' 'nonce-…'`, sem `unsafe-inline` e sem `unsafe-eval` para scripts; `style-src 'self' 'unsafe-inline'`; `img-src 'self' data: blob:`; `font-src 'self' data:`; `connect-src 'self'`; `object-src 'none'`; `base-uri 'self'`; `form-action 'self'`; `frame-ancestors 'self'`.
- Exceções identificadas:
  - nonce nos scripts em linha (tema em `app.blade.php` e atendimento) e nos gerados pelo Vite;
  - `unsafe-inline` só em estilos, porque Inertia e React, as páginas do site e o atendimento usam atributos `style`;
  - `data:` e `blob:` em imagens, para prévia de upload.
- Relatórios em `POST /api/csp-report`: público, sem sessão, limite de 60 por minuto. Query strings descartadas, corpos grandes ou malformados ignorados, registro no log.
- **Resultado em navegador:** 0 violações em todas as páginas e larguras homologadas (`csp-violations*.txt`). Cabeçalho `Content-Security-Policy-Report-Only` confirmado em produção.
- **Próximo passo:** observar os relatórios por alguns dias de tráfego real e, sem violações legítimas, passar a `CSP_MODE=enforce`.

### Retenção das conversas

- Modelo: `AssistantConversation` por tenant, com prazo por empresa em `AssistantSetting` (padrão `default_retention_days = 30`).
- `assistant:purge` reescrito:
  - por padrão, apenas **relata** por empresa: prazo, conversas vencidas, abertas, mensagens e a mais antiga;
  - `--execute` só é aceito com `ASSISTANT_RETENTION_DELETION=true`, que está desligado, e respeita o filtro por empresa.
- Agendado diariamente às 06:30 em simulação (`schedule:list` confirma), com `withoutOverlapping`. O relatório vai para o log do container scheduler.
- **Nenhuma conversa foi excluída.** A simulação não foi executada à mão em produção nesta sessão: o comando foi bloqueado pela política de permissões do agente. A primeira execução agendada será em 18/11, às 06:30 UTC, e aparece em `docker logs infra-abrasil-vetorsmart-scheduler-1`.
- **Pendente:** definir a política de retenção. Só depois disso ativar a exclusão.

## 7. Desempenho e Docker (item 6)

| Recurso | Depois do deploy |
|---|---|
| Web | 45 MiB, CPU de 0,01% |
| Worker e scheduler | 36 MiB cada |
| MySQL | 855 MiB |
| Memória do host | 4,0 GiB usados de 15,6 GiB (11,9 GiB disponíveis) |
| Disco `/` | 73 GB usados de 197 GB (39%) |
| Carga | 1,94 / 1,03 / 0,62 (durante o Playwright) |
| Tempo médio por requisição (sonda) | 82 ms |
| Worker e scheduler | sem erros; 0 jobs falhos |

Docker (`docker system df`): 46 imagens com 38,4 GB, dos quais 3,7 GB recuperáveis, e 23,0 GB de cache de build, dos quais 8,3 GB recuperáveis. 24 tags do VetorSmart.

Recomendação de limpeza, **não executada**:

- manter `rollback-pre-10.2` e `rollback-pre-10.1-fixes`;
- remover as tags `rollback-pre-10.1`, `rollback-pre-bb29f39` e anteriores do VetorSmart;
- rodar `docker builder prune` com filtro de idade, por exemplo `--filter until=168h`.

Tudo isso depende de autorização.

## 8. Testes (item 7)

### Automatizados

Suíte completa com banco descartável em loopback: **454 aprovados, 9 pulados por desenho, 0 falhas**. Análise estática (PHPStan) sem erros; TypeScript (`tsc --noEmit`) sem erros. São 10 testes novos em `tests/Feature/Platform/Release102Test.php`:

- noindex, sitemap e faixa do site de homologação;
- texto institucional único;
- selo no console;
- ordem das mensagens;
- CSP com nonce e sem fonte insegura para scripts;
- modos da CSP;
- relatórios de violação;
- retenção em simulação;
- recusa da exclusão sem habilitação;
- agendamento.

### Navegador real (Chromium 1.49.1, contra a produção)

- `10-homolog`: 154 de 160 verificações aprovadas. As 6 reprovações eram erros do próprio roteiro, que tentou abrir `/atendimento` no painel; a rota do painel é `/atendimento-ia`.
- `11-painel`: repetição do painel com as rotas corretas, **158 de 158 aprovadas**.

Itens da regressão pedida:

- **Landing, login, cadastro, dashboard, Meu Site, serviços, agenda, atendimento:** 200, sem rolagem horizontal e sem erros, em 3 larguras e 2 temas.
- **Atendimento público:** saudação e serviços do tenant de teste, com faixa.
- **SEO e sitemap:** sitemap central sem empresas de teste; `robots.txt` responde.
- **Tenant de homologação:** não indexável (seção 4).
- **Containers:** web, worker e scheduler saudáveis; demais SaaS sem alteração.
- **Agendamento e operações com serviços:** cobertos pela suíte automatizada. O fluxo completo em navegador foi feito na RELEASE-10.1, e esta release não muda esses fluxos.
- **Isolamento multi-tenant:** coberto pela suíte, com os testes de acesso cruzado.
- **RootAdmin:** sem navegador, por falta de credencial de administrador; coberto por teste.

Evidências em `/opt/infra-abrasil/backups/vetorsmart_release10.2_homolog/`: `shots/` (capturas), `10-homolog.json`, `11-painel.json`, `csp-violations*.txt` e `scripts/`.

**Clima Total:** nenhum dado alterado. O site continua despublicado, por decisão do proprietário dela na RELEASE-10.1; ela tem 0 clientes, 0 solicitações, 0 conversas e 0 serviços.

## 9. Arquivos alterados (aplicação)

| Arquivo | Motivo |
|---|---|
| `Dockerfile` | Encerramento gracioso do PHP-FPM |
| `app/Models/Tenant.php` | `isHomolog()` |
| `app/Http/Controllers/Site/SiteController.php`, `resources/views/website/site.blade.php` | noindex, sitemap e faixa de teste; texto institucional único |
| `app/Http/Controllers/Site/AssistantController.php`, `resources/views/website/atendimento.blade.php` | noindex, faixa, cabeçalho responsivo, nonce |
| `app/Http/Controllers/RootAdmin/{Dashboard,Tenant}Controller.php`, `resources/js/pages/rootadmin/**` | Selo de homologação |
| `app/Services/Assistant/AssistantConversations.php` | Ordem das mensagens no resumo |
| `app/Http/Middleware/ContentSecurityPolicy.php`, `config/csp.php`, `app/Http/Controllers/Security/CspReportController.php`, `routes/api.php`, `bootstrap/app.php`, `resources/views/app.blade.php` | CSP em modo relatório |
| `app/Console/Commands/PurgeAssistantConversations.php`, `config/assistant.php`, `routes/console.php` | Retenção em simulação e agendamento |
| `tests/Feature/Platform/Release102Test.php`, `tests/Feature/Assistant/AssistantTest.php` | Testes |

Infraestrutura: nenhum arquivo alterado. Nginx, compose, DNS e certificados ficaram como estavam.

## 10. Pendências

1. **Consolidação Git da infraestrutura:** decisão do proprietário (seção 2).
2. **`fastcgi_connect_timeout 2s` no Nginx do VetorSmart**, para eliminar a lentidão residual da troca (seção 3); depende de autorização.
3. **Commit e push** das alterações desta release; avanço do submódulo na infraestrutura depois do commit.
4. **CSP:** acompanhar os relatórios e passar a `enforce`.
5. **Retenção:** definir a política. A primeira simulação agendada roda em 18/11, às 06:30 UTC.
6. **RootAdmin em navegador**, quando houver credencial de administrador disponível; Safari e Firefox reais.
7. **Limpeza de imagens e cache de build**, mediante autorização.
8. Domínios próprios (Nginx e certificados), herdado da RELEASE-10.1.

## Resultado final

**APROVADO COM RESTRIÇÕES.**

- Aplicação funcional em produção.
- Tenants de teste não indexáveis e identificados.
- Ajustes visuais validados em 390, 768 e 1366 px.
- CSP em modo relatório sem violações.
- Retenção agendada só em simulação.
- **Deploy sem nenhum 502**, comprovado por medição.

As restrições são:

- a consolidação Git, interrompida por depender de mudanças do VetorSales;
- a lentidão de até 10 s para parte das requisições logo após a remoção do container antigo, com correção proposta no Nginx que aguarda autorização.

Restrições cumpridas: outros SaaS intactos; sem MFA, sem migrations, sem alteração em DNS, certificados ou Nginx; sem exclusão de dados, backups ou imagens; sem push; nenhuma credencial impressa.
