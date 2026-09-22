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

### 3. PHP 8.4

O piso mínimo passou a ser **PHP 8.4**. Projetos em PHP 8.2/8.3 devem permanecer na linha
`0.2.x` até poderem atualizar o runtime.

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
