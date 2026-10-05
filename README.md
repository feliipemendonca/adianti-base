# Adianti Framework — Ambiente de Desenvolvimento

Projeto baseado no **Adianti Framework 8.6** (PHP 8.4+), com ambiente Docker (Apache + MySQL + phpMyAdmin), migrations e seeders.

---

## Sumário

- [Requisitos](#requisitos)
- [Docker](#docker)
- [Acessos](#acessos)
- [Configuração do banco](#configuração-do-banco)
- [Migrations](#migrations)
- [Seeders](#seeders)
- [Comandos úteis](#comandos-úteis)
- [Estrutura relevante](#estrutura-relevante)

---

## Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (com Docker Compose)
- Git (opcional)

Não é necessário instalar PHP ou MySQL no host: tudo roda nos containers.

---

## Docker

### Serviços

| Serviço | Container | Descrição |
|---------|-----------|-----------|
| `app` | `adianti_app` | PHP 8.4 + Apache (código do projeto) |
| `db` | `adianti_db` | MySQL 8.0 |
| `phpmyadmin` | `adianti_phpmyadmin` | Interface web do MySQL |

### Arquivos

```text
Dockerfile                 # Imagem PHP/Apache + extensões Adianti
docker-compose.yml         # Orquestração dos serviços
.env                       # Portas e credenciais
docker/
├── apache/000-default.conf
├── php/php.ini
└── mysql/init/            # Scripts .sql executados na 1ª subida do volume
```

### Subir o ambiente

Na raiz do projeto:

```bash
docker compose up -d --build
```

### Parar / reiniciar

```bash
docker compose stop
docker compose start
docker compose down          # para e remove containers (mantém volume do MySQL)
docker compose down -v       # também apaga o volume mysql_data (zera o banco)
```

### Variáveis (`.env`)

| Variável | Padrão no projeto | Descrição |
|----------|------------------|-----------|
| `APP_PORT` | `80` | Porta HTTP da aplicação |
| `MYSQL_PORT` | `3306` | Porta do MySQL no host |
| `PHPMYADMIN_PORT` | `8086` | Porta do phpMyAdmin |
| `MYSQL_ROOT_PASSWORD` | `root` | Senha do root |
| `MYSQL_DATABASE` | `adianti` | Nome do banco |
| `MYSQL_USER` | `adianti` | Usuário da aplicação |
| `MYSQL_PASSWORD` | `adianti` | Senha do usuário |

Ajuste as portas no `.env` se houver conflito com outros serviços locais.

### Init SQL (opcional)

Arquivos em `docker/mysql/init/*.sql` rodam **somente na primeira criação** do volume MySQL.  
Se o volume já existir, use migrations ou execute o SQL manualmente.

### Extensões PHP na imagem

`pdo_mysql`, `pdo_sqlite`, `mysqli`, `mbstring`, `zip`, `gd`, `intl`, `opcache`, além de `mod_rewrite` no Apache.

---

## Acessos

Com o `.env` atual:

| Recurso | URL / Host |
|---------|------------|
| Aplicação | http://localhost:80 |
| phpMyAdmin | http://localhost:8086 |
| MySQL (host) | `localhost:3306` |

**Credenciais MySQL / phpMyAdmin**

- Usuário: `adianti`
- Senha: `adianti`
- Banco: `adianti`
- Root: `root` / `root`

Dentro da rede Docker, o host do MySQL para a aplicação é `db` (não `localhost`).

---

## Configuração do banco

Arquivo usado pelas migrations e pela app:

`app/config/database.php`

```php
'host' => 'db',
'name' => 'adianti',
'user' => 'adianti',
'pass' => 'adianti',
'type' => 'mysql',
```

No código Adianti:

```php
TTransaction::open('database'); // nome do arquivo de config (sem .php)
```

> Existe também `app/config/sample.php` (nome da application). Use a conexão que o seu código realmente abrir.

---

## Migrations

Sistema simples de versionamento de schema em `database/`.

### Estrutura

```text
database/
├── MigrationRunner.php
├── SeedRunner.php
├── migrations/
│   └── 001_clientes.php
└── seeders/
    └── 001_clientes.php
```

| Item | Descrição |
|------|-----------|
| Conexão | `app/config/database.php` |
| Controle | Tabela `schema_migrations` (criada automaticamente) |
| Pasta | `database/migrations/*.php` |

### Comandos

```bash
# Aplicar pendentes
docker compose exec app php database/MigrationRunner.php migrate

# Reverter a última (padrão: 1)
docker compose exec app php database/MigrationRunner.php rollback

# Reverter as N últimas
docker compose exec app php database/MigrationRunner.php rollback 2

# Status
docker compose exec app php database/MigrationRunner.php status
```

### Criar uma nova migration

1. Crie `database/migrations/002_nome.php` (prefixo numérico crescente).
2. Retorne um array com `up` e `down`:

```php
<?php

return [
    'up' => function () {
        $pdo = TTransaction::get();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS exemplo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },

    'down' => function () {
        $pdo = TTransaction::get();
        $pdo->exec("DROP TABLE IF EXISTS exemplo");
    },
];
```

### Regras

- Use sempre `TTransaction::get()` — a conexão já é aberta pelo runner.
- **Não** chame `TTransaction::open()`, `begin()`, `commit()` ou `rollback()` dentro da migration.
- `up` aplica; `down` desfaz (SQL inverso).
- O prefixo (`001`, `002`...) define a ordem.
- Cada versão roda uma vez (registrada em `schema_migrations`).

### Como funciona

**migrate**

1. Abre conexão com `TTransaction::openFake('database')`.
2. Garante `schema_migrations`.
3. Executa o `up` de cada arquivo ainda não registrado.

**rollback [n]**

1. Lê quantas steps reverter (padrão `1`).
2. Executa o `down` das N últimas e remove o registro em `schema_migrations`.

**status**

Lista cada arquivo como `[OK]` ou `[PENDENTE]`.

### Por que `openFake`?

No MySQL, DDL (`CREATE` / `ALTER` / `DROP`) faz commit implícito e encerra a transaction do PDO.  
O runner abre a conexão sem `beginTransaction`. Rollback de migration = SQL no `down`, não `PDO->rollBack()`.

### Tabela de controle

```text
schema_migrations
├── version      — ex: 001
├── migration    — ex: 001_clientes.php
└── executed_at
```

### Fluxo típico

```bash
# 1. Criar 002_xxx.php com up/down
# 2. Aplicar
docker compose exec app php database/MigrationRunner.php migrate

# 3. Conferir
docker compose exec app php database/MigrationRunner.php status

# 4. Desfazer se necessário
docker compose exec app php database/MigrationRunner.php rollback
docker compose exec app php database/MigrationRunner.php rollback 2
```

O model Adianti (`app/model/...`) só mapeia a tabela; quem cria/altera o schema é a migration.

---

## Seeders

Populam o banco com dados iniciais/demo. Rode **depois** das migrations.

### Estrutura

```text
database/
├── SeedRunner.php
└── seeders/
    └── 001_clientes.php
```

| Item | Descrição |
|------|-----------|
| Conexão | `app/config/database.php` |
| Controle | Tabela `schema_seeders` (criada automaticamente) |
| Pasta | `database/seeders/*.php` |

### Comandos

```bash
# Executar seeders pendentes
docker compose exec app php database/SeedRunner.php seed

# Limpar controle e executar todos de novo
docker compose exec app php database/SeedRunner.php fresh

# Status
docker compose exec app php database/SeedRunner.php status
```

### Criar um novo seeder

1. Crie `database/seeders/002_nome.php` (prefixo numérico crescente).
2. Retorne um array com `run`:

```php
<?php

return [
    'run' => function () {
        $pdo = TTransaction::get();

        $check = $pdo->prepare("SELECT COUNT(*) FROM exemplo WHERE email = ?");
        $insert = $pdo->prepare("INSERT INTO exemplo (name, email) VALUES (?, ?)");

        $check->execute(['demo@example.com']);

        if ((int) $check->fetchColumn() === 0) {
            $insert->execute(['Demo', 'demo@example.com']);
        }
    },
];
```

### Regras

- Use sempre `TTransaction::get()`.
- Prefira seeders **idempotentes** (não duplicar se o registro já existe) — importante no `fresh`.
- O prefixo (`001`, `002`...) define a ordem.
- Cada versão roda uma vez até usar `fresh` (registrada em `schema_seeders`).

### Como funciona

**seed**

1. Abre conexão com `openFake('database')`.
2. Garante `schema_seeders`.
3. Executa o `run` de cada arquivo ainda não registrado.

**fresh**

1. Limpa a tabela `schema_seeders`.
2. Executa todos os seeders novamente (como pendentes).

**status**

Lista cada arquivo como `[OK]` ou `[PENDENTE]`.

### Tabela de controle

```text
schema_seeders
├── version      — ex: 001
├── seeder       — ex: 001_clientes.php
└── executed_at
```

### Fluxo típico (banco novo)

```bash
docker compose up -d --build
docker compose exec app php database/MigrationRunner.php migrate
docker compose exec app php database/SeedRunner.php seed
docker compose exec app php database/SeedRunner.php status
```

### Exemplo existente

`database/seeders/001_clientes.php` insere 3 clientes de demonstração:

| name | email |
|------|-------|
| Cliente Demo | demo@example.com |
| Ana Silva | ana.silva@example.com |
| Bruno Costa | bruno.costa@example.com |

Se o e-mail já existir, o registro é ignorado (idempotente).

---

## Comandos úteis

```bash
# Logs da aplicação
docker compose logs -f app

# Shell no container PHP
docker compose exec app bash

# Composer dentro do container
docker compose exec app composer install

# MySQL via CLI
docker compose exec db mysql -uadianti -padianti adianti

# Migrations
docker compose exec app php database/MigrationRunner.php migrate
docker compose exec app php database/MigrationRunner.php rollback
docker compose exec app php database/MigrationRunner.php rollback 2
docker compose exec app php database/MigrationRunner.php status

# Seeders
docker compose exec app php database/SeedRunner.php seed
docker compose exec app php database/SeedRunner.php fresh
docker compose exec app php database/SeedRunner.php status
```

---

## Estrutura relevante

```text
app/
├── config/          # application.php, database.php, sample.php
├── control/         # pages / forms
├── model/           # TRecord
├── service/         # regras de aplicação
└── lib/             # helpers, validators da app
database/
├── MigrationRunner.php
├── SeedRunner.php
├── migrations/
└── seeders/
docker/
lib/adianti/         # core do framework
Dockerfile
docker-compose.yml
.env
```

---

## Troubleshooting

| Problema | O que fazer |
|----------|-------------|
| Porta em uso | Altere `APP_PORT`, `MYSQL_PORT` ou `PHPMYADMIN_PORT` no `.env` |
| `service "php" is not running` | O serviço se chama `app`: `docker compose exec app ...` |
| Banco vazio após recreate | `docker compose down -v` apaga o volume; rode `migrate` de novo |
| Init SQL não rodou | Só executa na 1ª criação do volume; use migrations depois disso |
| Tabela não existe | `docker compose exec app php database/MigrationRunner.php migrate` |
| Precisa de dados demo | `docker compose exec app php database/SeedRunner.php seed` |
| Seeder não roda de novo | Use `fresh` ou torne o seeder idempotente |
