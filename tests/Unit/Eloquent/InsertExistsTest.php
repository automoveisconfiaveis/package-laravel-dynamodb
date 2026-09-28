<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Unit\Eloquent;

use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Connection\DynamoDbConnection;
use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Regressão: o performInsert do pacote não marcava exists=true, então um update()
 * no mesmo objeto logo após o save()/create() retornava false sem chamar UpdateItem.
 */
class InsertExistsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function model(DynamoDbConnection $connection): Model
    {
        $model = new class extends Model
        {
            protected $table = 'messages';

            protected $partitionKey = 'id';

            protected $sortKey = null;

            protected $guarded = [];

            public $timestamps = false;

            public $autoCreateTable = false;

            public static DynamoDbConnection $testConnection;

            // Evita config() (sem app booted no teste unitário puro).
            public function getConnectionName()
            {
                return 'dynamodb';
            }

            public function getConnection()
            {
                return self::$testConnection;
            }
        };
        $model::$testConnection = $connection;

        return $model;
    }

    public function test_save_de_model_novo_marca_exists_e_was_recently_created(): void
    {
        $client = Mockery::mock(DynamoDbClient::class);
        $client->shouldReceive('putItem')->once()->andReturn(new Result([]));

        $model = $this->model(new DynamoDbConnection($client, ['table' => 'messages']));
        $model->fill(['id' => 'msg-1', 'message_id' => 'uuid-do-agente']);

        $this->assertTrue($model->save());
        $this->assertTrue($model->exists);
        $this->assertTrue($model->wasRecentlyCreated);
    }

    public function test_update_logo_apos_o_insert_grava_via_update_item(): void
    {
        $client = Mockery::mock(DynamoDbClient::class);
        $client->shouldReceive('putItem')->once()->andReturn(new Result([]));
        $client->shouldReceive('updateItem')->once()
            ->withArgs(function (array $params): bool {
                return $params['Key'] === ['id' => ['S' => 'msg-1']]
                    && in_array(['S' => 'mid-do-instagram'], $params['ExpressionAttributeValues'] ?? [], true)
                    && in_array(['S' => 'sent'], $params['ExpressionAttributeValues'] ?? [], true);
            })
            ->andReturn(new Result([]));

        $model = $this->model(new DynamoDbConnection($client, ['table' => 'messages']));
        $model->fill(['id' => 'msg-1', 'message_id' => 'uuid-do-agente']);
        $model->save();

        $this->assertTrue($model->update(['message_status' => 'sent', 'message_id' => 'mid-do-instagram']));
    }
}
