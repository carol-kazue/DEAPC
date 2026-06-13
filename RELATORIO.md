# Relatório Técnico — Sistema de Bilhética da Casa da Música

**Unidade curricular:** Desenvolvimento de Aplicações para a Web (DEAPC)  
**Grupo 18 — ESMAD / IPP**

| Nome | Número |
|------|--------|
| Ana Inada | 1242098 |
| Pedro Silva | 1242116 |
| Paulo Costa | 1231470 |

**Repositório:** https://github.com/carol-kazue/DEAPC  
**Versão final:** `main` — junho 2026

---

## 1. Descrição do Projeto

Aplicação web para gestão e venda de bilhetes da **Casa da Música do Porto**. O sistema permite a compra online de bilhetes por clientes registados ou como convidado, a emissão presencial de bilhetes por vendedores, e a gestão completa de eventos e compras pelo administrador. O projeto foi desenvolvido sem frameworks externos — PHP puro, SQLite e CSS/JS nativos.

---

## 2. Tecnologias e Ferramentas

| Camada | Tecnologia | Versão / Nota |
|--------|-----------|---------------|
| Linguagem servidor | PHP | 8.5 (built-in dev server) |
| Base de dados | SQLite 3 | via extensão `php-sqlite3` |
| Linguagem cliente | HTML5 + CSS3 + JavaScript (ES6) | sem frameworks |
| Tipografia | Google Fonts | Cinzel + Inter (via `@import`) |
| Controlo de versões | Git | GitHub — `carol-kazue/DEAPC` |
| Servidor local | `php -S localhost:8000` | script `start.sh` |
| Email | SMTP próprio (PHP `fsockopen`) | STARTTLS + AUTH LOGIN |

Sem Composer, sem npm, sem frameworks JS ou CSS — todas as dependências são resolvidas nativamente.

---

## 3. Arranque Local

```bash
# Na raiz do projeto
./start.sh
# ou
bash start.sh
```

O servidor fica disponível em `http://localhost:8000`. A base de dados SQLite está em `data/casaMusica.db` e as sessões PHP em `data/sessions/`.

---

## 4. Estrutura de Ficheiros

```
DEAPC/
├── index.php                      # Página inicial (landing page)
├── eventos.php                    # Listagem pública de eventos (com filtros e paginação)
├── evento.php                     # Detalhe de um evento + selecção de bilhetes
├── carrinho.php                   # Revisão da encomenda antes do pagamento
├── pagamento.php                  # Formulário de pagamento (simulado) + comprovativo popup
├── confirmacao.php                # Página de confirmação pós-compra + reenvio de email
├── cliente.php                    # Área pessoal do cliente (histórico de compras)
├── vendedor.php                   # Área do vendedor (emissão presencial de bilhetes)
├── bilhetes.php                   # Gestão de bilhetes (admin + vendedor)
├── login.html                     # Formulário de autenticação
├── registo.html                   # Formulário de registo de clientes
├── start.sh                       # Script de arranque do servidor local
│
├── admin/                         # Área de administração
│   ├── index.php                  # Dashboard (estatísticas + próximos eventos)
│   ├── eventos.php                # Listagem e gestão de eventos
│   ├── evento-criar.php           # Formulário de criação de evento
│   └── evento-editar.php          # Formulário de edição de evento
│
├── scripts/                       # Lógica de negócio (back-end)
│   ├── db.php                     # Ligação à base de dados SQLite
│   ├── sessao.php                 # Gestão de sessões e controlo de acesso
│   ├── login.php                  # Autenticação (POST)
│   ├── logout.php                 # Destruição de sessão
│   ├── registo.php                # Criação de conta de cliente (POST)
│   ├── criar_evento.php           # Criação de evento (POST, admin)
│   ├── editar_evento.php          # Edição de evento (POST, admin)
│   ├── cancelar_evento.php        # Cancelamento de evento (POST, admin)
│   ├── processar_compra.php       # Registo de compra online (POST, suporta JSON/AJAX)
│   ├── vender_bilhete.php         # Registo de venda presencial (POST, suporta JSON/AJAX)
│   ├── atualizar_estado_bilhete.php # Cancelar/reativar bilhete (POST, admin + vendedor)
│   ├── enviar_bilhete.php         # Envio de comprovativo por email (POST, AJAX)
│   ├── mailer.php                 # Cliente SMTP + template HTML do bilhete
│   ├── validacao.js               # Funções de validação de formulários (cliente)
│   ├── listar_eventos.php         # API interna de listagem de eventos
│   ├── get_evento.php             # API interna de detalhe de evento
│   ├── sessao_status.php          # Estado da sessão (JSON)
│   ├── setup_admin.php            # Criação do utilizador administrador inicial
│   └── setup_check.php            # Verificação de configuração inicial
│
├── styles/                        # Folhas de estilo CSS
│   ├── index.css
│   ├── eventos.css
│   ├── evento.css
│   ├── carrinho.css
│   ├── pagamento.css
│   ├── confirmacao.css
│   ├── cliente.css
│   ├── login.css
│   ├── registo.css
│   ├── vendedor.css
│   ├── admin.css
│   ├── admin-evento-editar-criar.css
│   └── bilhetes.css
│
└── data/
    ├── casaMusica.db              # Base de dados SQLite
    └── sessions/                  # Ficheiros de sessão PHP
```

---

## 5. Base de Dados

A base de dados SQLite contém 6 tabelas:

### `utilizadores`
Armazena todos os utilizadores do sistema.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INTEGER PK | Identificador |
| `nome` / `apelido` | TEXT | Nome completo |
| `email` | TEXT UNIQUE | Email de acesso |
| `password_hash` | TEXT | Hash bcrypt da palavra-passe |
| `perfil` | TEXT | `cliente` \| `vendedor` \| `administrador` |
| `data_nascimento`, `nif`, `telefone` | TEXT | Dados opcionais |
| `estado` | TEXT | `ativo` \| `suspenso` |

### `eventos`
Eventos musicais geridos pelo administrador.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INTEGER PK | Identificador |
| `nome` | TEXT | Título do evento |
| `descricao` | TEXT | Descrição |
| `data` / `hora` | TEXT | Data e hora de realização |
| `sala` | TEXT | Local (Sala Suggia, Grande Auditório, etc.) |
| `categoria` | TEXT | Género musical (13 categorias) |
| `capacidade` | INTEGER | Lotação máxima |
| `estado` | TEXT | `rascunho` \| `publicado` \| `cancelado` |

### `precos`
Tarifários por tipo de bilhete, associados a um evento.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `evento_id` | INTEGER FK | Evento |
| `tipo` | TEXT | `normal` \| `jovem` \| `senior` |
| `preco` | REAL | Preço em euros |

Constraint UNIQUE em `(evento_id, tipo)`.

### `compras`
Cabeçalho de cada transacção (online ou presencial).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `referencia` | TEXT UNIQUE | Código de referência gerado (ex: `2026-001234`) |
| `evento_id` | INTEGER FK | Evento |
| `utilizador_id` | INTEGER FK (nullable) | Cliente registado (NULL se convidado) |
| `nome_cliente` / `email_cliente` | TEXT | Dados do comprador |
| `canal` | TEXT | `online` \| `presencial` |
| `vendedor_id` | INTEGER FK (nullable) | Vendedor que emitiu (presencial) |
| `metodo_pagamento` | TEXT | `cartao` \| `multibanco` \| `mbway` \| `dinheiro` |
| `total` | REAL | Valor total da compra |
| `estado` | TEXT | `confirmado` \| `cancelado` |

### `itens_compra`
Linhas de cada compra (bilhetes por tipo).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `compra_id` | INTEGER FK | Compra |
| `tipo` | TEXT | `normal` \| `jovem` \| `senior` |
| `quantidade` | INTEGER | Número de bilhetes |
| `preco_unitario` | REAL | Preço no momento da compra |

### `acessos`
Registo de logins (auditoria).

---

## 6. Perfis de Utilizador e Controlo de Acesso

O sistema tem três perfis, controlados pela função `requirePerfil()` em `sessao.php`:

| Perfil | Acesso |
|--------|--------|
| **Público** | `index.php`, `eventos.php`, `evento.php`, `carrinho.php`, `pagamento.php`, `confirmacao.php` |
| **Cliente** | Tudo o acima + `cliente.php` (histórico de compras pessoal) |
| **Vendedor** | `vendedor.php` (emissão presencial) + `bilhetes.php` (apenas as suas vendas) |
| **Administrador** | Tudo + `admin/*` + `bilhetes.php` (todas as compras, reativar) |

As sessões PHP são guardadas em ficheiro (`data/sessions/`) para funcionar correctamente com o servidor built-in.

---

## 7. Funcionalidades

### 7.1 Área Pública

#### Listagem de Eventos (`eventos.php`)
- Grelha de cartões com paginação (6 por página)
- Filtros: pesquisa por nome, intervalo de datas, categoria
- 13 categorias musicais: Sinfónico, Música Clássica, Jazz, Ópera, Fado, Hip-Hop, Rock, entre outras
- Indicação de preço mínimo em cada cartão

#### Detalhe de Evento (`evento.php`)
- Informação completa: nome, data, hora, sala, categoria, classificação etária, descrição
- Tabela de tarifários (Normal, Jovem ≤30, Sénior +65)
- Selecção de quantidades de bilhetes por tipo
- Botão "Comprar" → guarda no `sessionStorage` e redireciona para `carrinho.php`

#### Carrinho (`carrinho.php`)
- Revisão da encomenda com resumo de bilhetes e total
- Lê o estado do `sessionStorage`

#### Pagamento (`pagamento.php`)
- Formulário de dados do cliente (nome, email, telefone)
- Formulário de dados do cartão (validação client-side: número 16 dígitos, validade MM/AA, CVV)
- **Simulação de API de pagamento**: ao submeter, o formulário é interceptado (fetch/AJAX), um overlay animado percorre 4 passos:
  1. Ligação segura estabelecida
  2. Dados do cartão verificados
  3. Pagamento autorizado pelo emissor
  4. Bilhetes emitidos e reservados
- Após animação: exibe **comprovativo estilo bilhete de teatro** (fundo escuro, bordas gold, linha de perfuração CSS, código de barras em gradiente CSS)
- Botões: Imprimir (media query print), Enviar por email, Continuar

#### Confirmação (`confirmacao.php`)
- Resumo completo da compra (referência, evento, bilhetes, total)
- Botão de reenvio do comprovativo por email

### 7.2 Área do Cliente (`cliente.php`)

- Histórico de todas as compras do utilizador autenticado
- Detalhe de cada compra com breakdown de bilhetes
- Estado de cada compra (confirmada/cancelada)

### 7.3 Área do Vendedor (`vendedor.php`)

- Tabela de eventos publicados com lugares disponíveis em tempo real
- Botão **"Emitir Bilhete"** por evento → abre modal inline:
  - Tipos de bilhete com preços e controlos de quantidade (−/+)
  - Campos do cliente: nome, email, telefone, NIF
  - Método de pagamento (Dinheiro, Multibanco, MB Way, Cartão)
  - Total calculado em tempo real
- Ao confirmar: **simulação de API presencial** (3 passos animados) → comprovativo
- O comprovativo inclui botão "Enviar por email" pré-preenchido com o email do cliente

#### Gestão de Bilhetes (`bilhetes.php`) — partilhada por vendedor e admin
- Vendedor vê apenas as suas vendas; administrador vê tudo
- Filtros: pesquisa (referência ou nome), evento, estado, canal, intervalo de datas
- Paginação (25 por página)
- Botão **"Ver"**: modal com detalhe completo (dados cliente, breakdown bilhetes, total)
- Botão **"Cancelar"**: modal de confirmação → altera estado para `cancelado`
- Botão **"Reativar"** (admin only): restaura estado para `confirmado`
- Sumário de receita (admin): total de compras confirmadas e valor agregado

### 7.4 Área de Administração

#### Dashboard (`admin/index.php`)
- Estatísticas: eventos publicados, bilhetes vendidos, receita total
- Lista dos próximos 10 eventos com ocupação

#### Gestão de Eventos (`admin/eventos.php`)
- Tabela com todos os eventos (ordenados por data)
- Filtro por estado (publicado/rascunho/cancelado)
- Ações por evento: Editar, Cancelar (com modal de confirmação temático)

#### Criar/Editar Evento (`admin/evento-criar.php`, `admin/evento-editar.php`)
- Campos: título, data, hora, sala, categoria, classificação etária, lotação, descrição
- Tarifários: tabela com preços Normal, Jovem, Sénior
- Estado de publicação (rascunho/publicado)
- Validação client-side e server-side

### 7.5 Envio de Email (`scripts/mailer.php`, `scripts/enviar_bilhete.php`)

Cliente SMTP implementado do zero em PHP puro (sem Composer, sem PHPMailer):
- Protocolo SMTP com `STARTTLS` + `AUTH LOGIN` via `fsockopen`
- Encriptação TLS com `stream_socket_enable_crypto`
- Template HTML do email: mesmo design escuro do comprovativo, com tabela de bilhetes e código de barras em unicode
- Configuração em `scripts/mailer.php` (compatível com Gmail App Password, Outlook, etc.)
- Endpoint `scripts/enviar_bilhete.php`: aceita `referencia` + `email` opcional (override do email guardado)

---

## 8. Design e Interface

### Tema Visual
Paleta inspirada num ambiente de sala de espectáculo:

| Variável | Cor | Uso |
|----------|-----|-----|
| Fundo principal | `#0b0b14` | `body` |
| Superfície | `#12121f` | Cartões, tabelas |
| Dourado | `#c9a83c` | Títulos, botões primários, acentos |
| Texto | `#f0ece4` | Corpo de texto |
| Texto secundário | `#9e9080` | Metadados, labels |
| Admin (nav) | `#0a0805` + borda dourada | Diferencia área admin |
| Vendedor (nav) | `#090b14` + borda azul | Diferencia área vendedor |

### Tipografia
- **Cinzel** (Google Fonts) — títulos, logótipo, cabeçalhos de secção
- **Inter** (Google Fonts) — corpo de texto, formulários

### Modais Temáticos
- Cancelamento de evento (admin): confirmação antes de submeter
- Emissão de bilhete (vendedor): seleção de bilhetes, dados do cliente
- Detalhe de compra (bilhetes.php): breakdown de itens
- Cancelar/reativar bilhete: confirmação com feedback visual
- Simulação de pagamento: overlay com steps animados + comprovativo

### Comprovativo / Bilhete
Estilo de bilhete de teatro com:
- Linha de perfuração CSS (`border-top: dashed` + pseudo-elementos `::before`/`::after` circulares)
- Código de barras em gradiente CSS (`repeating-linear-gradient`)
- Media query `@media print` para impressão limpa

---

## 9. Fluxos Principais

### Compra Online
```
eventos.php → evento.php → carrinho.php → pagamento.php
                                             ↓ fetch AJAX
                                       processar_compra.php
                                             ↓ JSON
                                       popup: simulação API
                                             ↓
                                       popup: comprovativo
                                       [Imprimir | Enviar email | Continuar]
                                             ↓
                                       confirmacao.php
```

### Emissão Presencial (Vendedor)
```
vendedor.php → modal "Emitir Bilhete"
                    ↓ fetch AJAX
              vender_bilhete.php
                    ↓ JSON
              overlay: simulação API
                    ↓
              overlay: comprovativo
              [Imprimir | Enviar email | Fechar → reload]
```

### Gestão de Evento (Admin)
```
admin/index.php → admin/eventos.php
                       ↓
              evento-criar.php / evento-editar.php
                       ↓ POST
              criar_evento.php / editar_evento.php
                       ↓ redirect
              admin/eventos.php?sucesso=...
```

---

## 10. Aspectos Técnicos Relevantes

### Sessões PHP em ficheiro
O servidor `php -S` não inicializa o caminho de sessão automaticamente. O `sessao.php` define:
```php
$sessDir = __DIR__ . '/../data/sessions';
session_save_path($sessDir);
```

### Respostas duplas (HTML + JSON)
Os scripts `processar_compra.php` e `vender_bilhete.php` detectam se a chamada é AJAX:
```php
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
          && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
```
Se sim, devolvem JSON; caso contrário, fazem `header('Location: ...')` — compatibilidade com e sem JavaScript.

### Verificação de disponibilidade em tempo real
A disponibilidade de bilhetes é sempre calculada na query com `SUM(itens_compra.quantidade)` WHERE `compras.estado = 'confirmado'`, garantindo que cancelamentos libertam lugares imediatamente.

### Validação
- **Client-side** (`scripts/validacao.js`): funções `marcaErro()` / `limpaErro()`, validação de email, telefone, formato de cartão de crédito
- **Server-side**: todos os scripts PHP validam e sanitizam os dados independentemente do cliente

### Protecção de rotas
`requirePerfil(string $perfil, string $redirect)` em `sessao.php` verifica `$_SESSION['perfil']` e redireciona se não corresponder.

---

## 11. Configuração de Email

Para activar o envio de email, editar `scripts/mailer.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');   // ou outro servidor SMTP
define('SMTP_PORT', 587);
define('SMTP_USER', 'seuemail@gmail.com');
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx'); // App Password (Gmail: Conta → Segurança → Palavras-passe de apps)
```

O sistema detecta automaticamente se as credenciais não foram configuradas e devolve uma mensagem de erro apropriada sem crashar.

---

## 12. Histórico de Desenvolvimento (Git)

| Commit | Descrição |
|--------|-----------|
| `a39620a` | Tema dark concert hall aplicado a todos os CSS |
| `b0dedec` | Modal temático a substituir `confirm()` nativo no admin |
| `58a85f8` | Estilos de formulário harmonizados no admin |
| `2639e3b` | Categorias completas nos dropdowns |
| `c145b39` | Modal de emissão de bilhete no vendedor (sem reload) |
| `3de7ebd` | Página `bilhetes.php` — gestão partilhada admin/vendedor |
| `cf6292e` | Simulação de API com comprovativo estilo bilhete de teatro |
| `6b7a2a9` | Envio de comprovativo por email (SMTP próprio) |
| `dbec05c` | Correcção de caminhos relativos no admin |
| `c8ffa9c` | Correcção de links `index.html → index.php` |

---

## 13. Referências

- [PHP Manual — sqlite3](https://www.php.net/manual/en/book.sqlite3.php)
- [PHP Manual — fsockopen](https://www.php.net/manual/en/function.fsockopen.php)
- [RFC 5321 — Simple Mail Transfer Protocol](https://datatracker.ietf.org/doc/html/rfc5321)
- [Google Fonts — Cinzel](https://fonts.google.com/specimen/Cinzel)
- [Casa da Música Porto](https://www.casadamusica.com)
