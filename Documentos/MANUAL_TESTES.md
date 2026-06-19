# Manual de Setup e Testes — Casa da Música

## 1. Arrancar o servidor

```bash
cd /Users/costap/Documents/DEAPC
bash start.sh
```

Ou diretamente:

```bash
cd /Users/costap/Documents/DEAPC
php -S localhost:8000
```

Abrir no browser: **`http://localhost:8000`**

> O `start.sh` já está criado na raiz do projeto. Basta `bash start.sh` a partir de qualquer terminal na pasta.

---

## 2. Credenciais de teste

| Perfil | Email | Palavra-passe | Redireciona para |
|---|---|---|---|
| Administrador | `paulo@cdmusica.pt` | `admin123` | `admin.php` |
| Vendedor | `joao@cdmusica.pt` | `vendedor123` | `vendedor.php` |
| Cliente | `ana@exemplo.com` | `cliente123` | `cliente.php` |
| Cliente | `carlos@gmail.com` | `cliente123` | `cliente.php` |

---

## 3. Roteiros de teste

### A — Compra online (sem conta)

1. `http://localhost:8000/eventos.php` — lista de eventos da BD
2. Clicar **Ver Mais** num evento (ex: id=8, "Farinelli")
3. Clicar **Selecionar Bilhetes** → `carrinho.php?evento_id=8`
4. Adicionar quantidades com `+` / `−`
5. Clicar **Continuar** (guarda em sessionStorage, vai para `pagamento.php`)
6. Preencher nome e email do cliente → **Pagar**
7. Deve aparecer `confirmacao.php` com referência da compra

### B — Histórico do cliente (com conta)

1. `http://localhost:8000/login.html` → entrar como `ana@exemplo.com` / `cliente123`
2. Redireciona para `cliente.php` — perfil + histórico de compras
3. A Ana já tem 2 compras registadas na BD

### C — Painel do administrador

1. Login como `paulo@cdmusica.pt` / `admin123`
2. `admin.php` — dashboard com stats reais da BD
3. `admin-eventos.php` — lista todos os eventos com filtro de estado
4. Clicar **Editar** → `admin-evento-editar.php?id=X` pré-preenchido com dados da BD
5. Alterar algo (ex: preço) → **Guardar Alterações** → confirmar na lista
6. Clicar **+ Novo Evento** → `admin-evento-criar.php` → preencher → **Criar Evento**
7. Clicar **Cancelar** num evento → confirmação → estado muda para "Cancelado"

### D — Venda presencial (vendedor)

1. Login como `joao@cdmusica.pt` / `vendedor123`
2. `vendedor.php` — tabela de disponibilidade dos eventos
3. Clicar **Emitir Bilhete** → formulário de venda
4. Preencher dados do cliente + quantidades → **Confirmar Venda**
5. Confirma com referência na mesma página

### E — Registo de novo utilizador

1. `http://localhost:8000/registo.html`
2. Preencher nome, email, password → submeter
3. Redireciona para `login.html?sucesso=conta_criada`
4. Fazer login com a nova conta

---

## 4. Verificar a BD diretamente

```bash
# Ver compras registadas
sqlite3 data/casaMusica.db "SELECT referencia, nome_cliente, total, estado FROM compras;"

# Ver eventos publicados
sqlite3 data/casaMusica.db "SELECT id, nome, data, estado FROM eventos WHERE estado='publicado';"

# Ver utilizadores registados
sqlite3 data/casaMusica.db "SELECT id, nome, email, perfil FROM utilizadores;"

# Ver bilhetes vendidos por evento
sqlite3 data/casaMusica.db "
  SELECT e.nome, SUM(ic.quantidade) as vendidos
  FROM itens_compra ic
  JOIN compras c ON c.id = ic.compra_id
  JOIN eventos e ON e.id = c.evento_id
  GROUP BY e.id;"
```

---

## 5. Problemas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| `Class "SQLite3" not found` | Extensão SQLite não activa | `php -m \| grep sqlite` — confirmar que aparece `sqlite3` |
| Página em branco / erro 500 | Erro PHP | Ver output do terminal onde corre o servidor |
| Login redireciona em loop | Sessões não iniciam | Confirmar `session_start()` em `scripts/sessao.php` — já está ✓ |
| `pagamento.php` redireciona para eventos | sessionStorage vazio | Navegar sempre a partir do `carrinho.php`, não diretamente |
| Compra falha com "evento não encontrado" | Evento não está publicado | Editar no admin e mudar estado para "Publicado" |
| BD sem permissões de escrita | Ficheiro read-only | `chmod 664 data/casaMusica.db` |

---

## 6. Estado atual da BD

| Tabela | Registos |
|---|---|
| `utilizadores` | 4 (1 admin, 1 vendedor, 2 clientes) |
| `eventos` | 30 (todos `publicado`, datas Mai–Jul 2026) |
| `precos` | 90 (3 tarifários por evento) |
| `compras` | 3 (para testar histórico de `ana@exemplo.com`) |
| `itens_compra` | 3 |

---

## 7. Mapa de páginas

```
Público
  index.html
  eventos.php           ← lista eventos da BD
  evento.php?id=X       ← detalhe do evento
  carrinho.php?evento_id=X  ← seleção de bilhetes
  pagamento.php         ← formulário de pagamento
  confirmacao.php?ref=X ← confirmação da compra
  login.html            → scripts/login.php
  registo.html          → scripts/registo.php

Cliente (requer login)
  cliente.php           ← perfil + histórico de compras

Vendedor (requer login)
  vendedor.php          ← emissão de bilhetes presencial

Admin (requer login)
  admin.php             ← dashboard com stats reais
  admin-eventos.php     ← lista e gestão de eventos
  admin-evento-criar.php    → scripts/criar_evento.php
  admin-evento-editar.php?id=X  → scripts/editar_evento.php
                        → scripts/cancelar_evento.php
```
