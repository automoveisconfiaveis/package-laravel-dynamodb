<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Unit\Eloquent;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Connection\DynamoDbConnection;
use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent\Builder as DynamoDbEloquentBuilder;
use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Regressões da paginação por cursor (DynamoDB).
 *
 * 1) O Model precisa rotear simplePaginate para o Builder por cursor do pacote,
 *    senão cai no Eloquent base (OFFSET) e toda página repete a primeira.
 * 2) No branch sem FilterExpression, o cursor deve ser a chave do último item
 *    EXIBIDO — não o LastEvaluatedKey (item sentinela perPage+1), que seria
 *    pulado na página seguinte.
 */
class PaginationCursorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function model(): Model
    {
        return new class extends Model
        {
            protected $table = 'sessions';

            protected $partitionKey = 'id';

            protected $sortKey = null;

            public $autoCreateTable = false;

            protected $gsiIndexes = [
                'revenda_id_updated_at_index' => [
                    'partition_key' => 'revenda_id',
                    'sort_key' => 'updated_at',
                ],
            ];

            // Evita config() (sem app booted no teste unitário puro).
            public function getConnectionName()
            {
                return 'dynamodb';
            }
        };
    }

    public function test_new_eloquent_builder_usa_o_builder_por_cursor_do_pacote(): void
    {
        $eloquent = $this->model()->newEloquentBuilder(Mockery::mock(BaseQueryBuilder::class));

        $this->assertInstanceOf(DynamoDbEloquentBuilder::class, $eloquent);
    }

    public function test_cursor_aponta_para_o_ultimo_item_exibido_e_nao_para_o_sentinela(): void
    {
        // perPage=2 → o Builder lê perPage+1=3 itens (o 3º é sentinela p/ detectar próxima página).
        $marshal = fn (string $id, string $ts): array => [
            'id' => ['S' => $id],
            'revenda_id' => ['N' => '178'],
            'updated_at' => ['S' => $ts],
        ];

        $item1 = $marshal('aaa', '2026-09-22 03:00:00');
        $item2 = $marshal('bbb', '2026-09-22 02:00:00'); // último EXIBIDO → cursor esperado
        $item3 = $marshal('ccc', '2026-09-22 01:00:00'); // sentinela (descartado) → NÃO pode virar cursor

        $client = Mockery::mock(DynamoDbClient::class);
        $client->shouldReceive('query')->once()->andReturn(new Result([
            'Items' => [$item1, $item2, $item3],
            'LastEvaluatedKey' => $item3, // chave do sentinela — deve ser ignorada
        ]));

        $connection = new DynamoDbConnection($client, ['table' => 'sessions']);

        $query = $connection->query();
        $query->setModel($this->model());
        $query->from('sessions')->where('revenda_id', 178)->orderBy('updated_at', 'desc');

        // cursor '' (não null) evita depender do helper request() fora do app.
        $paginator = $query->simplePaginate(2, ['*'], 'cursor', '');

        $this->assertCount(2, $paginator->items(), 'a página deve exibir exatamente perPage itens');
        $this->assertTrue($paginator->hasMorePages());

        $cursor = $this->appendedCursor($paginator);
        $this->assertNotNull($cursor, 'deve haver next_cursor quando há mais páginas');

        $decoded = json_decode(base64_decode($cursor), true);

        $this->assertSame('bbb', $decoded['id'] ?? null, 'cursor deve ser a chave do último item EXIBIDO (item2)');
        $this->assertNotSame('ccc', $decoded['id'] ?? null, 'cursor NÃO pode ser o item sentinela (item3) — causaria skip');
        // Chave completa do índice (GSI) — pronta para virar ExclusiveStartKey.
        $this->assertSame('178', (string) ($decoded['revenda_id'] ?? null));
        $this->assertSame('2026-09-22 02:00:00', $decoded['updated_at'] ?? null);
    }

    public function test_eloquent_builder_hidrata_itens_em_instancias_do_model(): void
    {
        $marshal = fn (string $id, string $ts): array => [
            'id' => ['S' => $id],
            'revenda_id' => ['N' => '178'],
            'updated_at' => ['S' => $ts],
        ];

        $client = Mockery::mock(DynamoDbClient::class);
        $client->shouldReceive('query')->once()->andReturn(new Result([
            'Items' => [
                $marshal('aaa', '2026-09-22 03:00:00'),
                $marshal('bbb', '2026-09-22 02:00:00'),
                $marshal('ccc', '2026-09-22 01:00:00'), // sentinela
            ],
        ]));

        $connection = new DynamoDbConnection($client, ['table' => 'sessions']);
        $model = $this->model();

        $query = $connection->query();
        $query->setModel($model);
        $query->from('sessions')->where('revenda_id', 178)->orderBy('updated_at', 'desc');

        $eloquent = $model->newEloquentBuilder($query)->setModel($model);

        $paginator = $eloquent->simplePaginate(2, ['*'], 'cursor', '');

        $this->assertCount(2, $paginator->items());
        $this->assertInstanceOf(Model::class, $paginator->items()[0], 'itens devem ser hidratados em models, não stdClass');
        $this->assertSame('aaa', $paginator->items()[0]->id);
        $this->assertSame('bbb', $paginator->items()[1]->id);
        $this->assertTrue($paginator->items()[0]->exists, 'model hidratado deve marcar exists=true');
    }

    /**
     * Lê o cursor anexado ao paginator (propriedade protegida `query`).
     */
    private function appendedCursor($paginator): ?string
    {
        $ref = new \ReflectionObject($paginator);
        $prop = $ref->getProperty('query');
        $prop->setAccessible(true);
        $query = $prop->getValue($paginator);

        return $query['cursor'] ?? null;
    }
}
