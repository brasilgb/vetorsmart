# VETORSMART-RELEASE-10.1 — Estabilização e Homologação de Produção

**Data:** 16/11/2026, 19:26 a 20:06 UTC
**Ambiente:** VPS de produção `srv1990862`, `gateway/vetorsmart/`
**Branch:** `main`. HEAD inicial `fd7fa5f`; as alterações desta release foram registradas em commits locais após a homologação, sem push (seção 2)

## Resultado final

**APROVADO COM RESTRIÇÕES.**

Sites públicos e atendimento funcionam em produção, com isolamento entre empresas, caches regenerados a cada início de container e experiência desktop e mobile conferida em navegador real (Chromium via Playwright, contra `https://vetorsmart.com.br`).

Restrições:

1. **Os commits desta release existem só nesta VPS.** Foram feitos depois da homologação, a pedido, e não foram enviados ao remoto (seção 2).
2. **Navegador único.** A homologação visual usou Chromium headless em perfil desktop (1366 px), tablet (768 px) e iPhone 13 (390 px). Safari e Firefox reais não foram usados.
3. **Console RootAdmin não aberto em navegador.** Não há credencial de administrador disponível para a homologação, e ela não foi criada nem redefinida. Os indicadores foram conferidos por teste automatizado e por leitura direta em produção.
4. **Duas empresas de teste permanecem em produção**, com o site de uma delas publicado e indexável (seção 5).
5. **Domínios próprios continuam sem atendimento no Nginx** (seção 9).

## 1. Diagnóstico inicial

- Git: `main` em `fd7fa5f`, igual ao esperado. Havia alterações locais da própria RELEASE-10.1, iniciada numa sessão anterior: `Dockerfile`, `docker-entrypoint.sh`, `docker-healthcheck.php` e o healthcheck no `docker-compose.yml` da infraestrutura. Nada foi sobrescrito.
- Containers: 24 de 24 saudáveis. Os três do VetorSmart já rodavam o entrypoint novo desde 19:32 UTC.
- Banco: 36 migrations executadas, nenhuma pendente. Nenhuma migration nesta release.
- Filas: driver `database`, 0 jobs pendentes, 0 falhos. Sessão e cache em banco; o Redis não é usado pelo VetorSmart.
- Caches: antes desta release eram gerados à mão no container web e sumiam na recriação.
- Empresa de teste: `[HOMOLOG] Homologação VetorSmart` já cadastrada às 19:34 UTC pela sessão anterior, pelo formulário público. Sem serviços, sem site.
- Clima Total: às 19:25 UTC o site dela foi publicado e retirado do ar 28 segundos depois, pelo proprietário, de um navegador externo (Firefox, IP externo). Não foi ação desta execução. O site segue fora do ar.

## 2. Arquivos modificados

No repositório `gateway/vetorsmart`:

| Arquivo | Alteração |
|---|---|
| `docker-entrypoint.sh` | Gera os caches a cada início; recusa subir com configuração incompleta |
| `docker-healthcheck.php` (novo) | Saúde real: caches, `APP_KEY`, banco, PHP-FPM |
| `Dockerfile` | Copia o healthcheck; `expose_php=Off` |
| `resources/views/website/site.blade.php` | Correção da fonte do site público |
| `resources/js/pages/tenant/settings/index.tsx` | Correção da tela de horários de funcionamento |
| `app/Http/Controllers/RootAdmin/DashboardController.php` | Empresas de homologação fora dos indicadores |
| `app/Http/Middleware/SecurityHeaders.php` (novo), `bootstrap/app.php` | Cabeçalhos de segurança |
| `tests/Feature/Websites/WebsiteTest.php`, `tests/Feature/RootAdmin/ConsoleTest.php`, `tests/Feature/Platform/PlatformStartTest.php` | Três testes novos |
| `docs/VETORSMART-RELEASE-10.1.md`, `executed.md` | Relatório |

No repositório da infraestrutura (alteração feita na sessão anterior): `docker-compose.yml`, healthcheck dos serviços `vetorsmart*`.

### Commits

Feitos às 20:05 UTC, depois do deploy e da homologação, sem push. As imagens em produção foram construídas antes, do mesmo conteúdo que está em `c7b8477`; os commits seguintes só trazem documentação.

Aplicação, branch `main`, 8 commits à frente de `origin/main`: os sete abaixo e um oitavo, só de documentação, com a atualização deste relatório e do `executed.md`.

| Commit | Conteúdo |
|---|---|
| `1fb41cb` | `fix(deploy)`: caches a cada início do container, healthcheck novo e `expose_php=Off` |
| `4976b2d` | `fix(sites)`: fonte do site público, com teste |
| `b07f0df` | `fix(configuracoes)`: horários de funcionamento |
| `1aa9edb` | `fix(rootadmin)`: empresas de homologação fora dos indicadores, com teste |
| `c7b8477` | `feat(seguranca)`: cabeçalhos de segurança, com teste |
| `89ec6da` | `docs(release)`: relatórios da RELEASE-10 e da RELEASE-10.1 |
| `6bb89aa` | `chore(controle)`: `correio.md` e `executed.md` |

Infraestrutura, branch `feat/vetorsales-infra` (a que estava ativa), 2 commits à frente do remoto: o abaixo e um segundo, que só avança o submódulo `gateway/vetorsmart` para o commit de documentação.

| Commit | Conteúdo |
|---|---|
| `33baf62` | `chore(deploy)`: healthcheck novo no `docker-compose.yml` e submódulo `gateway/vetorsmart` de `f2b898b` para `6bb89aa` |

Ficaram de fora, por não serem desta release: `correio.md`, `executed.md`, `gateway/correio.md`, `gateway/executed.md` e os submódulos `abrasilsistemas`, `vetorsales` e `vetoros` do repositório da infraestrutura, que seguem modificados.

Não alterados: `.env`, Nginx, DNS, certificados, MySQL, Redis, demais SaaS.

## 3. Correção dos caches

`docker-entrypoint.sh` identifica o papel do container pelo comando (`php-fpm` = web, `queue:work` = worker, `schedule:work` = scheduler; qualquer outro = comando avulso) e, nos três papéis de longa duração:

1. Confere as variáveis obrigatórias (`APP_KEY`, `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `PLATFORM_CENTRAL_HOST`). Faltando alguma, o container não sobe. O log traz só os nomes.
2. Apaga os caches antigos e roda `config:cache`, `route:cache` e `view:cache` como `www-data`, com as variáveis reais do container.
3. Confere que os arquivos existem e que o `APP_ENV` em cache é o do container.

Concorrência: `bootstrap/cache` e `storage/framework` ficam no sistema de arquivos de cada container, então cada um gera o seu, sem disputa. O único volume compartilhado é o do build do front, e só o container web escreve nele.

Saúde: o healthcheck antigo (`php-fpm -t`) passava mesmo sem cache e sem banco. O novo falha se faltar cache, `APP_KEY`, conexão com o banco ou, no web, se o PHP-FPM não aceitar conexões. Imagens antigas, sem o script, caem na verificação antiga, o que mantém o rollback de imagem funcionando.

Caminhos de falha testados em containers descartáveis, sem tocar nos de produção:

| Cenário | Resultado |
|---|---|
| Web com `DB_PASSWORD` vazio | Não inicia: `variáveis obrigatórias ausentes ou vazias: DB_PASSWORD`, saída 1 |
| Worker com `APP_KEY` vazio | Não inicia: mesma mensagem com `APP_KEY`, saída 1 |
| Healthcheck sem caches | `unhealthy: caches do Laravel ausentes`, saída 1 |
| Healthcheck com banco inacessível | `unhealthy: banco de dados inacessível (código 2002)`, saída 1 |

## 4. Recriação dos containers

Feita duas vezes: às 19:32 UTC (sessão anterior, entrypoint novo) e às 19:58 UTC (correções desta sessão).

Na segunda, medida com uma requisição por segundo a `/login`:

- `docker compose build` e `up -d --no-deps` apenas de `vetorsmart`, `vetorsmart-worker` e `vetorsmart-scheduler`.
- Os três saudáveis em 8 segundos, cada um com a linha `caches de config, rotas e views gerados (APP_ENV=production)` no log.
- **4 respostas 502 em cerca de 5 segundos** durante a troca; 86 de 90 sondagens com 200.
- Os outros 21 containers mantiveram o mesmo ID. MySQL, Redis e Nginx não foram reiniciados.

Rollback: imagens anteriores marcadas `rollback-pre-10.1` (antes do entrypoint novo) e `rollback-pre-10.1-fixes` (antes das correções desta sessão). Backup do banco de 19:28 UTC em `backups/vetorsmart_pre_release10.1_fd7fa5f_20261116_192840.sql.gz`, com SHA-256 ao lado. Não houve mudança de esquema, então o rollback não exige restaurar o banco.

## 5. Empresas de homologação

| Empresa | Segmento | Endereço | Site |
|---|---|---|---|
| `[HOMOLOG] Homologação VetorSmart` | Assistência técnica | `/assistencia-tecnica/homolog-homologacao-vetorsmart` | Publicado |
| `[HOMOLOG] Empresa B Isolamento` | Limpeza | — | Não publicado |

- Criadas pelo formulário público de cadastro, sem intervenção no banco. Seguem a convenção que o próprio sistema reconhece: nome iniciado por `[HOMOLOG]` e e-mail `homolog.*@example.test`.
- A segunda existe só para o teste de isolamento.
- Dados fictícios: dois serviços com "(teste)" no nome, um profissional, um cliente, uma solicitação, um agendamento, cinco conversas.
- Sem cobrança: o sistema não tem módulo de pagamento.
- Sem comunicação externa: `MAIL_MAILER=log`, provedor de IA `none`, sem integração de WhatsApp no código. Os e-mails `example.test` não são entregáveis. O telefone exibido é `(11) 0000-0000`.
- Acesso: um proprietário por empresa. Credenciais em `backups/vetorsmart_pre_release10.1_fd7fa5f_20261116_192840.conf/homolog/credentials.env`, legível só pelo root.
- Clima Total: nenhum registro alterado. Contagens dela antes e depois: 0 clientes, 0 solicitações, 0 agendamentos, 0 conversas, 0 serviços.

**Relatórios comerciais.** A verificação falhou no primeiro momento: o painel do RootAdmin contava as empresas de teste como clientes (empresas, usuários, sites publicados, agendamentos, uso de IA). Foi corrigido: empresas `[HOMOLOG]` e seus usuários ficam fora de todos os indicadores e continuam na lista de empresas, com o próprio nome. Conferido em produção depois do deploy: 1 empresa, 1 ativa, 2 usuários, 0 sites publicados, 0 agendamentos, 0 mensagens de IA.

**O site de teste está no ar, indexável e no `sitemap.xml`.** Foi deixado assim para permitir a conferência. Para retirar: Meu Site → Publicação → Retirar do ar. Para remover as duas empresas e seus dados: `php artisan homolog:purge` (prévia) e `--execute` em terminal interativo.

## 6. Evidências funcionais e visuais

Testes em Chromium real contra a produção, por HTTPS. **128 de 129 verificações gravadas aprovadas**, mais as 21 do fluxo de agendamento (abaixo). 49 capturas de tela.

Evidências em `backups/vetorsmart_pre_release10.1_fd7fa5f_20261116_192840.conf/homolog/`: `shots/` (capturas), `*.json` (resultados) e `scripts/` (roteiros).

| Item da instrução | Resultado | Captura |
|---|---|---|
| 1. Site em `/segmento/empresa` | 404 antes de publicar, 200 depois | `11`, `16` |
| 2. Página inicial | Título, texto, serviços, diferenciais, profissional, horários, contato, localização | `16`, `42` |
| 3. Lista e detalhes de serviços | Nome, descrição, duração e preço ("R$ 150,00", "Sob consulta") | `17`, `44` |
| 4. Responsividade | Sem rolagem horizontal em 1366, 768 e 390 px, no site e em seis telas do painel | `18`–`21`, `36`–`41` |
| 5. Navegação e botões | Início, Serviços, Atendimento, "Entrar em contato" (`tel:`), "Voltar ao site" | `16`, `17` |
| 6. Formulários e validações | Cadastro, login, serviço sem preço, SEO acima de 160 caracteres, mensagem vazia, telefone ausente, data inválida | `03`, `06`, `13`, `45` |
| 7. Atendimento web | Saudação, serviço reconhecido, coleta de nome e telefone com aviso de privacidade | `20`, `22` |
| 8. Encaminhamento humano | Pedido de atendente, "Aguardando atendente", equipe assume, responde, visitante recebe sem recarregar, encerramento | `24`–`28c` |
| 9. Solicitação de serviço | Solicitação #4 criada pelo atendimento | `29` |
| 10. Agendamento com horário disponível | Oito horários reais de 17/11 oferecidos; escolhido 08:00 | `22` |
| 11. Confirmação | "Agendamento confirmado … Número da solicitação: #4"; consulta de status responde | `23` |
| 12. Painel da empresa | Solicitação, cliente, agendamento e conversa visíveis | `29`–`31` |
| 13. Alteração de status | Agendado → Em andamento | `32` |
| 14. Conclusão | Em andamento → Concluído; histórico completo; ações somem | `33` |
| 15. Isolamento | Seção 7 | `34` |
| 16. SEO | Canonical e `og:url` no endereço padrão, título e descrição aplicados, JSON-LD válido, página de serviços com canonical próprio, sitemap com as duas páginas e sem a Clima Total, `robots.txt` com o sitemap, prévia e atendimento com `noindex` | — |

Ressalvas sobre a contagem:

- A única reprovação gravada (`01-register.json`, "cadastro leva ao dashboard") é do roteiro, que leu a URL cedo demais. O cadastro funcionou: a empresa foi criada e o painel abriu em seguida (registro do Nginx, 19:34:53).
- O fluxo de agendamento teve 21 verificações aprovadas no console, mas o roteiro parou logo depois, num seletor meu, antes de gravar o JSON. O restante foi coberto por um segundo roteiro (`04b`), com outro visitante.

### Defeitos encontrados e corrigidos

Os dois só aparecem em navegador; respostas HTTP não os mostram.

1. **Site público com fonte errada.** Todo site saía em fonte serifada, mesmo configurado como "sem serifa". O template imprimia a lista de fontes com aspas dentro de `<style>`; o escape as transformava em `&quot;`, o navegador descartava a declaração e usava a fonte padrão. Corrigido e coberto por teste. Depois: `16`, `18`, `42`, `43`, `44`. As capturas do estado anterior foram sobrescritas quando o roteiro foi repetido após a correção; o registro do defeito é a declaração que a produção servia, `--site-font: system-ui, -apple-system, &quot;Segoe UI&quot;, Roboto, sans-serif`.
2. **Horários de funcionamento não respondiam.** Em Configurações, marcar um dia não mudava a caixa nem liberava os campos de horário, e alterar o fechamento desfazia a abertura digitada. A tela lia o valor salvo, não o do formulário. Corrigido. Depois: `35`.

### Observações sem correção

- Atendimento no celular: nome longo de empresa quebra em três linhas no cabeçalho, ao lado do indicador (`20`). Legível, mas apertado.
- Detalhe da solicitação: a conversa aparece da mensagem mais recente para a mais antiga (`33`, `41`).
- Página inicial: o texto institucional aparece duas vezes, no topo e em "Sobre a empresa" (`42`).

## 7. Segurança

| Verificação | Resultado |
|---|---|
| Limite de login | 5 tentativas por minuto por e-mail e IP; a 6ª e a 7ª receberam 429 |
| Limite do atendimento | 30 requisições por minuto por IP e empresa; da 31ª em diante, 429 com `Retry-After` |
| Cookies | Sessão `secure`, `httponly`, `samesite=lax`. Cookie do visitante `secure`, `httponly`, restrito ao caminho do atendimento da empresa |
| CSRF | `POST /login` sem token: 419. `POST` autenticado sem token: 419 |
| Rotas sem sessão | Nove rotas do painel: 302 para `/login`. Três da API: 401 |
| Usuário comum no RootAdmin | Quatro telas e a API administrativa: 403 |
| Isolamento | Empresa B não vê nada da A em sete telas. 14 tentativas de ler e alterar registros da A por ID (solicitação, serviço, profissional, cliente, agendamento, conversa, API): todas 404, sem conteúdo da A na resposta. A empresa A ficou intacta |
| Visitantes | Um visitante não vê a conversa de outro |
| Auditoria | 30 ações das empresas de teste registradas, de cadastro a conclusão. Nenhum metadado com senha ou token |
| Arquivos expostos | `/.env` e `/.git/config`: 403. `composer.json`, logs, `vendor`, `docker-compose.yml`, Telescope, Horizon, Ignition: 404 |
| Depuração | Página 404 sem rastro de pilha nem dados internos |
| Dados sensíveis no site público | Nenhum e-mail, ID interno ou UUID nas páginas e no sitemap |
| Host desconhecido | Handshake TLS recusado; 421 com `Host` forjado |

Corrigido nesta release: as respostas não traziam cabeçalhos de segurança e anunciavam `X-Powered-By: PHP/8.4.27`. Agora toda resposta da aplicação traz `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` e, em HTTPS, `Strict-Transport-Security: max-age=31536000`. A versão do PHP não é mais anunciada. Feito na aplicação, sem alterar o Nginx compartilhado; arquivos estáticos servidos direto pelo Nginx não recebem esses cabeçalhos. Não há `Content-Security-Policy`.

MFA não foi implementado nem reintroduzido.

Testes automatizados: **444 aprovados, 9 pulados por desenho, 0 falhas** (453 testes), em MySQL descartável acessível só por `127.0.0.1`, removido ao final. Pint e `tsc --noEmit` sem apontamentos.

## 8. Uso de recursos

Medido às 20:01 UTC, depois dos testes.

| Item | Valor |
|---|---|
| `vetorsmart` (web) | 44 MiB, 0,02% de CPU |
| `vetorsmart-worker` | 36 MiB, 0,00% |
| `vetorsmart-scheduler` | 36 MiB, 0,10% |
| Servidor | 4,8 GB de 16 GB em uso; carga 1,54 em 4 núcleos; disco 74 GB de 197 GB (39%) |
| Tempo de resposta do PHP | 927 requisições desde 19:30: p95 de 53 ms, máximo de 1,0 s |
| MySQL | Log de consultas lentas ligado (limite 1 s): 0 consultas lentas; pico de 14 conexões |
| Erros HTTP | Nenhum 5xx além dos quatro 502 da recriação. Os 4xx são dos próprios testes |
| Logs da aplicação | Nenhum erro, exceção ou aviso nos três containers |
| Worker e scheduler | Ativos e saudáveis. 0 jobs pendentes, 0 falhos |

O scheduler roda, mas **não há nenhuma tarefa agendada** (`schedule:list` vazio). As conversas gravam uma data de retenção (30 dias por padrão) e não encontrei rotina que as apague nessa data.

## 9. Domínios próprios

Inspeção, sem criar certificados nem alterar DNS.

| Aspecto | Situação |
|---|---|
| Verificação de propriedade | Pronta. Registro TXT em `_vetorsmart-verify.<domínio>` com valor `vetorsmart-verify=<token de 32 caracteres>`. O domínio fica pendente até o registro ser visto. Limite de domínios por empresa |
| Resolução da empresa por domínio | Pronta. Rotas por host registradas antes das centrais; a empresa vem do domínio verificado |
| Hosts aceitos | Só o domínio central, subdomínios da base e domínios próprios verificados. A lista fica em cache por 5 minutos |
| Canonical | Com domínio próprio principal, ele vira o canônico; os demais endereços redirecionam com 301 |
| Pendentes e desconhecidos | Não chegam à aplicação: o Nginx recusa o handshake; com `Host` forjado, 421 |
| Certificados TLS | **Falta.** Só existe certificado para `vetorsmart.com.br` e `www`. O Certbot do stack é manual (perfil `certbot`) |
| Nginx | **Falta.** Não há bloco de servidor para domínios de clientes |

O que falta para ligar o recurso, para decisão futura:

1. Um bloco `server` genérico no Nginx para domínios de clientes, apontando para o VetorSmart.
2. Emissão automática de certificado por domínio, disparada só depois da verificação do TXT, para não emitir para domínios de terceiros.
3. Instrução ao cliente para apontar o domínio (A ou CNAME) além do TXT. Hoje a tela pede só o TXT.
4. Renovação e remoção do certificado quando o domínio sai.

Enquanto isso, um cliente consegue verificar o domínio no painel, mas o endereço não abre.

## 10. Manutenção Docker

Inventário, sem remover nada. O Docker ocupa 41,3 GB em imagens e 20,5 GB em cache de build.

| Grupo | Imagens | Observação |
|---|---|---|
| Em uso por containers rodando | 20 | Não remover |
| Rollback | 22 | 21 do VetorSmart (7 gerações × 3 serviços) e 1 `vetorsmart-rollback:f2b898b` |
| De containers parados | 3 | `vetorpet`, parado há dias. Outro produto; não avaliado |
| Sem referência | 2 | `mcr.microsoft.com/playwright:v1.49.1-noble` (3,5 GB, usada nesta homologação) e `certbot/certbot` (0,3 GB, usada na emissão manual) |
| Sem tag | 0 | — |

Gerações de rollback do VetorSmart: `pre-10.1-fixes`, `pre-10.1`, `pre-fd7fa5f`, `pre-ce8404b`, `pre-d2aec27`, `pre-bb29f39`, `pre-f2b898b`.

Espaço recuperável:

- O Docker estima **6,4 GB** em imagens e **5,8 GB** em cache de build exclusivo.
- As imagens do VetorSmart têm 3,6 GB cada, mas dividem quase todas as camadas, e os três serviços de uma geração são praticamente a mesma imagem. Remover gerações antigas libera bem menos do que a soma nominal (79 GB). O ganho real só se conhece removendo.
- Sugestão, para autorização: manter as duas gerações mais recentes (`pre-10.1-fixes` e `pre-10.1`) e a `pre-fd7fa5f`, e remover as quatro anteriores; limpar o cache de build com `docker builder prune`. Não executado.

Backups em `/opt/infra-abrasil/backups`: 491 MB. Não tocados. Volumes: 8, todos em uso.

## 11. Pendências

1. **Enviar os commits ao remoto** (`git push`) nos dois repositórios, quando autorizado. No da infraestrutura, o commit está em `feat/vetorsales-infra`; confirmar se é a branch certa para o healthcheck do VetorSmart.
2. Decidir o destino das empresas de teste: manter para futuras homologações ou remover com `homolog:purge`. Enquanto o site de teste estiver publicado, ele pode ser indexado.
3. Conferir o console RootAdmin em navegador, com a conta de administrador.
4. Repetir a conferência visual em Safari (iPhone real) e Firefox.
5. Recriação com cerca de 5 segundos de 502. Para eliminar, seria preciso subir o container novo antes de parar o antigo.
6. Domínios próprios: Nginx e certificados (seção 9).
7. Retenção das conversas sem rotina que a aplique (seção 8).
8. `Content-Security-Policy` ausente; cabeçalhos de segurança ausentes nos arquivos estáticos.
9. Limpeza de imagens Docker, mediante autorização (seção 10).
10. Três observações de layout sem correção (seção 6).
11. A empresa de teste ficou com o endereço `homolog-homologacao-vetorsmart`, não `homologacao-vetorsmart` como no exemplo da instrução: o endereço vem do nome, que precisa começar por `[HOMOLOG]`.

## Restrições cumpridas

- Sem alteração em outros SaaS, no Nginx, no DNS ou em certificados.
- Sem alteração em dados da Clima Total.
- Sem MFA.
- Sem migrations.
- Sem push. Commits locais feitos após a homologação, a pedido.
- Sem remoção de volumes, backups ou imagens.
- Sem `git reset --hard`, `docker system prune` ou `docker volume prune`.
- Nenhuma credencial impressa.
