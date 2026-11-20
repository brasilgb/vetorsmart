# VETORSMART-SITES-01 — Sites das empresas, Portal Regional e localização automática

Data: 20/11/2026, das 19:20 às 20:30 UTC. Local: VPS `srv1990862`, `gateway/vetorsmart/` (branch `feat/sites-portal-geo`, integrada na `main`).

## Parecer

**Concluída e em produção (`fd35157`).** As cinco frentes do correio foram implementadas, testadas e homologadas em desktop e celular. Uma pendência depende de terceiros: a homologação do Portal Regional com uma empresa real, porque nenhuma empresa aderiu ao portal até agora.

| Frente | Situação |
|---|---|
| 1. Sites públicos | Entregue |
| 2. Editor "Meu site" em perguntas e SEO | Entregue |
| 3. Portal Regional | Entregue. Validado com dados controlados; em produção, o portal ainda não tem empresas participantes |
| 4. Localização automática | Entregue (Nominatim/OpenStreetMap), validada em produção |
| 5. Jornada completa | 96 verificações locais e 40 em produção, todas aprovadas |

## 1. O que já existia e foi reaproveitado

- **Catálogo de serviços:** os serviços do site continuam vindo do catálogo.
- **Editor e publicação:** rascunho, versões imutáveis e publicação atômica foram mantidos.
- **Mídia privada:** o mecanismo de imagens privadas por empresa foi reaproveitado.
- **Endereços do site:** endereço padrão, subdomínio e domínio próprio, sem mudança.
- **Atribuição comercial:** link assinado do portal (`PortalReferral`) e UTM continuam como estavam.
- **Ordenação do portal:** segue neutra.
- **Municípios:** não havia cadastro de municípios. A cidade era texto livre em Configurações. Por isso, criei o cadastro oficial do IBGE, descrito na seção 4.

## 2. Funcionalidades entregues

### Site público (`website/site.blade.php`)

**Cabeçalho**
- Logomarca ou nome da empresa.
- Menu com Serviços, Quem somos, Onde atendemos e Contato.
- Botão "Solicitar orçamento". No celular, o menu ocupa uma linha própria.

**Hero**
- Foto própria com texto alternativo editável.
- Sem foto, mostra uma ilustração padrão do segmento: SVG embutido, sem banco de imagens.
- H1: a frase de destaque, ou "{segmento} em {cidade}, {UF}".
- O que a empresa faz, a partir dos serviços publicados.
- "Sede em X · atendemos N municípios".
- Botão "Solicitar orçamento".

**Quem somos**
- Apresentação, foto opcional e diferenciais.
- Certificações, tempo de atuação e avaliações só aparecem com a fonte informada.

**Serviços oferecidos**
- Seguem a ordem escolhida pela empresa.
- Cada serviço tem descrição própria ou a do catálogo, foto e preço público ou "Sob consulta".
- O botão "Solicitar orçamento" abre o formulário com o serviço já escolhido.
- Quando há um único serviço, ele ganha um layout próprio.

**Onde atendemos**
- Sede, municípios atendidos e modalidade: vem até nós, vamos até você ou ambos.

**Rodapé**
- Contatos públicos, horários, região e links úteis.
- O endereço e o "Como chegar" (Google Maps) só aparecem quando a empresa opta por publicar.
- Campos vazios ficam ocultos.

**Atendimento online**
- "Agendar online" só aparece quando o atendimento IA está ativo e algum serviço tem profissional com disponibilidade.
- Fora disso, o botão é "Atendimento online".

**Cores**
- Oito paletas, com sugestões por segmento, e cor principal livre.
- A cor é escurecida automaticamente até alcançar contraste de 4,5:1.

### Editor "Meu site"

**Montar o site:** nove perguntas, cada uma salva apenas a sua parte do rascunho.
1. Logomarca e frase de destaque.
2. Foto e descrição da foto.
3. Apresentação, diferenciais e informações com fonte.
4. Sede e municípios atendidos, com sugestões do IBGE.
5. Modalidade de atendimento.
6. Serviços: escolha, ordem, descrição, preço público e foto de cada um.
7. Contatos, horários e endereço públicos.
8. Paleta, cor e tipografia.
9. SEO: título e descrição sugeridos, editáveis, com prévia do resultado de busca.

**Prévias:** no computador e no celular (moldura de 390 px).

**Abas mantidas:** Visão geral, Publicação, Domínios e Portal Regional.

### SEO

- H1 claro.
- Título e descrição sugeridos a partir do nome, dos serviços e da região. Um campo vazio usa a sugestão.
- Canonical, Open Graph e Twitter card.
- JSON-LD `ProfessionalService` com:
  - `areaServed` por município (`City`);
  - `PostalAddress`, com a rua só se publicada;
  - `openingHoursSpecification`;
  - `hasOfferCatalog`.
- Mantidos: o `noindex` da prévia e da homologação, as regras do sitemap e o canonical no subdomínio (conferido em produção).

### Portal Regional

**Busca**
- Em destaque: "Qual serviço você precisa?", "Em qual município?" e "Usar minha localização".
- Em "Mais filtros": categoria, nome da empresa e estado, além de "Limpar todos os filtros".

**Área atendida**
- O portal busca também pela área atendida, na tabela `portal_listing_areas`, montada só a partir da versão publicada.
- Exemplo: uma empresa sediada em Montenegro (RS) que atende Salvador do Sul aparece na busca por Salvador do Sul.
- Municípios atendidos ganham página própria (`/regioes/rs/salvador-do-sul`) e entram no sitemap.

**Cards**
- Nome, principais serviços, "Sede em Montenegro, RS", "Atende em Salvador do Sul, RS", área atendida e os botões "Ver site da empresa" e "Solicitar orçamento".

**Estados vazios**
| Situação | Ação oferecida |
|---|---|
| Portal ainda sem empresas | Como funciona |
| Município sem cobertura | Trocar o município, ampliar para o estado ou ver todas as empresas |
| Busca sem correspondência | Buscas mais amplas e "Limpar os filtros" |

**Mantidos:** a participação voluntária e a listagem só de empresas elegíveis com site publicado. Empresas de homologação continuam fora do portal.

### Localização automática

- **Onde:** botão "Usar minha localização" ao lado do campo Município, na busca do portal e no formulário de orçamento.
- **Disponibilidade:** o botão só aparece em contexto seguro (HTTPS) com a API de geolocalização. A permissão é pedida só no clique.
- **Durante a busca:**
  - o botão mostra "Localizando…";
  - o campo manual continua ativo o tempo todo.
- **Resposta:**
  - "Encontramos Salvador do Sul, RS", com "Confirmar" e "Escolher outro";
  - recusa, demora, erro, posição fora do Brasil ou resultado ambíguo levam a uma orientação para escolher o município manualmente.
- **Do portal ao formulário:**
  - o portal passa só `municipio=<código IBGE>` ao site;
  - o site guarda o código na sessão, por empresa, até o formulário;
  - o visitante pode corrigir o município.
- **Fora da área:** se o município estiver fora da área cadastrada, o formulário mostra a cobertura da empresa e permite revisar. O envio continua possível, para atendimento em outra cidade.
- **No pedido:** o pedido grava o município confirmado em `service_requests.municipality_id`, e o detalhe da solicitação no painel mostra "Município do atendimento".
- **Atribuição:** a do portal, por link assinado, está preservada. O teste cobre portal + município.

#### Privacidade

- O navegador arredonda a posição para 3 casas decimais (cerca de 110 m) e a envia no corpo de um POST para o mesmo host.
- O servidor arredonda de novo e envia a posição só ao provedor.
- Nada é guardado nem registrado: falhas vão para o log apenas pelo tipo.
- Verificado em produção: nenhuma coordenada nos logs da aplicação nem no log de acesso do Nginx.

#### Provedor e limites

O provedor é o Nominatim (OpenStreetMap), configurável em `config/geolocation.php`:

| Variável | Uso |
|---|---|
| `GEOLOCATION_PROVIDER` | `nominatim` ou `none`; com `none`, o botão some do uso efetivo |
| `GEOLOCATION_NOMINATIM_URL` | Instância própria, se houver |
| `GEOLOCATION_USER_AGENT` | Identificação genérica da plataforma |
| `GEOLOCATION_TIMEOUT` | Padrão: 5 s |
| `GEOLOCATION_GLOBAL_PER_MINUTE` | Padrão: 50 |
| `GEOLOCATION_VISITOR_PER_MINUTE` | Padrão: 6 |

- A instância pública aceita no máximo 1 requisição por segundo. Por isso há o limite global, e acima dele o visitante escolhe o município manualmente.
- Para volume maior: instância própria do Nominatim ou um provedor comercial, trocando só a configuração e `ReverseGeocoder`.
- A resposta é casada com o cadastro do IBGE por nome e UF, sem acento.

## 3. Correção encontrada na homologação de produção

O envio de logomarca e fotos **nunca funcionou em produção**: o PHP da imagem foi compilado sem JPEG e WebP no GD, e a mensagem era "Não foi possível ler a imagem". O defeito vem da WEBSITES-10 e não aparecia nos testes porque o host tem um GD completo.

**Correção:** commit `fd35157` no app e linha do volume na infra.
- `Dockerfile`: `docker-php-ext-configure gd --with-jpeg --with-webp`.
- Healthcheck: recusa um container sem esses formatos, o que barra a troca.
- As imagens dos sites (`storage/app/private/websites`) estavam no sistema de arquivos do container e seriam perdidas a cada deploy. Agora ficam no volume `./volumes/vetorsmart-websites`. O entrypoint garante o dono `www-data`.

## 4. Banco de dados

**Migration:** `2026_11_20_200000_create_municipalities_and_service_areas`.

**Criada**
- `municipalities`: 5.571 municípios do IBGE, a partir de `database/data/municipios-ibge.csv`, baixado da API de localidades do IBGE em 20/11/2026.

**Alteradas**
- `portal_listings.municipality_id`: sede.
- `portal_listing_areas`: municípios atendidos.
- `service_requests.municipality_id`: nulo nos registros antigos.

**Execução**
- Ensaio na cópia do dump, em rede isolada: 249 ms, dados intactos.
- Produção: 676 ms.

**Rascunho:** novas chaves no JSON. Os rascunhos e versões antigas continuam válidos e recebem valores padrão.

## 5. Testes

| Verificação | Resultado |
|---|---|
| `SitesPortalLocationTest` (novo) | 16 testes |
| Suíte completa (MySQL 8.4 descartável) | **905 testes: 890 aprovados, 15 pulados, 0 falhas** |
| PHPStan / Pint / TypeScript / build | 0 erros / ok / ok / ok |
| Homologação local (Playwright, dados controlados) | **96/96** em desktop e celular |
| Homologação em produção (empresa #10, Playwright) | **40/40** em desktop e celular |

**Ajustes em testes existentes**
- Município passou a ser obrigatório no formulário.
- Novos textos: "Solicitar orçamento", "Ver site da empresa", "Sede em…/Atende em…".
- "Agendar online" agora exige agenda real.
- Os três estados vazios do portal.

**O que a homologação local verificou**
- Portal com localização, confirmação e busca em Salvador do Sul.
- Card que distingue sede e área atendida.
- Site com foto, três serviços, área atendida, horários e "Como chegar".
- Teclado chega a "Solicitar orçamento" na 7ª tabulação, com foco visível.
- Serviço e município pré-preenchidos e editáveis.
- Aviso de fora da área.
- Envio do pedido.
- Site com um serviço e sem foto.
- Localização negada, busca sem resultado e região sem cobertura.
- Painel com o município, origem Portal Regional e Meu Crescimento.
- Editor e prévia no celular.
- Isolamento entre empresas.
- axe WCAG 2.1 AA sem violações em todas as páginas públicas.
- Sem rolagem horizontal e sem erros de JS ou CSP.

**O que a homologação de produção verificou**
- Editor guiado preenchido pela interface e publicação (versão 6 da #10).
- Site no subdomínio com foto, três serviços, área atendida, canonical e `noindex`.
- Formulário com localização real via HTTPS e Nominatim.
- Pedido #8 com município Salvador do Sul.
- Portal com estado "sem empresas" e localização.
- Isolamento: a empresa B recebe 404.

Evidências (capturas, resultados e roteiros): `backups/vetorsmart_sites01_20261120_200309/homolog/`.

## 6. Deploy

**Ponto de retorno**
- Imagens marcadas como `rollback-pre-sites01`: web `9dc82465d089`, worker `8eba63ada926`, scheduler `a52a59daeb5d`.
- Dump `vetorsmart_pre_sites01.sql` (permissão 600) com `.sha256`.
- Também ficou marcada a imagem intermediária `sites01-sem-gd`.

**Trocas sem 502**
| Troca | Mudança | Sonda |
|---|---|---|
| 1 | `vetorsmart-12` → `13` (`50e993b8df2f`) | 530/530 respostas 200 |
| 2 | `13` → `14` (`df9c516b974c`, GD corrigido e volume) | 530/530 respostas 200 |

- Worker (`cb0e0cd8fa27`) e scheduler (`bfc381f4e370`) estão healthy.
- O VetorOS manteve 106/106 respostas 200 em cada troca.

## 7. Pendências

1. **Região piloto com prestadores reais:** nenhuma empresa aderiu ao Portal Regional. Empresas de homologação ficam fora por regra. Falta a adesão de prestadores reais (por exemplo, no Vale do Caí) com autorização de publicação, para validar em produção a busca por área atendida e o card "Atende em". Isso já está validado com dados controlados.
2. **Empresas já publicadas:** só ganham sede estruturada, municípios atendidos e o novo layout de serviços depois de responder às perguntas e publicar de novo. Até lá, o site usa a cidade de Configurações e a região em texto livre.
3. **Geocodificação:** a instância pública do Nominatim é adequada ao volume atual. Com crescimento, avaliar instância própria ou provedor pago.
4. **Endereço no site:** passou a ser publicado só por opção (padrão: desligado). Sites que mostravam o endereço deixam de mostrá-lo na próxima publicação até a empresa marcar a opção.
5. **Dados de homologação da #10:**
   - versão 6 publicada;
   - serviço 7 "Formatação e backup (teste)";
   - pedidos #7 e #8;
   - foto em `volumes/vetorsmart-websites/10`.

   Saem com `homolog:purge`.
