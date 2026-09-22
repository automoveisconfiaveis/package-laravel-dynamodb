<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests;

use AutomoveisConfiaveis\LaravelDynamoDb\DynamoDbServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base para testes de integração: inicializa um app Laravel mínimo
 * (via Orchestra Testbench) com o pacote registrado.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DynamoDbServiceProvider::class,
        ];
    }
}
