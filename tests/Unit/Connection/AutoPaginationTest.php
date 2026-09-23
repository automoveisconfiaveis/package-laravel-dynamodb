<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Unit\Connection;

use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Connection\DynamoDbConnection;
use AutomoveisConfiaveis\LaravelDynamoDb\Tests\TestCase;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use Mockery;

/**
 * Regressão: o get() (Query/Scan sem Limit) parava a paginação automática
 * assim que acumulava 1000 itens. Como a 1ª página do DynamoDB (1MB) pode
 * sozinha trazer milhares de itens, as páginas seguintes eram descartadas
 * em silêncio — ex.: busca de contatos no autoconf sem os mais recentes.
 */
class AutoPaginationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return array<int, array<string, array<string, string>>>
     */
    private function items(int $from, int $count): array
    {
        return array_map(
            fn (int $i): array => ['id' => ['S' => "id-{$i}"]],
            range($from, $from + $count - 1)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function compiled(string $operation, array $extraParams = []): array
    {
        $params = ['TableName' => 'users'];

        if ($operation === 'Query') {
            $params += [
                'IndexName' => 'revenda_id_updated_at_index',
                'KeyConditionExpression' => '#revenda_id = :revenda_id',
                'ExpressionAttributeNames' => ['#revenda_id' => 'revenda_id'],
                'ExpressionAttributeValues' => [':revenda_id' => 7],
            ];
        }

        return ['operation' => $operation, 'params' => $extraParams + $params];
    }

    public function test_query_sem_limit_percorre_todas_as_paginas_mesmo_passando_de_1000_itens(): void
    {
        $client = Mockery::mock(DynamoDbClient::class);
        $client->shouldReceive('query')->times(3)->andReturn(
            new Result(['Items' => $this->items(1, 1500), 'LastEvaluatedKey' => ['id' => ['S' => 'id-1500']]]),
            new Result(['Items' => $this->items(1501, 1500), 'LastEvaluatedKey' => ['id' => ['S' => 'id-3000']]]),
            new Result(['Items' => $this->items(3001, 200)]),
        );

        $rows = (new DynamoDbConnection($client, []))->select($this->compiled('Query'));

        $this->assertCount(3200, $rows);
        $this->assertSame('id-3200', end($rows)->id);
    }

    public function test_scan_sem_limit_percorre_todas_as_paginas_mesmo_passando_de_1000_itens(): void
    {
        $client = Mockery::mock(DynamoDbClient::class);
        $client->shouldReceive('scan')->twice()->andReturn(
            new Result(['Items' => $this->items(1, 1200), 'LastEvaluatedKey' => ['id' => ['S' => 'id-1200']]]),
            new Result(['Items' => $this->items(1201, 300)]),
        );

        $rows = (new DynamoDbConnection($client, []))->select($this->compiled('Scan'));

        $this->assertCount(1500, $rows);
    }

    public function test_query_com_limit_para_ao_atingir_o_limit(): void
    {
        $client = Mockery::mock(DynamoDbClient::class);
        $client->shouldReceive('query')->twice()->andReturn(
            new Result(['Items' => $this->items(1, 60), 'LastEvaluatedKey' => ['id' => ['S' => 'id-60']]]),
            new Result(['Items' => $this->items(61, 40), 'LastEvaluatedKey' => ['id' => ['S' => 'id-100']]]),
        );

        $rows = (new DynamoDbConnection($client, []))->select($this->compiled('Query', ['Limit' => 100]));

        $this->assertCount(100, $rows);
    }
}
