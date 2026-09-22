<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Unit\Query;

use Illuminate\Database\Query\Builder;
use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Query\Grammar;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Cobre o compileUpdate: campos vazios (null ou string vazia) devem ser
 * removidos do payload, pois o DynamoDB não aceita string vazia em atributo
 * que é chave de índice (GSI/LSI).
 */
class GrammarUpdateTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        // compileUpdate não usa a connection; instanciamos sem construtor para
        // manter o teste unitário e independente do bootstrap do Laravel.
        $this->grammar = (new ReflectionClass(Grammar::class))->newInstanceWithoutConstructor();
    }

    private function builder(array $key = ['id' => 'abc-123']): Builder
    {
        $query = (new ReflectionClass(Builder::class))->newInstanceWithoutConstructor();
        $query->from = 'users';
        $query->wheres = [];

        foreach ($key as $column => $value) {
            $query->wheres[] = [
                'type' => 'Basic',
                'column' => $column,
                'operator' => '=',
                'value' => $value,
            ];
        }

        return $query;
    }

    public function test_campos_null_e_string_vazia_sao_removidos_do_payload(): void
    {
        $params = $this->grammar->compileUpdate($this->builder(), [
            'name' => 'João',
            'email' => '',
            'phone' => null,
        ])['params'];

        $columns = array_values($params['ExpressionAttributeNames']);

        $this->assertContains('name', $columns);
        $this->assertNotContains('email', $columns);
        $this->assertNotContains('phone', $columns);
        $this->assertSame('SET #attr1 = :val1', $params['UpdateExpression']);
    }

    public function test_zero_e_false_sao_preservados(): void
    {
        $params = $this->grammar->compileUpdate($this->builder(), [
            'age' => 0,
            'ativo' => false,
        ])['params'];

        $columns = array_values($params['ExpressionAttributeNames']);
        $values = array_values($params['ExpressionAttributeValues']);

        $this->assertContains('age', $columns);
        $this->assertContains('ativo', $columns);
        $this->assertTrue(in_array(0, $values, true), 'valor 0 deve ser preservado');
        $this->assertTrue(in_array(false, $values, true), 'valor false deve ser preservado');
    }

    public function test_todos_os_campos_vazios_geram_update_expression_vazio(): void
    {
        $params = $this->grammar->compileUpdate($this->builder(), [
            'email' => '',
            'phone' => null,
        ])['params'];

        $this->assertSame('', trim($params['UpdateExpression']));
        $this->assertSame([], $params['ExpressionAttributeNames']);
        $this->assertArrayNotHasKey('ExpressionAttributeValues', $params);
    }
}
