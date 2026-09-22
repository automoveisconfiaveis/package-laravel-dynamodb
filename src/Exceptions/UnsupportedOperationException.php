<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Exceptions;

/**
 * Lançada quando se tenta uma operação não suportada pelo DynamoDB
 * (por exemplo, SQL cru ou uma operação desconhecida).
 */
class UnsupportedOperationException extends DynamoDbException {}
