# VETORSMART-WILDCARD-01 — Subdomínios públicos por tenant

Data: 17/11/2026. Ambiente: desenvolvimento local (WSL), `gateway/vetorsmart/`. Base: `466a363`.

## Situação

**Fase A concluída no código, sem implantação.** O suporte a subdomínios foi implementado e testado (475 aprovados, 9 pulados, 0 falhas), atrás de uma flag desligada por padrão. Com ela desligada, a produção se comporta exatamente como hoje. Nada foi alterado na VPS, no Nginx, nos certificados ou no banco de produção.

**Fase B não iniciada:** depende da propagação do DNS e do certificado curinga (seção 6).

## 1. Diagnóstico

| Item | Encontrado |
| --- | --- |
| Endereço atual | `vetorsmart.com.br/{segmento}/{empresa}` (WEBSITES-09). |
| Hosts das empresas | `tenant_domains` (hostname único global, tipo `subdomain` ou `custom`). Os subdomínios antigos só redirecionavam para o endereço padrão, e novos não eram mais criados. |
| Hosts confiáveis | `TrustedHosts` já aceita `*.{PLATFORM_TENANT_BASE_DOMAIN}`, o host central e os domínios próprios verificados. Qualquer outro `Host` é recusado antes do roteamento. |
| Isolamento do painel | `SiteHostGuard` responde 404 em qualquer caminho fora do site (`/`, `/servicos`, `/atendimento*`, `/sitemap.xml`, `/robots.txt`) em host que não seja o central. |
| Slugs | `tenants.slug` é único global; colisão no cadastro ganha sufixo aleatório (`clima-total-x7k2`). |
| Produção | `PLATFORM_CENTRAL_HOST=vetorsmart.com.br` e `PLATFORM_TENANT_BASE_DOMAIN=vetorsmart.com.br` (RELEASE-10.x). |
| Nginx de produção (`infraabrasil` `origin/main`) | Só há vhost e certificado para `vetorsmart.com.br` e `www.vetorsmart.com.br` (HTTP-01 via webroot). PHP via FastCGI direto, sem proxy reverso, então o `Host` chega intacto. |
| Sessão | `SESSION_DOMAIN=null` no `.env` local: cookie host-only. Mantido. O valor de produção não foi conferido nesta fase. |

Nada foi duplicado: os subdomínios reutilizam `tenant_domains`, `TrustedHosts`, `SiteHostGuard`, `WebsiteResolver` e as views do site.

## 2. Comportamento implementado

### Flag (config `platform`)

| Variável | Padrão | Efeito |
| --- | --- | --- |
| `PLATFORM_SUBDOMAIN_SITES` | `false` | O subdomínio atual serve o site (início, serviços, atendimento, sitemap e robots) e vira a URL canônica quando não há domínio próprio. Desligada, o subdomínio redireciona (301) para o endereço padrão, como hoje. |
| `PLATFORM_MAX_SUBDOMAIN_CHANGES` | `3` | Quantas vezes a empresa pode alterar o subdomínio. |

### Resolução do tenant

- Pelo hostname exato em `tenant_domains` (nunca por `tenant_id` da URL, do formulário ou de cabeçalho). Sub-subdomínios, nomes inexistentes, sites não publicados e empresas suspensas respondem 404 genérico.
- Ordem da canônica: domínio próprio verificado → subdomínio (com a flag) → endereço padrão. Sitemap e robots seguem a canônica, e o sitemap central deixa de listar empresas que têm host canônico próprio.
- Domínio próprio continua prioritário; o subdomínio segue servindo como alternativa, com a canônica apontando para o domínio próprio.
- Empresas `[HOMOLOG]`: `noindex` no HTML e em `X-Robots-Tag`, fora de todos os sitemaps.

### Cadastro e alteração

- Todo cadastro recebe um subdomínio na mesma transação, derivado do nome: `Clima Total` → `climatotal`; colisão → `climatotal2` … `climatotal20` → sufixo aleatório. A unicidade é garantida pelo índice único de `tenant_domains.hostname`; uma colisão simultânea cai no índice e tenta o próximo nome (savepoint).
- O administrador altera em **Meu Site → Domínios** (`PATCH /meu-site/subdominio`, `manageSettings`, limitado a 10/min). Regras: 3 a 63 caracteres, `[a-z0-9-]`, sem hífen no início/fim nem `--` (bloqueia `xn--`), lista reservada ampliada (`www`, `api`, `admin`, `rootadmin`, `login`, `suporte`, `mail`, `ns1`, `autodiscover`, `homolog`, `n8n`, `phpmyadmin` etc.).
- O nome anterior ganha `retired_at`, continua da empresa e redireciona (301, com caminho e query) para o atual. A empresa pode retomar um nome que já foi dela; um nome que já foi de outra empresa nunca fica disponível.
- O subdomínio não pode ser removido nem marcado como principal (essas ações valem só para domínio próprio), e não conta no limite de domínios próprios.
- Empresas existentes: `php artisan sites:assign-subdomains` mostra a prévia; com `--execute`, cria só os subdomínios que faltam.

## 3. Arquivos

| Arquivo | Alteração |
| --- | --- |
| `database/migrations/2026_11_17_134456_add_retired_at_to_tenant_domains_table.php` | **Nova, aditiva:** `tenant_domains.retired_at` (timestamp, nulo). |
| `config/platform.php` | Flags, limite de alterações e lista reservada ampliada. |
| `app/Services/Websites/TenantSubdomainService.php` | **Novo:** subdomínio atual, atribuição, alteração, validação e histórico. |
| `app/Services/Websites/PublicSiteAddress.php` | `subdomainHost`, `canonicalHost` e a ordem da canônica. |
| `app/Http/Controllers/Site/SiteController.php` | Subdomínio serve ou redireciona; 301 opcional do endereço padrão; sitemap e robots pela canônica. |
| `app/Http/Controllers/Site/AssistantController.php` | Atendimento no subdomínio atual; redirecionamentos só na página. |
| `app/Services/Websites/WebsiteDomainService.php` | Limite conta só domínio próprio; subdomínio não é removido nem vira principal. |
| `app/Services/Websites/WebsitePresenter.php`, `resources/js/pages/tenant/website/index.tsx` | Cartão "Endereço VetorSmart" com o endereço atual, os anteriores e o formulário de alteração. |
| `app/Http/Controllers/Tenant/WebsiteSubdomainController.php`, `app/Http/Requests/Tenant/UpdateWebsiteSubdomainRequest.php`, `routes/tenant.php` | **Novos:** rota de alteração. |
| `app/Services/TenantRegistrationService.php` | Atribui o subdomínio no cadastro. |
| `app/Models/TenantDomain.php` | `retired_at` e `isRetired()`. |
| `app/Console/Commands/AssignTenantSubdomains.php` | **Novo:** `sites:assign-subdomains`. |
| `tests/Feature/Websites/SubdomainSiteTest.php` | **Novo:** 21 testes. |

## 4. Testes executados

Banco MySQL de testes isolado (`vetorsmart_test`, protegido pelo `DatabaseGuard`).

| Gate | Resultado |
| --- | --- |
| `php artisan test` (suíte completa) | **475 aprovados, 9 pulados, 0 falhas** (3083 asserções) |
| `tests/Feature/Websites` + `tests/Feature/Tenancy` | 140/140 |
| PHPStan | 0 erros |
| Pint | ok |
| `tsc --noEmit` | 0 erros |
| `npm run build` | ok |

Cobertura do correio (seção 9):

| # | Item | Situação |
| --- | --- | --- |
| 1 | Resolução por subdomínio | Testado |
| 2 | Subdomínios inexistentes | Testado (404 sem dados da empresa) |
| 3 | Slugs duplicados ou reservados | Testado (cadastro duplicado, 9 nomes inválidos/reservados/de outra empresa, limite de alterações) |
| 4 | Isolamento entre empresas | Testado |
| 5 | Páginas públicas | Testado (início, serviços, sitemap, robots, canônica) |
| 6 | Atendimento | Testado (página e início de conversa no subdomínio; JSON no endereço padrão durante o 301) |
| 7 | URLs antigas | Fora do escopo: não há clientes cadastrados, então não há endereço antigo a redirecionar (decisão do proprietário). O endereço padrão continua respondendo, com a canônica no subdomínio. |
| 8 | Autenticação administrativa | Testado (`/login`, `/dashboard`, `/meu-site`, `/rootadmin` → 404 no subdomínio; operador → 403 na alteração) |
| 9 | Aparência clara e escura | **Não verificado em navegador.** O site reutiliza a mesma view já homologada na WEBSITES-09.1; o cartão novo do painel usa os tokens existentes (`vs-*`). |
| 10 | Layout mobile e desktop | **Não verificado em navegador** (mesma observação). |
| 11 | Cookies e cabeçalhos | Testado: cookies sem atributo `Domain` (host-only) no subdomínio; CSP/segurança inalterados (middleware global). |
| 12 | HTTPS e redirecionamentos com DNS | **Fase B.** |

Agendamentos: o site público não tem formulário de agendamento próprio; o fluxo passa pelo atendimento, coberto acima.

## 5. Segurança

- Tenant só pelo hostname registrado; nenhum identificador vindo do visitante.
- Redirecionamentos montados apenas com hostnames do banco (sem eco do `Host` ou de parâmetros), portanto sem redirecionamento aberto.
- `TrustedHosts` mantém a lista de hosts aceitos; `SiteHostGuard` mantém o painel fora dos subdomínios.
- Sessões administrativas continuam host-only no domínio central (`SESSION_DOMAIN` vazio). **Não definir `SESSION_DOMAIN=.vetorsmart.com.br`.**
- CSRF: as rotas do atendimento já funcionavam em host de empresa; nada mudou. CORS não se aplica (nenhuma chamada entre hosts). CSP `'self'` funciona porque a página do site não carrega scripts nem folhas de estilo do host central (o único `<script>` é JSON-LD, que não executa). O `report-uri` continua absoluto no host central.
- Rate limiting: `assistant-public` inalterado; alteração de subdomínio com `throttle:10,1`.
- `TrustedProxies`: a produção não usa proxy reverso para o VetorSmart (FastCGI direto), então nada muda.

## 6. Nginx e certificados (preparado, não aplicado)

Estado atual: o Nginx de produção não responde por `*.vetorsmart.com.br`; um subdomínio cai no `default_server` (HTTP 444 / TLS recusado). O certificado atual cobre só `vetorsmart.com.br` e `www`.

### Certificado curinga

HTTP-01 não emite curinga; é preciso DNS-01. Use um **nome de certificado separado**, para não tocar no atual:

```sh
# Manual (um TXT em _acme-challenge.vetorsmart.com.br). Renovação NÃO automática: repetir a cada ~60 dias.
docker compose run --rm certbot certonly --manual --preferred-challenges dns \
  --cert-name vetorsmart-wildcard -d vetorsmart.com.br -d '*.vetorsmart.com.br'
```

Renovação automática exige um provedor DNS com API e plugin do Certbot (ex.: Cloudflare com `certbot-dns-cloudflare`), ou delegação de `_acme-challenge` por CNAME para um servidor `acme-dns`. O DNS do Registro.br não tem API para o Certbot. **Decisão pendente:** escolher o provedor DNS da delegação em andamento.

### Vhost

Acrescentar ao `nginx/conf.d/apps.conf` **somente depois** de o certificado existir (um `ssl_certificate` inexistente impede o `nginx -t`). Nomes exatos (`vetorsmart.com.br`, `www.vetorsmart.com.br`) têm prioridade sobre o curinga, então os blocos atuais e o redirecionamento do `www` continuam valendo. Nenhum outro SaaS usa `*.vetorsmart.com.br`.

```nginx
# HTTP: desafio ACME e redirecionamento para HTTPS.
server {
    listen 80;
    server_name *.vetorsmart.com.br;
    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 301 https://$host$request_uri; }
}

# HTTPS: sites das empresas. A aplicação decide o que cada host serve (SiteHostGuard/TrustedHosts).
server {
    listen 443 ssl;
    http2 on;
    server_name *.vetorsmart.com.br;
    root /var/www/vetorsmart/public;
    ssl_certificate /etc/letsencrypt/live/vetorsmart-wildcard/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/vetorsmart-wildcard/privkey.pem;
    include /etc/nginx/conf.d/laravel.inc;
    location /build/ { alias /var/www/vetorsmart/build/; try_files $uri =404; }
    location /storage/ { alias /var/www/vetorsmart/storage/; try_files $uri =404; }
    location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name; fastcgi_connect_timeout 2s; set $php_upstream vetorsmart:9000; fastcgi_pass $php_upstream; }
}
```

O checkout local da infraestrutura está em HEAD destacado e com conflito de submódulo (`gateway/vetorleads`); por isso o bloco ficou neste relatório e não num commit da infraestrutura.

## 7. Pendências de DNS

1. Concluir a delegação no Registro.br e confirmar os nameservers autoritativos (`dig NS vetorsmart.com.br`).
2. Registros: `A vetorsmart.com.br` e `A *.vetorsmart.com.br` → IP da VPS (`dig +short qualquer.vetorsmart.com.br`).
3. TXT `_acme-challenge.vetorsmart.com.br` na emissão do curinga.

## 8. Riscos

| Risco | Mitigação |
| --- | --- |
| Ligar `PLATFORM_SUBDOMAIN_SITES` antes do DNS/HTTPS faria a canônica e os links apontarem para hosts inacessíveis. | Flag desligada por padrão; ligar só na Fase B, depois de homologar um tenant de teste. |
| Certificado manual expira em 90 dias. | Definir provedor DNS com API antes de depender do curinga. |
| Nomes gerados pelo nome da empresa podem ficar longos. | O administrador pode trocar (até 3 vezes; o anterior redireciona). |
| Mudança de `PLATFORM_TENANT_BASE_DOMAIN` invalidaria os hostnames gravados. | Não está previsto; exigiria migração de dados específica. |
| Configuração em cache (`config:cache`) ignora a troca de `.env`. | Recriar o container ou refazer `config:cache` ao ligar as flags. |

## 9. Rollback

- Flag: voltar `PLATFORM_SUBDOMAIN_SITES` para `false` e refazer o cache de config. O comportamento volta ao atual sem deploy de código.
- Nginx: remover os dois blocos `*.vetorsmart.com.br`, `nginx -t`, `nginx -s reload`. O certificado atual nunca é substituído.
- Código: reverter o commit. A migration é aditiva; `migrate:rollback --step=1` remove só `retired_at` (desnecessário para voltar ao comportamento anterior).
- Subdomínios criados pelo cadastro ou pelo comando ficam inertes com a flag desligada (só redirecionam para o endereço padrão).

## 10. Próximos passos (Fase B, com autorização)

1. Revisar e commitar este código; deploy com `php artisan migrate --force` (migration aditiva).
2. `php artisan sites:assign-subdomains` (prévia), revisar e rodar com `--execute`.
3. DNS: delegação, `A` do apex e do curinga, verificação dos nameservers.
4. Emitir o curinga com nome separado e validar (`openssl s_client -servername teste.vetorsmart.com.br`).
5. Adicionar o vhost, `nginx -t`, reload; confirmar VetorOS, VetorSales, VetorLeads e VetorSmart.
6. Ligar `PLATFORM_SUBDOMAIN_SITES=true`; homologar um tenant `[HOMOLOG]` em desktop e mobile, claro e escuro.
