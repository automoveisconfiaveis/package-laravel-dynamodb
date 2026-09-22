<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Exceptions;

use RuntimeException;

/**
 * Exceção base de todas as exceções lançadas pelo driver DynamoDB.
 *
 * Estende RuntimeException para manter compatibilidade com código que já
 * capturava \RuntimeException antes da introdução das exceções tipadas.
 */
class DynamoDbException extends RuntimeException {}
