<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Exceptions;

/**
 * Lançada quando a query recebida está mal formada (insert/update/delete)
 * ou quando não há dados válidos para a operação.
 */
class InvalidQueryException extends DynamoDbException {}
