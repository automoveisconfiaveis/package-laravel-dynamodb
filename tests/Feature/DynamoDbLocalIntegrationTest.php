<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Feature;

use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model;
use AutomoveisConfiaveis\LaravelDynamoDb\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Integração REAL contra um DynamoDB Local acessível (default http://localhost:8000).
 * Pula automaticamente quando não houver DynamoDB Local no ar, para não quebrar a CI.
 */
class DynamoDbLocalIntegrationTest extends TestCase
{
    private string $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = getenv('DDB_ENDPOINT') ?: 'http://localhost:8000';

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 2, CURLOPT_RETURNTRANSFER => true]);
        curl_exec($ch);
        $reachable = curl_errno($ch) === 0;
        curl_close($ch);

        if (! $reachable) {
            $this->markTestSkipped("DynamoDB Local não acessível em {$this->endpoint}");
        }

        config()->set('database.connections.dynamodb', [
            'driver' => 'dynamodb',
            'database' => 'default',
            'table' => 'default',
            'prefix' => '',
            'region' => 'us-east-1',
            'endpoint' => $this->endpoint,
            'key' => 'fake',
            'secret' => 'fake',
        ]);

        DB::purge('dynamodb');
    }

    /**
     * Garante um schema limpo: dropa a tabela (se existir) para o autoCreateTable
     * recriá-la com a definição atual — evita depender de estado de execuções anteriores.
     */
    private function dropTable(string $table): void
    {
        $client = DB::connection('dynamodb')->getDynamoDbClient();

        try {
            $client->deleteTable(['TableName' => $table]);
            $client->waitUntil('TableNotExists', [
                'TableName' => $table,
                '@waiter' => ['delay' => 1, 'maxAttempts' => 20],
            ]);
        } catch (\Throwable $e) {
            // Tabela não existia — nada a fazer.
        }
    }

    private function model(): Model
    {
        return new class extends Model
        {
            protected $connection = 'dynamodb';

            protected $table = 'itest_users';

            protected $primaryKey = 'id';

            protected $keyType = 'string';

            public $incrementing = false;

            protected $partitionKey = 'id';

            protected $sortKey = null;

            public $autoCreateTable = true;

            public $timestamps = false;

            protected $gsiIndexes = [
                'email-index' => [
                    'partition_key' => 'email',
                    'sort_key' => null,
                    'projection_type' => 'ALL',
                ],
            ];

            protected $fillable = ['id', 'name', 'email', 'status'];
        };
    }

    public function test_crud_completo_contra_dynamodb_local(): void
    {
        $this->dropTable('itest_users');

        $m = $this->model();

        // CREATE — dispara auto-criação da tabela (com o GSI) e o PutItem
        $u = $m->newInstance(['id' => 'u1', 'name' => 'Joao', 'email' => 'joao@x.com', 'status' => 'ativo']);
        $u->save();

        // FIND — GetItem pela partition key
        $found = $m->newQuery()->find('u1');
        $this->assertNotNull($found, 'find() deveria achar o item recém-criado');
        $this->assertSame('Joao', $found->name);

        // QUERY por GSI (email-index)
        $byEmail = $m->newQuery()->where('email', 'joao@x.com')->first();
        $this->assertNotNull($byEmail, 'where(email) deveria usar o GSI e achar o item');
        $this->assertSame('u1', $byEmail->id);

        // UPDATE
        $found->status = 'inativo';
        $found->save();
        $this->assertSame('inativo', $m->newQuery()->find('u1')->status);

        // DELETE
        $m->newQuery()->find('u1')->delete();
        $this->assertNull($m->newQuery()->find('u1'), 'após delete, find() deve retornar null');
    }

    private function sessionModel(): Model
    {
        return new class extends Model
        {
            protected $connection = 'dynamodb';

            protected $table = 'itest_sessions';

            protected $primaryKey = 'id';

            protected $keyType = 'string';

            public $incrementing = false;

            protected $partitionKey = 'id';

            protected $sortKey = null;

            public $autoCreateTable = true;

            public $timestamps = false;

            // A chave numérica do GSI precisa de cast para o createTable defini-la como N.
            protected $casts = ['revenda_id' => 'integer'];

            protected $gsiIndexes = [
                'revenda_id_updated_at_index' => [
                    'partition_key' => 'revenda_id',
                    'sort_key' => 'updated_at',
                    'projection_type' => 'ALL',
                ],
            ];

            protected $fillable = ['id', 'revenda_id', 'updated_at', 'nome'];
        };
    }

    /**
     * Lê o next_cursor anexado ao paginator (propriedade protegida `query`).
     */
    private function nextCursor($paginator): ?string
    {
        $ref = new \ReflectionObject($paginator);
        $prop = $ref->getProperty('query');
        $prop->setAccessible(true);

        return $prop->getValue($paginator)['cursor'] ?? null;
    }

    public function test_simple_paginate_por_cursor_contra_dynamodb_local(): void
    {
        $this->dropTable('itest_sessions');

        $seed = [
            ['id' => 'a', 'revenda_id' => 178, 'updated_at' => '2026-09-22 05:00:00'],
            ['id' => 'b', 'revenda_id' => 178, 'updated_at' => '2026-09-22 04:00:00'],
            ['id' => 'c', 'revenda_id' => 178, 'updated_at' => '2026-09-22 03:00:00'],
            ['id' => 'd', 'revenda_id' => 178, 'updated_at' => '2026-09-22 02:00:00'],
            ['id' => 'e', 'revenda_id' => 178, 'updated_at' => '2026-09-22 01:00:00'],
        ];

        foreach ($seed as $row) {
            $this->sessionModel()->newInstance($row)->save();
        }

        $page = fn (?string $cursor) => $this->sessionModel()->newQuery()
            ->where('revenda_id', 178)
            ->orderBy('updated_at', 'desc')
            ->simplePaginate(2, ['*'], 'cursor', $cursor ?? '');

        // Página 1
        $p1 = $page(null);
        $this->assertCount(2, $p1->items(), 'página deve exibir exatamente perPage itens');
        $this->assertTrue($p1->hasMorePages(), 'ainda há páginas após a 1ª');
        $ids1 = array_map(fn ($m) => $m->id, $p1->items());
        $this->assertSame(['a', 'b'], $ids1, 'ordem desc por updated_at na página 1');
        $this->assertInstanceOf(Model::class, $p1->items()[0], 'itens hidratados em models');

        $c1 = $this->nextCursor($p1);
        $this->assertNotNull($c1, 'deve haver next_cursor na página 1');

        // Página 2 (via cursor)
        $p2 = $page($c1);
        $ids2 = array_map(fn ($m) => $m->id, $p2->items());
        $this->assertSame(['c', 'd'], $ids2, 'página 2 continua de onde a 1 parou (sem repetir/pular)');
        $this->assertTrue($p2->hasMorePages());

        // Página 3 (última)
        $p3 = $page($this->nextCursor($p2));
        $ids3 = array_map(fn ($m) => $m->id, $p3->items());
        $this->assertSame(['e'], $ids3, 'página 3 traz o último item');
        $this->assertFalse($p3->hasMorePages(), 'não há mais páginas após a última');

        // Cobertura total: todos os 5 ids, na ordem, sem repetição e sem pulo
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], array_merge($ids1, $ids2, $ids3));
    }
}
