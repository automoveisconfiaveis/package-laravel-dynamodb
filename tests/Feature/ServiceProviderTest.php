<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Feature;

use AutomoveisConfiaveis\LaravelDynamoDb\Tests\TestCase;

/**
 * Garante que o pacote boota dentro de um app Laravel real (via Testbench):
 * o ServiceProvider registra a config e mescla a conexão 'dynamodb'.
 */
class ServiceProviderTest extends TestCase
{
    public function test_config_padrao_do_pacote_e_mesclada(): void
    {
        $this->assertNotNull(config('dynamodb'));
        $this->assertIsArray(config('dynamodb.connections'));
        $this->assertArrayHasKey('aws', config('dynamodb.connections'));
        $this->assertArrayHasKey('local', config('dynamodb.connections'));
    }

    public function test_conexao_dynamodb_fica_disponivel_no_database(): void
    {
        $connections = config('database.connections');

        $this->assertArrayHasKey('dynamodb', $connections);
        $this->assertSame('dynamodb', $connections['dynamodb']['driver']);
    }
}
