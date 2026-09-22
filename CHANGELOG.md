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
- **BREAKING**: piso mínimo de PHP passa a ser `^8.4`.
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
