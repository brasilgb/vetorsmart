# VetorSmart — Créditos e licenças das imagens

Registro obrigatório de cada imagem utilizada na landing e nas áreas públicas. Nenhuma imagem é incluída no projeto sem licença verificada e registrada aqui.

**Situação atual: nenhuma fotografia registrada.** A landing usa, até aqui, o fundo vetorial local `public/images/auth-background.svg` (criado pelo projeto) e composições em CSS com as cores da paleta.

## Procedimento

1. Pesquisar fotografias nos bancos aprovados (Unsplash, Pexels, Pixabay) pela busca oficial de cada um.
2. Verificar a licença da foto específica na própria página dela antes de baixar.
3. Preferir o download pela API oficial do banco, quando houver chave de aplicativo, para cumprir as regras de rastreamento de cada serviço.
4. Converter para WebP e gerar versões responsivas em `public/images/landing/`.
5. Registrar a entrada abaixo e só então ligar a imagem em `resources/js/components/landing/photo-slots.ts`.

## Modelo de registro

| Arquivo | URL original | Autor | Banco | Licença | Data da consulta | Seção de uso | Atribuição |
|---|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — | — |

## Requisitos já conhecidos

- **Unsplash License**: uso gratuito, inclusive comercial. Não é permitido vender a imagem sem alteração significativa, nem usá-la para compor produto concorrente de bancos de imagens. Atribuição não obrigatória, mas registrada.
- **Pexels License** e **Pixabay Content License**: uso gratuito, inclusive comercial, sem atribuição obrigatória. Não é permitido identificar pessoas retratadas de forma que sugira endosso.

Em todos os casos: não usar fotografias com marcas registradas visíveis, nem de concorrentes.

## Registro de fotografias incorporadas

| Arquivo | URL original | Autor | Banco | Licença | Data da consulta | Seção de uso | Atribuição |
|---|---|---|---|---|---|---|---|
| `public/images/landing/hero-plumber.webp` | https://upload.wikimedia.org/wikipedia/commons/f/fa/Plumber_at_work.jpg (página: https://commons.wikimedia.org/w/index.php?curid=1613365) | photo taken by flickr user rick (Flickr: https://www.flickr.com/photos/spine) | Wikimedia Commons (via Openverse) | CC BY 2.0 — https://creativecommons.org/licenses/by/2.0/ | 10/11/2026 | Hero (fundo, película escura) | "Plumber at work" by photo taken by flickr user rick, CC BY 2.0 |

Observações:
- Imagens localizadas pela API oficial do Openverse (`api.openverse.org/v1/images/`) com filtro `license_type=commercial,modification`; a licença de cada arquivo foi conferida no detalhe de cada obra.
- Imagem de Alex Cheek: CC BY-SA exige que derivados sigam a mesma licença. Se a landing for reproduzir a foto em produto vendido, avaliar essa obrigação.
- A foto do eletricista (Alex Cheek) foi retirada a pedido: a cena não mostrava um profissional executando o serviço. O arquivo foi removido do projeto.
- Os demais segmentos e as funcionalidades continuam com fallback de gradiente, aguardando fotografias de profissionais executando cada tarefa, com licença verificada.
