# VETORSMART-BACKEND-05 — Sites Multi-Tenant, Domínios, Templates e Publicação

**Status: CONCLUÍDO COM RESSALVAS.** Fluxo completo implementado e validado por testes: configurar o site, salvar rascunho, pré-visualizar e publicar, servido por hostname de tenant. Validação: 231 testes (228 aprovados, 0 falhas, 3 pulados de 2FA), Pint, PHPStan (0 erros), TypeScript (0 erros) e build.

**Não validado:** navegador real (Chrome não instalado), MySQL 8 (servidor local é MariaDB 11.8.6) e a propagação real de DNS. **Pendência que depende de você:** o arquivo estático `public/robots.txt` continua presente (ver seção 9).

Data: 10/11/2026. Referências: `docs/VETORSMART-PRODUCT-VISION.md`, `docs/VETORSMART-PLATFORM-START-01.md`.

---

## 1. Diagnóstico inicial

- Módulo operacional do tenant (BACKEND-02 e 04) estável: clientes, serviços, profissionais, disponibilidade, equipe e configurações. Reutilizado sem recriação.
- Landing, login universal, cadastro, layout administrativo, eventos de crescimento e console RootAdmin preservados.
- `TrustHosts` e `trustProxies` não configurados. Nenhuma resolução por hostname existia.
- Havia `public/robots.txt` estático (starter kit, versionado).

## 2. Separação dos domínios

| Função | Hostname | Conteúdo |
|---|---|---|
| Comercial | `PLATFORM_CENTRAL_HOST` | Landing institucional |
| Administrativo | `PLATFORM_CENTRAL_HOST` | Login, cadastro, RootAdmin, painel do tenant |
| Público do tenant | `<empresa>.<PLATFORM_TENANT_BASE_DOMAIN>` ou domínio próprio verificado | Somente o site publicado |

- Configuração em `config/platform.php`, lida de variáveis de ambiente. O domínio definitivo não é presumido.
- Desenvolvimento: `PLATFORM_TENANT_BASE_DOMAIN=vetorsmart.test` (padrão). Hostnames de teste são aceitos pelo `TrustHosts` quando enviados com o cabeçalho correto.
- **Hosts desconhecidos**: rejeitados pelo `TrustHosts` (regras aceitas: host central, subdomínios do domínio base e domínios personalizados verificados). Hosts que passam, mas não resolvem, recebem 404 genérico, sem dados de empresa.
- **Não usa domínio informado pelo cliente para escolher tenant**: a resolução vem do registro verificado no banco.
- **Cookies**: sessão e cookie administrativo ficam no host central; cada hostname de site tem seu próprio host e não recebe rotas administrativas (`SiteHostGuard` responde 404 para qualquer caminho fora de `/`, `/sitemap.xml` e `/robots.txt`).
- **Proxies**: `PLATFORM_TRUSTED_PROXIES` (lista de IPs). Vazio = cabeçalhos encaminhados são ignorados. Aplicado no `AppServiceProvider`.

## 3. Banco de dados

**Migrations criadas** (MySQL, InnoDB, aplicadas no banco de desenvolvimento):

| Tabela | Conteúdo | Integridade |
|---|---|---|
| `tenant_domains` | hostname (único global), tipo, situação de verificação, token, principal, data de verificação | FK tenant; índice `(tenant_id, is_primary)` |
| `tenant_websites` | um site por empresa, template, situação, rascunho (JSON), ponteiro para a versão publicada, datas | `unique (tenant_id)`; `unique (tenant_id, id)`; FK composta para `website_versions` |
| `website_versions` | snapshots imutáveis de cada publicação, com número de versão | FK composta `(tenant_id, tenant_website_id)`; `unique` por site e versão; `CHECK (version > 0)` |

**Decisão de desenho**: a especificação sugeria `website_pages`, `website_sections`, `website_media` e `website_seo` como tabelas separadas. **Não foram criadas**: o site tem uma única página e seções fixas do template. O conteúdo do rascunho e o SEO ficam no JSON `draft_content`; cada publicação grava o snapshot completo em `website_versions.content`. Isso evita tabelas sem uso. Se houver múltiplas páginas no futuro, a migração será necessária e está registrada como pendência.

Reversibilidade: as migrations têm `down()`. A FK composta de `tenant_websites` para `website_versions` é removida antes de apagar a tabela de versões.

## 4. Resolução por hostname

Serviço `WebsiteResolver` (fluxo da especificação):

1. Recebe o hostname da requisição e normaliza (`HostnameNormalizer`: minúsculas, sem porta, sem ponto final; IP literal rejeitado).
2. Procura o domínio **verificado** na tabela.
3. Confere se a empresa está **ativa**.
4. Confere se o site está **publicado** e tem snapshot.
5. Carrega **somente** o snapshot publicado daquela empresa, nunca o rascunho.
6. Qualquer falha retorna `null`, e o controller responde 404 genérico.

Outras regras aplicadas:
- **Domínio secundário verificado** redireciona (301) para o domínio principal.
- **Nomes reservados** da plataforma (`app`, `admin`, `api`, `painel`, `rootadmin` etc. sob o domínio base) não podem ser reivindicados; o host central e o próprio domínio base também não.
- **Domínio personalizado** com nome reservado fora do domínio base (ex.: `www.empresa.com.br`) é permitido.
- **Duplicidade** de hostname é recusada (índice único).
- **Limite de domínios por empresa**: configurável (`PLATFORM_MAX_DOMAINS_PER_TENANT`, padrão 3).

**Cache**: o conteúdo publicado é guardado por **tenant e versão** (`site:tenant:{id}:version:{n}`, 1 hora). Como a versão é imutável, uma nova publicação substitui o cache naturalmente, sem invalidação manual. A situação de domínio, empresa e site é sempre lida do banco.

## 5. Meu Site (painel)

Menu **Meu Site** entre Usuários e Configurações. Tela única com abas:

- **Visão geral**: nome, modelo (segmento), situação, endereço público, última publicação e último rascunho.
- **Aparência**: cor da marca (paleta aprovada), tipografia. **Logotipo próprio: não implementado.**
- **Conteúdo**: título, texto institucional, diferenciais (até 6), mostrar horários. Telefone, WhatsApp, e-mail e endereço vêm de Configurações (sem duplicação).
- **Serviços**: escolha de quais serviços ativos aparecem; mostrar profissionais e escolher quais. Sem recriar a gestão de serviços.
- **Domínios**: endereço da plataforma (criado e verificado automaticamente), domínio próprio (pendente até o registro DNS), instruções DNS com o valor do TXT, verificar, tornar principal e remover (com confirmação).
- **SEO**: título, descrição, indexação. Não promete posição em buscadores.
- **Publicação**: prévia, publicar e retirar do ar (com confirmação). Retirar não apaga dados nem histórico.

Permissões: leitura para todos os membros; escrita, publicação e domínios para quem gerencia configurações (proprietário e administrador). Operador vê as abas somente leitura. Empresa de outro tenant: 404.

## 6. Template-base

- Template único `base` (Blade, `resources/views/website/site.blade.php`), responsivo, com CSS embutido e variáveis (`--site-accent`, `--site-font`).
- Seções na ordem da especificação: cabeçalho, hero, sobre, serviços, diferenciais, profissionais (quando publicados), horários, contato, localização, rodapé. **Seção sem conteúdo real não aparece.** Galeria: não implementada (depende de upload).
- Variação por segmento: o rótulo do modelo é o segmento da empresa. As seis variações de design não foram criadas (conforme a especificação).
- Identidade própria do tenant: cor e tipografia do site independentes do painel.
- **Nenhum texto fixo fictício**: nomes, serviços, preços, horários e contatos vêm do banco da empresa.

## 7. Rascunho, prévia e publicação

- Alterações salvas ficam **somente no rascunho** (`draft_content`).
- **Publicação atômica**: monta o snapshot a partir do rascunho e dos dados reais, cria a versão e troca o ponteiro, tudo em uma transação.
- **Histórico**: cada publicação gera uma versão numerada e registrada com autor e data.
- **Prévia**: `/meu-site/previa`, somente para membros autenticados, marcada como não publicada e com `noindex`.
- **Não coberto**: prévia por link temporário assinado para compartilhar com quem não tem conta. Pendência.

Testes explícitos de não vazamento: rascunho não aparece ao visitante antes e depois de edições, e a versão publicada permanece até nova publicação.

## 8. SEO técnico

- `<title>`, meta description, `rel="canonical"` (sempre no domínio principal), Open Graph (título, descrição, URL).
- JSON-LD `ProfessionalService` com apenas os campos preenchidos (nome, telefone, e-mail, endereço).
- Sitemap XML por hostname, listando somente a página inicial e apenas se indexável.
- Robots: texto por hostname. Central mantém a regra original.
- Página não indexável: `noindex` e robots `Disallow: /`.
- Prévias: sempre `noindex`.
- Duplicidade entre subdomínio e domínio próprio: tratada por canonical e redirecionamento 301 para o principal.
- **Não há promessa de classificação.** Nenhum dado de tráfego é inventado.

## 9. Pendência: `public/robots.txt` estático

O arquivo `public/robots.txt` (versionado, conteúdo `User-agent: *` / `Disallow:`) é servido **pelo servidor web antes do Laravel**, para qualquer host. Enquanto existir, o robots dinâmico por hostname **não é alcançado** em produção. Os testes passam porque utilizam o roteador do Laravel, que não ignora esse arquivo.

Tentei remover o arquivo, mas a operação foi bloqueada por política de segurança do ambiente (apaga arquivo versionado). **Não contornei o bloqueio.** Decisão necessária: remover `public/robots.txt` (a rota `/robots.txt` mantém o mesmo texto no host central) ou manter o arquivo e configurar o servidor web para não servi-lo nos hosts de tenant.

## 10. Crescimento e eventos

Três eventos novos, todos com significado operacional:

| Evento | Quando |
|---|---|
| `website_configured` | Primeira gravação do rascunho |
| `website_published` | Cada publicação |
| `domain_verified` | Verificação de domínio personalizado concluída |

Não registram visualizações de página, dados de visitantes nem conteúdo. O evento de "serviço publicado" foi **omitido** porque duplicaria a publicação. Coleta de visitas e conversões com consentimento não foi iniciada (conforme a especificação).

## 11. Preparação para IA, aplicativo e ofertas

- CTA "Entrar em contato" aparece **somente** se o tenant cadastrou telefone ou WhatsApp, e leva a esses contatos reais.
- Nenhuma integração de IA, agendamento, proposta ou avaliação foi simulada no site.
- Recursos futuros não aparecem como disponíveis.

## 12. Segurança

- **XSS**: todo texto do tenant é escapado pelo Blade. Teste com `<script>` confirma que é exibido como texto.
- **JSON-LD**: serializado com `JSON_HEX_*`.
- **CSRF**: rotas administrativas de escrita herdam o middleware web (token obrigatório). Os testes do Laravel desativam CSRF por padrão; a proteção não foi validada por teste dedicado.
- **Isolamento**: testes de dois tenants confirmam que cada hostname mostra somente o próprio conteúdo; domínio alheio retorna 404 em verificar, principal e remover.
- **Uploads**: não há upload nesta etapa (logotipo e galeria pendentes).
- **HTML arbitrário**: não é aceito do tenant.
- **Logs**: nenhuma credencial é registrada; auditoria registra ações sem conteúdo sensível.
- **Limites**: máximo de domínios por empresa. Não há limite de taxa para resolução de hosts (pendência).

## 13. Testes

| Verificação | Resultado |
|---|---|
| `php artisan test --compact` | **231 testes: 228 aprovados, 0 falhas, 3 pulados** (2FA desativado) |
| Testes novos do módulo | 21 (`Websites/WebsiteTest`) |
| Pint | aprovado |
| PHPStan | **0 erros** |
| TypeScript | **0 erros** |
| Build | aprovado |

Cobertura: rascunho sem vazamento, publicação e versões, retirada sem perda de dados, empresa suspensa, host desconhecido, domínio pendente, verificação, redirecionamento para principal, nomes reservados e duplicados, limite de domínios, isolamento entre empresas, seleção de serviços ativos, prévia protegida, XSS, SEO, robots e sitemap, permissão de operador, eventos de crescimento.

**Problemas corrigidos durante a execução**: (1) mescla de rascunho por índice (listas antigas sobreviviam); (2) reserva de subdomínio aplicada a domínios personalizados (`www.empresa.com.br` era recusado); (3) um script de edição quebrou `bootstrap/app.php`, que foi reescrito inteiro; (4) testes de host afetados por raiz de URL herdada do cliente de teste; (5) 12 apontamentos do PHPStan, corrigidos na origem.

## 14. Homologação

- **Navegador real**: não executada. Chrome não está instalado. Nenhuma captura de tela foi gerada.
- **HTTP**: validado por testes pelo pipeline do Laravel (roteamento, middleware, `TrustHosts`, resposta), sem rede real.
- **DNS**: a verificação de domínio personalizado usa `dns_get_record` em produção; **não foi testada contra DNS real**. Nos testes, o verificador é substituído.
- **MySQL 8**: não validado; testes em MariaDB 11.8.6.

## 15. Infraestrutura (documentada, não alterada)

Configuração futura para produção (nada foi alterado em DNS, certificados ou infraestrutura):

- **DNS**: registro wildcard `*.<domínio base>` apontando para o servidor; um registro por domínio personalizado após o cliente publicar o TXT.
- **Proxy reverso e HTTPS**: Caddy (ou equivalente) com emissão automática de certificados, somente para hostnames validados. Emissão restrita a domínios cadastrados e verificados, para evitar abuso.
- **Hosts e proxies**: `PLATFORM_TRUSTED_PROXIES` com os IPs do proxy; `PLATFORM_PUBLIC_SCHEME=https`; `SESSION_SECURE_COOKIE=true` (já padrão em produção).
- **Monitoramento**: falhas de verificação e de certificado; renovação automática.
- **Backup**: banco (incluindo versões e domínios) e arquivos de mídia quando existirem.
- **Limites contra abuso**: limite de domínios (implementado); limite de taxa por IP para resolução e verificação (pendente).

## 16. Pendências

1. **Decidir o destino de `public/robots.txt`** (seção 9). Bloqueante para robots por hostname em produção.
2. Navegador real: homologar site, painel, prévia, desktop e mobile.
3. Verificação de DNS real em ambiente de teste.
4. Logotipo e galeria do site (uploads seguros, armazenamento, remoção).
5. Prévia com link temporário assinado para quem não tem conta.
6. Limite de taxa para resolução e verificação de hosts.
7. Validação em MySQL 8.
8. Seis variações de design por segmento (apenas o template-base foi entregue).
9. Eventos de visita e conversão com consentimento e separação de robôs (planejado).
10. Páginas e seções em tabelas próprias, se o site crescer além de uma página.
11. Pendências anteriores sem alteração: rotação de MySQL e `APP_KEY`, remoção dos registros `[HOMOLOG]`, RootAdmin real, condições do "teste grátis".

## 17. Próxima etapa recomendada

Resolver a pendência 1 (robots estático) e homologar em navegador real. Em seguida, BACKEND-06: agenda com reserva transacional, que pode reutilizar `professional_availabilities` e os horários do site publicado.
