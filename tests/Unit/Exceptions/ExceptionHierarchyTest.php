<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Tests\Unit\Exceptions;

use AutomoveisConfiaveis\LaravelDynamoDb\Exceptions\DynamoDbException;
use AutomoveisConfiaveis\LaravelDynamoDb\Exceptions\InvalidQueryException;
use AutomoveisConfiaveis\LaravelDynamoDb\Exceptions\MissingKeyException;
use AutomoveisConfiaveis\LaravelDynamoDb\Exceptions\UnsupportedOperationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * As exceções do pacote devem descender de DynamoDbException, que por sua vez
 * estende \RuntimeException — assim `catch (\RuntimeException)` legado continua válido.
 */
class ExceptionHierarchyTest extends TestCase
{
    public function test_todas_estendem_a_base_e_runtime_exception(): void
    {
        foreach ([MissingKeyException::class, UnsupportedOperationException::class, InvalidQueryException::class] as $class) {
            $e = new $class('x');

            $this->assertInstanceOf(DynamoDbException::class, $e);
            $this->assertInstanceOf(RuntimeException::class, $e);
        }
    }

    public function test_base_estende_runtime_exception(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new DynamoDbException('x'));
    }
}
