<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Unit\Query;

use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Query\Grammar;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Cobre o compileInsert: campos vazios (null ou string vazia) devem ser
 * removidos do Item, pois o DynamoDB não aceita string vazia em atributo
 * que é chave de índice (GSI/LSI). Mesmo contrato do compileUpdate.
 */
class GrammarInsertTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        // compileInsert não usa a connection; instanciamos sem construtor para
        // manter o teste unitário e independente do bootstrap do Laravel.
        $this->grammar = (new ReflectionClass(Grammar::class))->newInstanceWithoutConstructor();
    }

    private function builder(): Builder
    {
        $query = (new ReflectionClass(Builder::class))->newInstanceWithoutConstructor();
        $query->from = 'user_notifications';

        return $query;
    }

    public function test_campos_null_e_string_vazia_sao_removidos_do_item(): void
    {
        $item = $this->grammar->compileInsert($this->builder(), [
            'id' => 'abc-123',
            'user_id' => '30975',
            'url' => 'https://app.autoconf.com.br/',
            'url2' => '',
            'link_text' => null,
        ])['params']['Item'];

        $this->assertSame('abc-123', $item['id']);
        $this->assertSame('https://app.autoconf.com.br/', $item['url']);
        $this->assertArrayNotHasKey('url2', $item, 'string vazia em sort key de GSI nao pode ir no payload');
        $this->assertArrayNotHasKey('link_text', $item);
    }

    public function test_zero_e_false_sao_preservados(): void
    {
        $item = $this->grammar->compileInsert($this->builder(), [
            'id' => 'abc-123',
            'notification_id' => 0,
            'contador' => '0',
            'ativo' => false,
        ])['params']['Item'];

        $this->assertArrayHasKey('notification_id', $item);
        $this->assertArrayHasKey('contador', $item);
        $this->assertArrayHasKey('ativo', $item);
        $this->assertSame(0, $item['notification_id']);
        $this->assertSame('0', $item['contador']);
        $this->assertFalse($item['ativo']);
    }

    public function test_batch_insert_tambem_remove_campos_vazios(): void
    {
        $compiled = $this->grammar->compileInsert($this->builder(), [
            ['id' => 'a', 'url2' => ''],
            ['id' => 'b', 'url2' => 'https://app.autoconf.com.br/x'],
        ]);

        $this->assertCount(2, $compiled);
        $this->assertArrayNotHasKey('url2', $compiled[0]['params']['Item']);
        $this->assertSame('https://app.autoconf.com.br/x', $compiled[1]['params']['Item']['url2']);
    }
}
