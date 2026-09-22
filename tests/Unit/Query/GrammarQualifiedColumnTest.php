<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Unit\Query;

use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Query\Grammar;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * O Eloquent qualifica colunas de chave como "tabela.coluna" (find() usa
 * getQualifiedKeyName()). O Grammar precisa remover o prefixo da tabela das
 * cláusulas where, senão a partition key não casa e a query degrada para Scan.
 */
class GrammarQualifiedColumnTest extends TestCase
{
    private function grammar(): Grammar
    {
        return (new ReflectionClass(Grammar::class))->newInstanceWithoutConstructor();
    }

    private function builder(string $from, array $wheres): Builder
    {
        $query = (new ReflectionClass(Builder::class))->newInstanceWithoutConstructor();
        $query->from = $from;
        $query->wheres = $wheres;

        return $query;
    }

    private function strip(Grammar $grammar, Builder $query): void
    {
        $ref = new ReflectionClass(Grammar::class);
        $method = $ref->getMethod('stripTableQualifierFromWheres');
        $method->setAccessible(true);
        $method->invoke($grammar, $query);
    }

    public function test_remove_o_prefixo_da_tabela_das_colunas(): void
    {
        $query = $this->builder('users', [
            ['type' => 'Basic', 'column' => 'users.id', 'operator' => '=', 'value' => 'u1'],
            ['type' => 'Basic', 'column' => 'status', 'operator' => '=', 'value' => 'ativo'],
        ]);

        $this->strip($this->grammar(), $query);

        $this->assertSame('id', $query->wheres[0]['column'], 'o prefixo "users." deve ser removido');
        $this->assertSame('status', $query->wheres[1]['column'], 'coluna sem prefixo permanece intacta');
    }

    public function test_nao_altera_colunas_de_outra_tabela(): void
    {
        $query = $this->builder('users', [
            ['type' => 'Basic', 'column' => 'outra.id', 'operator' => '=', 'value' => 'x'],
        ]);

        $this->strip($this->grammar(), $query);

        $this->assertSame('outra.id', $query->wheres[0]['column'], 'só o prefixo da própria tabela é removido');
    }
}
