<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Exceptions;

/**
 * Lançada quando um model é persistido sem valor de Partition Key.
 */
class MissingKeyException extends DynamoDbException {}
