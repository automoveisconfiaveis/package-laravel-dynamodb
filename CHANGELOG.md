# Changelog

Todas as mudanças relevantes deste pacote são documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o projeto segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added
- Hierarquia de exceções própria em `AutomoveisConfiaveis\LaravelDynamoDb\Exceptions`
  (`DynamoDbException`, `MissingKeyException`, `UnsupportedOperationException`,
  `InvalidQueryException`).
- `CHANGELOG.md`, `CONTRIBUTING.md` e `UPGRADING.md`.
- `.gitattributes` (mantém testes, docs e CI fora do pacote distribuído) e `.editorconfig`.
- Configuração de qualidade: `pint.json` (Pint) e `phpstan.neon` (Larastan).
- Base de testes com Orchestra Testbench e scripts do Composer (`lint`, `analyse`, `test`, `check`).
- Testes de integração contra DynamoDB Local (CRUD e `simplePaginate` por cursor); pulam
  automaticamente quando não há um DynamoDB Local acessível.
- Workflow de CI no GitHub Actions.

### Changed
- Piso mínimo de PHP mantido em `^8.2` (a v1.0.0 havia subido para `^8.4` sem que o
  código exigisse recursos de 8.3/8.4; revertido na 1.0.1).
- Versão passa a ser controlada exclusivamente por tag git; campo `version` removido do
  `composer.json`.
- Vendor/namespace renomeados de `joaquim` para `automoveisconfiaveis`
  (`AutomoveisConfiaveis\LaravelDynamoDb`).
- Configuração consolidada em um único arquivo `config/database-dynamodb.php`
  (o `config/dynamodb.php` publicado em apps continua sendo aceito por compatibilidade).
- README reescrito seguindo estrutura de referência (requisitos, versões, exemplos,
  exceções, versionamento e upgrade).

### Removed
- Arquivo de configuração duplicado `config/dynamodb.php` do pacote.
- Documentos internos movidos para `docs/`.

### Fixed
- `Model::find()` (e qualquer `where` com coluna qualificada `tabela.coluna`, como
  as geradas por `getQualifiedKeyName()`) agora resolve a partition key corretamente e
  usa `GetItem`, em vez de degradar para `Scan` e retornar vazio.
- `PutItem` passa a remover do Item os campos vazios (`null` **ou** string vazia `''`),
  alinhando o `compileInsert` ao contrato que o `compileUpdate` já seguia. Antes o insert
  removia apenas `null`, e uma string vazia gravada em atributo que é chave de índice
  (GSI/LSI) fazia o DynamoDB devolver `ValidationException` ("The AttributeValue for a key
  attribute cannot contain an empty string value"). O atributo passa a simplesmente não ser
  gravado — o item não aparece nesses índices (índice esparso). `0`, `'0'` e `false`
  continuam preservados, em ambos os caminhos.
- `get()` (Query/Scan sem `Limit`) agora percorre todas as páginas até o DynamoDB não
  devolver mais `LastEvaluatedKey`. Antes a paginação automática parava ao acumular 1000
  itens; como a 1ª página (1MB) pode trazer milhares de itens sozinha, as páginas seguintes
  eram descartadas sem aviso. Com `Limit`, continua parando ao atingir o limite.

## [0.2.6] - 2026-09-22

### Fixed
- Paginação por cursor (`simplePaginate`) roteando pelo builder do pacote e hidratando os
  itens em instâncias do Model; correção do skip da página seguinte.
- Remoção de campos vazios (`null` ou `''`) do payload no `UpdateItem`.
- Montagem da `Key` de update/delete pela partition + sort key reais.
- Montagem da `Key` do `GetItem` pela partition key real.
- Geração do `id` (partition key) no `performInsert` e omissão de atributos nulos no `PutItem`.

## [0.2.0] - 2026-09-22

### Added
- Suporte a condições `IN` e `NOT NULL`.

### Changed
- Diversos refactors no `Model` e no `Grammar`.

## [0.1.0]

### Added
- Versão inicial: driver DynamoDB para Laravel com suporte a Eloquent, resolução automática
  de índices (GSI/LSI), `KeyConditionExpression`, paginação e DynamoDB Local.

[Unreleased]: https://github.com/automoveisconfiaveis/package-laravel-dynamodb/compare/v0.2.6...HEAD
[0.2.6]: https://github.com/automoveisconfiaveis/package-laravel-dynamodb/compare/v0.2.0...v0.2.6
[0.2.0]: https://github.com/automoveisconfiaveis/package-laravel-dynamodb/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/automoveisconfiaveis/package-laravel-dynamodb/releases/tag/v0.1.0
