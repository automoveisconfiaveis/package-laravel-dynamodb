<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Connector;

use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Connection\DynamoDbConnection;
use Aws\DynamoDb\DynamoDbClient;

class DynamoDbConnector
{
    /**
     * Estabelecer conexão com DynamoDB.
     */
    public function connect(array $config): DynamoDbConnection
    {
        $client = $this->createDynamoDbClient($config);

        return new DynamoDbConnection($client, $config);
    }

    /**
     * Criar instância do DynamoDbClient.
     */
    protected function createDynamoDbClient(array $config): DynamoDbClient
    {
        $clientConfig = [
            'region' => $config['region'] ?? 'us-east-1',
            'version' => 'latest',
        ];

        // Endpoint para DynamoDB Local (se fornecido)
        if (! empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];

            // DynamoDB Local: sempre usar credenciais válidas
            // Alguns DynamoDB Local exigem credenciais no formato AWS válido
            $clientConfig['credentials'] = [
                'key' => $config['key'] ?: 'AKIAIOSFODNN7EXAMPLE',
                'secret' => $config['secret'] ?: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            ];

            // Desabilitar SSL verification para DynamoDB Local (HTTP)
            $clientConfig['http'] = [
                'verify' => false,
            ];
        } elseif (! empty($config['key']) && ! empty($config['secret'])) {
            // Credenciais AWS (produção)
            $clientConfig['credentials'] = [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ];
        }

        return new DynamoDbClient($clientConfig);
    }
}
