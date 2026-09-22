# Laravel DynamoDB Driver

Driver para Amazon DynamoDB no Laravel, com suporte a Eloquent, resolução automática de
índices (GSI/LSI), ordenação nativa pela Sort Key e paginação por cursor.

[![Tests](https://github.com/automoveisconfiaveis/package-laravel-dynamodb/actions/workflows/tests.yml/badge.svg)](https://github.com/automoveisconfiaveis/package-laravel-dynamodb/actions/workflows/tests.yml)

## Índice

1. [O que o pacote faz](#1-o-que-o-pacote-faz)
2. [Requisitos](#2-requisitos)
3. [Versões de PHP suportadas](#3-versões-de-php-suportadas)
4. [Versões de Laravel suportadas](#4-versões-de-laravel-suportadas)
5. [Instalação](#5-instalação)
6. [Configuração](#6-configuração)
7. [Exemplos básicos](#7-exemplos-básicos)
8. [Exemplos avançados](#8-exemplos-avançados)
9. [Tratamento de erros / exceptions](#9-tratamento-de-erros--exceptions)
10. [Testes](#10-testes)
11. [Versionamento](#11-versionamento)
12. [Upgrade entre versões](#12-upgrade-entre-versões)
13. [Contribuição](#13-contribuição)

---

## 1. O que o pacote faz

Registra um driver de banco `dynamodb` no Laravel e uma classe base `Model` que estende o
Eloquent. Com isso você usa DynamoDB com a API do Eloquent que já conhece, enquanto o pacote
cuida do específico do DynamoDB por baixo:

- **Eloquent ORM** — modelos, `create`/`find`/`where`/`save`/`delete`, casts.
- **Resolução automática de índices** — detecta o melhor GSI/LSI a partir dos `where` e usa
  `Query` com `KeyConditionExpression` em vez de `Scan`/`FilterExpression` quando possível.
- **OrderBy nativo** — ordenação pela Sort Key do índice escolhido.
- **Paginação por cursor** — `simplePaginate()` baseado em `LastEvaluatedKey`.
- **ProjectionExpression** — `select()` para trazer só os atributos necessários.
- **Cache de metadados** — `DescribeTable` cacheado por tabela.
- **DynamoDB Local** — suporte completo para ambiente local.

## 2. Requisitos

| Dependência          | Versão              |
| -------------------- | ------------------- |
| PHP                  | `^8.2`              |
| Laravel (illuminate) | `^11.0` \| `^12.0`  |
| AWS SDK for PHP      | `^3.322.9`          |

## 3. Versões de PHP suportadas

`PHP 8.2`, `8.3` e `8.4`. Subir ou baixar o piso mínimo de PHP é tratado como **breaking change**
(ver [Versionamento](#11-versionamento)).

## 4. Versões de Laravel suportadas

`Laravel 11.x` e `Laravel 12.x`. Ver a tabela de compatibilidade em
[Versionamento](#11-versionamento).

## 5. Instalação

```bash
composer require automoveisconfiaveis/laravel-dynamodb
```

O `DynamoDbServiceProvider` é descoberto automaticamente (package auto-discovery).

Publique a configuração:

```bash
php artisan vendor:publish --tag=dynamodb-config
```

Isso cria `config/database-dynamodb.php`.

## 6. Configuração

### Arquivo `config/database-dynamodb.php`

```php
return [
    // Conexão padrão. Em produção, 'aws'; em desenvolvimento, defina DYNAMODB_CONNECTION=local.
    'default' => env('DYNAMODB_CONNECTION', 'aws'),

    'on_connection' => env('DYNAMODB_CONNECTION', 'aws'),

    'connections' => [
        'aws' => [
            'driver'   => 'dynamodb',
            'database' => env('DYNAMODB_TABLE', 'default'),
            'table'    => env('DYNAMODB_TABLE', 'default'),
            'prefix'   => '',
            'region'   => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'key'      => env('AWS_ACCESS_KEY_ID'),
            'secret'   => env('AWS_SECRET_ACCESS_KEY'),
        ],

        'local' => [
            'driver'   => 'dynamodb',
            'database' => env('DYNAMODB_TABLE', 'default'),
            'table'    => env('DYNAMODB_TABLE', 'default'),
            'prefix'   => '',
            'region'   => env('DYNAMODB_REGION', 'us-east-1'),
            'endpoint' => env('DYNAMODB_ENDPOINT', 'http://localhost:8000'),
            'key'      => env('DYNAMODB_ACCESS_KEY_ID', 'AKIAIOSFODNN7EXAMPLE'),
            'secret'   => env('DYNAMODB_SECRET_ACCESS_KEY', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'),
        ],
    ],
];
```

As conexões declaradas aqui são mescladas em `config/database.php` no boot, e a conexão
padrão também fica disponível com o nome `dynamodb`.

### Variáveis de ambiente

```env
# Seleciona a conexão (aws ou local)
DYNAMODB_CONNECTION=local

# DynamoDB Local
DYNAMODB_ENDPOINT=http://localhost:8000
DYNAMODB_REGION=us-east-1
DYNAMODB_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
DYNAMODB_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY

# AWS DynamoDB
# DYNAMODB_CONNECTION=aws
# AWS_DEFAULT_REGION=us-east-1
# AWS_ACCESS_KEY_ID=your-key
# AWS_SECRET_ACCESS_KEY=your-secret
```

## 7. Exemplos básicos

### Definindo um Model

```php
<?php

namespace App\Models;

use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model;

class Cliente extends Model
{
    protected $connection = 'dynamodb';
    protected $table = 'clientes';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    // Partition Key (obrigatória)
    protected $partitionKey = 'id';

    // Sort Key (opcional)
    protected $sortKey = null;

    // Global Secondary Indexes
    protected $gsiIndexes = [
        'email-index' => [
            'partition_key'   => 'email',
            'sort_key'        => null,
            'projection_type' => 'ALL',
        ],
        'status-nome-index' => [
            'partition_key'   => 'status',
            'sort_key'        => 'nome',
            'projection_type' => 'ALL',
        ],
    ];

    protected $fillable = ['id', 'nome', 'email', 'status'];
}
```

### Operações CRUD

```php
// Criar
$cliente = Cliente::create([
    'id'     => '123',
    'nome'   => 'João Silva',
    'email'  => 'joao@example.com',
    'status' => 'ativo',
]);

// Buscar por Partition Key (GetItem)
$cliente = Cliente::find('123');

// Buscar usando um índice GSI (Query)
$cliente = Cliente::where('email', 'joao@example.com')->first();

// Filtrar + ordenar pela Sort Key do índice
$clientes = Cliente::where('status', 'ativo')
    ->orderBy('nome', 'asc')
    ->get();

// Atualizar
$cliente->nome = 'João Santos';
$cliente->save();

// Remover
$cliente->delete();
```

### Campos vazios

Em **insert e update**, campos com valor `null` ou string vazia (`''`) são removidos do
payload antes de chegar ao DynamoDB — o atributo simplesmente não é gravado.

```php
Cliente::create([
    'id'    => '123',
    'nome'  => 'João Silva',
    'email' => '',       // nao vai no PutItem: o item nao entra na GSI de email
    'fone'  => null,     // idem
]);
```

O motivo é que o DynamoDB rejeita string vazia em atributo que é chave de índice
(GSI/LSI), devolvendo `ValidationException`. Omitir o atributo produz um **índice
esparso**: o item existe na tabela, mas fora daquele índice — que é o comportamento
desejado para campos opcionais.

Valores "falsy" que **não** são vazios continuam sendo gravados normalmente: `0`, `'0'`
e `false`. A checagem é estrita (`=== null || === ''`), não `empty()`.

> Consequência no update: como `null` e `''` significam "não alterar", não é possível
> **limpar** um atributo já gravado através de um update.

## 8. Exemplos avançados

### Resolução automática de índices

```php
// Usa 'status-nome-index' automaticamente por causa do where('status', ...)
$clientes = Cliente::where('status', 'ativo')
    ->orderBy('nome', 'asc')
    ->get();
```

### OrderBy

`orderBy` funciona nativamente quando o campo é a **Sort Key do índice usado** na query.

```php
// ✅ 'nome' é Sort Key de 'status-nome-index'
Cliente::where('status', 'ativo')->orderBy('nome', 'desc')->get();

// ⚠️ 'created_at' não é Sort Key deste índice — exigiria outro índice
```

### Paginação por cursor

```php
$clientes = Cliente::where('status', 'ativo')->simplePaginate(20);
```

### Seleção de atributos (ProjectionExpression)

```php
Cliente::select(['id', 'nome', 'email'])->where('status', 'ativo')->get();
```

### Count

```php
$total = Cliente::where('status', 'ativo')->count();
```

> **Notas do DynamoDB:** `orderBy` só funciona com a Sort Key do índice usado; prefira sempre
> `Query` (com índice) a `Scan`; para contagens grandes considere cache.

## 9. Tratamento de erros / exceptions

Todas as exceções do pacote ficam no namespace
`AutomoveisConfiaveis\LaravelDynamoDb\Exceptions` e estendem `DynamoDbException`
(que por sua vez estende `\RuntimeException`). Assim você pode capturar de forma específica
ou genérica:

```php
use AutomoveisConfiaveis\LaravelDynamoDb\Exceptions\DynamoDbException;

try {
    $cliente->save();
} catch (DynamoDbException $e) {
    // qualquer erro originado pelo driver DynamoDB
    report($e);
}
```

| Exception                       | Quando ocorre                                              |
| ------------------------------- | ---------------------------------------------------------- |
| `DynamoDbException`             | Base de todas as exceções do pacote.                       |
| `MissingKeyException`           | Model salvo sem valor de Partition Key.                    |
| `UnsupportedOperationException` | Operação não suportada pelo DynamoDB (ex.: SQL cru).       |
| `InvalidQueryException`         | Query mal formada (insert/update/delete) ou item vazio.    |

> Compatibilidade: como `DynamoDbException extends \RuntimeException`, código que já capturava
> `\RuntimeException` continua funcionando.

## 10. Testes

```bash
composer install
composer test        # PHPUnit
composer lint        # Laravel Pint (--test, não altera arquivos)
composer analyse     # PHPStan / Larastan
composer check       # roda lint + analyse + test
```

Os testes de integração usam [Orchestra Testbench](https://github.com/orchestral/testbench)
para inicializar um app Laravel mínimo com o pacote registrado.

## 11. Versionamento

O projeto segue [Semantic Versioning](https://semver.org/) e é publicado por **tags git**
(`vX.Y.Z`) — não há campo `version` no `composer.json`.

- **PATCH** (`1.0.0 → 1.0.1`) — correções e mudanças compatíveis, sem alterar a API pública.
- **MINOR** (`1.0.1 → 1.1.0`) — funcionalidade nova mantendo retrocompatibilidade, incluindo
  **adicionar** suporte a uma nova versão de Laravel.
- **MAJOR** (`1.5.2 → 2.0.0`) — quebra de compatibilidade: remoção/renome de método público,
  mudança de assinatura ou comportamento, e **subir o piso mínimo de PHP ou Laravel**.

Histórico em [CHANGELOG.md](CHANGELOG.md).

### Compatibilidade

| Linha do pacote | PHP    | Laravel        |
| --------------- | ------ | -------------- |
| `1.x`           | `^8.2` | `11.x`, `12.x` |

## 12. Upgrade entre versões

Instruções de migração entre versões MAJOR ficam em [UPGRADING.md](UPGRADING.md).

## 13. Contribuição

Contribuições são bem-vindas. Leia o [CONTRIBUTING.md](CONTRIBUTING.md) para o padrão de
commits (Conventional Commits), como rodar as verificações locais e o fluxo de Pull Request.

## Licença

[MIT](LICENSE).
