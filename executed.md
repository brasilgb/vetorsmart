# Execução: VETORSMART-RELEASE-10.1 — Estabilização e Homologação de Produção

Data: 16/11/2026, 19:26 a 20:06 UTC. Local: VPS de produção `srv1990862`, `gateway/vetorsmart/`.

## Análise do correio

`correio.md` difere do anterior. A última instrução executada era a `RELEASE-10`; a atual é a `RELEASE-10.1`. Ela foi executada em duas sessões: a primeira fez a correção dos caches e o cadastro da empresa de teste; a segunda concluiu as etapas 3 a 8. O conteúdo anterior deste arquivo foi apagado.

## Parecer

**APROVADO COM RESTRIÇÕES.** Relatório técnico completo: `docs/VETORSMART-RELEASE-10.1.md`.

Sites públicos e atendimento funcionam em produção, com isolamento entre empresas, caches regenerados a cada início e experiência desktop e mobile conferida em navegador real.

Restrições:

1. **Os commits desta release existem só nesta VPS**, sem push.
2. **Navegador único:** Chromium headless em 1366, 768 e 390 px. Safari e Firefox reais não foram usados.
3. **Console RootAdmin não aberto em navegador:** sem credencial de administrador disponível. Indicadores conferidos por teste e por leitura em produção.
4. **Duas empresas de teste permanecem em produção**, uma com site publicado e indexável.
5. **Domínios próprios** seguem sem Nginx e sem certificado.

## 1. HEAD

- Inicial: `fd7fa5f`. Depois dos commits: 8 à frente de `origin/main` (os sete abaixo e um de documentação, com esta atualização). Sem push.
- Commits da aplicação: `1fb41cb` (caches e healthcheck), `4976b2d` (fonte do site), `b07f0df` (horários), `1aa9edb` (indicadores do RootAdmin), `c7b8477` (cabeçalhos de segurança), `89ec6da` (relatórios), `6bb89aa` (correio e executed).
- Infraestrutura: `33baf62` na branch `feat/vetorsales-infra`, com o healthcheck do `docker-compose.yml` e o submódulo `gateway/vetorsmart`; um segundo commit avança o submódulo para o commit de documentação. Sem push.
- Os commits foram feitos depois do deploy e da homologação. As imagens em produção têm o mesmo código de `c7b8477`.

## 2. Alterações aplicadas

```
docker tag infra-abrasil-<serviço>:latest infra-abrasil-<serviço>:rollback-pre-10.1         (sessão anterior)
docker tag infra-abrasil-<serviço>:latest infra-abrasil-<serviço>:rollback-pre-10.1-fixes
docker compose build vetorsmart vetorsmart-worker vetorsmart-scheduler
docker compose up -d --no-deps vetorsmart vetorsmart-worker vetorsmart-scheduler
docker compose run --rm --no-deps ...                    (testes de falha do entrypoint, removidos)
docker run mcr.microsoft.com/playwright:v1.49.1-noble    (homologação em navegador)
docker run mysql:8.4 em 127.0.0.1:33111                  (banco da suíte, removido)
```

- Recriados às 19:32 e às 19:58 UTC: `vetorsmart`, `vetorsmart-worker`, `vetorsmart-scheduler`. Os outros 21 containers mantiveram o mesmo ID.
- Na segunda recriação: três containers saudáveis em 8 segundos; 4 respostas 502 em cerca de 5 segundos.
- MySQL, Redis e Nginx não foram reiniciados. `.env`, Nginx, DNS e certificados não foram alterados.

## 3. Correção dos caches

- O entrypoint gera `config:cache`, `route:cache` e `view:cache` a cada início, em web, worker e scheduler, com as variáveis do próprio container.
- Cada container escreve no seu sistema de arquivos; só o web publica o build do front no volume compartilhado.
- Variável obrigatória ausente ou cache não gerado: o container não sobe. O log traz nomes de variáveis, nunca valores.
- Healthcheck novo (`docker-healthcheck.php`): caches, `APP_KEY`, banco e PHP-FPM.
- Quatro cenários de falha testados em containers descartáveis; todos barraram com saída 1.

## 4. Empresas de homologação

- `[HOMOLOG] Homologação VetorSmart`, em `/assistencia-tecnica/homolog-homologacao-vetorsmart`, site publicado.
- `[HOMOLOG] Empresa B Isolamento`, só para o teste de isolamento, sem site.
- Criadas pelo formulário público. Dados fictícios, sem cobrança, sem envio externo (`MAIL_MAILER=log`, IA `none`).
- Credenciais em `backups/vetorsmart_pre_release10.1_fd7fa5f_20261116_192840.conf/homolog/credentials.env`.
- Clima Total: nenhum registro alterado. O site dela foi publicado e retirado às 19:25 UTC pelo proprietário, de um navegador externo, não por esta execução.
- Remoção, quando desejada: `php artisan homolog:purge` e depois `--execute`.

## 5. Homologação em navegador

- **128 de 129 verificações gravadas aprovadas**, mais 21 do fluxo de agendamento conferidas no console. 49 capturas de tela.
- A única reprovação é do roteiro de cadastro, que leu a URL cedo demais; o cadastro funcionou.
- Os 16 itens da instrução foram cobertos: site, página inicial, serviços, responsividade, navegação, formulários, atendimento, encaminhamento humano, solicitação, agendamento, confirmação, painel, mudança de status, conclusão, isolamento e SEO.
- Evidências em `backups/vetorsmart_pre_release10.1_fd7fa5f_20261116_192840.conf/homolog/` (`shots/`, `*.json`, `scripts/`).

## 6. Defeitos corrigidos

1. Site público saía em fonte serifada: aspas escapadas dentro de `<style>` invalidavam a declaração.
2. Horários de funcionamento: marcar um dia não mudava a caixa nem liberava os campos.
3. Painel RootAdmin contava as empresas de teste como clientes. Agora ficam fora dos indicadores.
4. Respostas sem cabeçalhos de segurança e com a versão do PHP exposta. Acrescentados `nosniff`, `X-Frame-Options`, `Referrer-Policy` e HSTS, na aplicação.

## 7. Segurança e desempenho

- Limite de login: 429 na 6ª tentativa. Limite do atendimento: 429 após 30 por minuto.
- CSRF: 419. Cookies `secure` e `httponly`. Rotas do painel sem sessão: 302. Usuário comum no RootAdmin: 403.
- Isolamento: 14 tentativas cruzadas de leitura e escrita por ID, todas 404, sem vazamento.
- Suíte automatizada: 444 aprovados, 9 pulados por desenho, 0 falhas.
- Recursos: web com 44 MiB; p95 de 53 ms; 0 consultas lentas; nenhum 5xx além dos 502 da recriação; 0 jobs falhos.
- MFA não implementado.

## 8. Domínios próprios e Docker

- Domínios: verificação por TXT, resolução por host e canonical prontos na aplicação. Faltam bloco de servidor no Nginx e emissão de certificado por domínio.
- Docker: 41,3 GB em imagens, 20,5 GB em cache de build. 22 imagens de rollback, 21 do VetorSmart. Estimativa do Docker: 6,4 GB recuperáveis em imagens e 5,8 GB em cache. Nada foi removido.

## 9. Pendências

1. Push dos commits nos dois repositórios, quando autorizado; confirmar a branch da infraestrutura.
2. Destino das empresas de teste.
3. RootAdmin em navegador; Safari e Firefox.
4. 5 segundos de 502 na recriação.
5. Nginx e certificados para domínios próprios.
6. Retenção de conversas sem rotina agendada (o scheduler não tem tarefas).
7. `Content-Security-Policy` ausente.
8. Limpeza de imagens, mediante autorização.
9. Três observações de layout sem correção.

Detalhes no relatório.

## Restrições cumpridas

- Sem alteração em outros SaaS, Nginx, DNS ou certificados. Sem alteração em dados da Clima Total.
- Sem MFA, sem migrations, sem push. Commits locais feitos após a homologação, a pedido.
- Sem remoção de volumes, backups ou imagens.
- Nenhuma credencial impressa.
