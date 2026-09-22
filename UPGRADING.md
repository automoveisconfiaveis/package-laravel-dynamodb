# Guia de Upgrade

Este documento reúne as instruções de migração entre versões que contêm mudanças
incompatíveis (MAJOR). Consulte também o [CHANGELOG.md](CHANGELOG.md).

## De `0.2.x` para `1.x`

Esta linha consolida a padronização do pacote. Antes de atualizar, revise os pontos abaixo.

### 1. Nome do pacote (vendor)

O pacote foi renomeado de `joaquim/laravel-dynamodb` para
`automoveisconfiaveis/laravel-dynamodb`.

```diff
- composer require joaquim/laravel-dynamodb
+ composer require automoveisconfiaveis/laravel-dynamodb
```

### 2. Namespace

O namespace PSR-4 mudou de `Joaquim\LaravelDynamoDb` para
`AutomoveisConfiaveis\LaravelDynamoDb`. Atualize os `use` dos seus models:

```diff
- use Joaquim\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model;
+ use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model;
```

Uma busca-e-substituição por `Joaquim\LaravelDynamoDb` → `AutomoveisConfiaveis\LaravelDynamoDb`
no seu projeto resolve.

### 3. PHP

O piso mínimo continua sendo **PHP 8.2** — o mesmo da linha `0.2.x`. Nenhuma ação necessária.

> A v1.0.0 declarou `^8.4` por engano: o código não usa nenhum recurso exclusivo de 8.3/8.4.
> A v1.0.1 corrige o piso para `^8.2`. Se você ficou travado na `0.2.x` por causa disso,
> pode atualizar direto para a `1.0.1`.

### 4. Configuração

O pacote passou a distribuir apenas `config/database-dynamodb.php`. Se o seu app já tem um
`config/dynamodb.php` publicado, ele **continua sendo aceito** por compatibilidade — mas
recomenda-se migrar para `config/database-dynamodb.php`:

```bash
php artisan vendor:publish --tag=dynamodb-config
```

### 5. Exceções

As exceções lançadas pelo driver agora são tipadas em
`AutomoveisConfiaveis\LaravelDynamoDb\Exceptions` e estendem `DynamoDbException`, que por sua
vez estende `\RuntimeException`. Código que capturava `\RuntimeException` continua funcionando;
para tratamento específico, capture `DynamoDbException` ou suas subclasses.

---

> A API pública dos models (métodos e propriedades como `$partitionKey`, `$sortKey`,
> `$gsiIndexes`, `find`, `where`, `simplePaginate`) **não mudou** nesta migração.
