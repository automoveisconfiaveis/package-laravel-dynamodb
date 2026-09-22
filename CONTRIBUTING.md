# Contribuindo

Obrigado por contribuir com o `automoveisconfiaveis/laravel-dynamodb`. Este documento descreve
o padrão de trabalho do projeto.

## Ambiente

```bash
git clone https://github.com/automoveisconfiaveis/package-laravel-dynamodb.git
cd package-laravel-dynamodb
composer install
```

Requisitos: PHP `^8.4`.

## Verificações locais

Antes de abrir um Pull Request, rode:

```bash
composer check
```

que executa, em ordem:

| Comando            | Ferramenta      | O que faz                                  |
| ------------------ | --------------- | ------------------------------------------ |
| `composer lint`    | Laravel Pint    | Confere o code style (PSR-12 + preset Laravel). |
| `composer analyse` | PHPStan/Larastan | Análise estática.                          |
| `composer test`    | PHPUnit         | Testes unitários e de integração.          |

Para formatar automaticamente: `vendor/bin/pint`.

## Padrão de commits — Conventional Commits

As mensagens seguem [Conventional Commits](https://www.conventionalcommits.org/):

```
<tipo>(<escopo opcional>): <descrição no imperativo>
```

Tipos usados:

| Tipo       | Uso                                               | Efeito no SemVer      |
| ---------- | ------------------------------------------------- | --------------------- |
| `feat`     | Nova funcionalidade                               | MINOR                 |
| `fix`      | Correção de bug                                   | PATCH                 |
| `docs`     | Só documentação                                   | —                     |
| `test`     | Só testes                                         | —                     |
| `refactor` | Refactor sem mudar comportamento                  | —                     |
| `perf`     | Melhoria de performance                           | PATCH                 |
| `chore`    | Manutenção (deps, build)                          | —                     |
| `ci`       | Configuração de CI                                | —                     |

Exemplos:

```text
feat: adiciona suporte a batch write
fix: corrige conversão de atributos DynamoDB
docs: adiciona exemplo de configuração
test: adiciona teste para putItem
refactor: reorganiza DynamoDbConnection
```

### Breaking changes

Sinalize incompatibilidade com `!` após o tipo **ou** com um rodapé `BREAKING CHANGE:`.
As duas formas resultam em bump **MAJOR**:

```text
feat!: altera a interface do DynamoDB client
```

```text
feat: nova assinatura do client

BREAKING CHANGE: getItem() passa a lançar DynamoDbException em vez de retornar null.
```

Lembre que **subir o piso mínimo de PHP ou Laravel** também é breaking change.

## Pull Requests

1. Crie uma branch a partir da `main`: `feat/...`, `fix/...`, `docs/...`.
2. Garanta `composer check` verde e atualize o `CHANGELOG.md` (seção `Unreleased`).
3. Abra o PR com título no padrão Conventional Commits.
4. O merge só ocorre com a CI verde e ao menos uma revisão.

## Releases

Os releases são publicados por tag git anotada a partir da `main`:

```bash
git tag -a v1.1.0 -m "Release v1.1.0"
git push origin v1.1.0
```

O Packagist publica automaticamente pela tag (webhook). Veja o `CHANGELOG.md`.
