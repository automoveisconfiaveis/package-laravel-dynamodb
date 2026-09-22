<?php

namespace Joaquim\LaravelDynamoDb\Database\DynamoDb\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Joaquim\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model as DynamoDbModel;
use Joaquim\LaravelDynamoDb\Database\DynamoDb\Index\IndexResolver;

class Grammar extends BaseGrammar
{
    /**
     * IndexResolver instance.
     *
     * @var IndexResolver|null
     */
    protected ?IndexResolver $indexResolver = null;

    /**
     * Get or create IndexResolver.
     *
     * @param BaseBuilder|null $query
     * @return IndexResolver|null
     */
    protected function getIndexResolver(?BaseBuilder $query = null): ?IndexResolver
    {
        if (!$query) {
            return null;
        }

        // Tentar obter model do query builder
        $model = $this->getModelFromQuery($query);

        if (!$model) {
            return null;
        }

        if (!$this->indexResolver) {
            $this->indexResolver = new IndexResolver($model);
        } else {
            $this->indexResolver->setModel($model);
        }

        return $this->indexResolver;
    }

    /**
     * Get model instance from query.
     *
     * @param BaseBuilder $query
     * @return DynamoDbModel|null
     */
    protected function getModelFromQuery(BaseBuilder $query): ?DynamoDbModel
    {
        // O DynamoDbBuilder tem método getModel()
        if ($query instanceof Builder && method_exists($query, 'getModel')) {
            $model = $query->getModel();
            if ($model instanceof DynamoDbModel) {
                return $model;
            }
        }

        return null;
    }
    /**
     * Compile a select query into DynamoDB operation.
     *
     * @param BaseBuilder $query
     * @return array
     */
    public function compileSelect(BaseBuilder $query)
    {
        // Determinar qual operação usar (GetItem, Query, Scan)
        $operation = $this->determineOperation($query);

        $params = [
            'TableName' => $this->getTableName($query),
        ];

        switch ($operation) {
            case 'GetItem':
                return [
                    'operation' => 'GetItem',
                    'params' => $this->compileGetItem($query, $params),
                ];

            case 'Query':
                return [
                    'operation' => 'Query',
                    'params' => $this->compileQuery($query, $params),
                ];

            case 'Scan':
            default:
                return [
                    'operation' => 'Scan',
                    'params' => $this->compileScan($query, $params),
                ];
        }
    }

    /**
     * Determine which DynamoDB operation to use.
     *
     * @param BaseBuilder $query
     * @return string
     */
    protected function determineOperation(BaseBuilder $query)
    {
        $wheres = $query->wheres;

        // GetItem: quando há apenas uma condição de igualdade na primary key
        if (count($wheres) === 1 &&
            $wheres[0]['type'] === 'Basic' &&
            $wheres[0]['operator'] === '=') {
            // Verificar se é realmente a primary key
            $resolver = $this->getIndexResolver($query);
            if ($resolver && $resolver->isPartitionKey($wheres[0]['column'])) {
                return 'GetItem';
            }
        }

        // Tentar encontrar índice usando IndexResolver
        $resolver = $this->getIndexResolver($query);
        if ($resolver) {
            $indexMatch = $resolver->findBestIndex($query);
            if ($indexMatch) {
                // Primary key simples sem sort key: usar GetItem só se NÃO houver
                // outras condições. Se houver (ex.: whereNull, where numa GSI),
                // usar Query para aplicar FilterExpression — do contrário a Key
                // sairia errada e/ou os filtros seriam ignorados (AUTOCONF-8-91B).
                if ($indexMatch['index_type'] === 'primary' &&
                    count($indexMatch['key_conditions']) === 1 &&
                    !$resolver->getSortKey()) {
                    $keyColumns = array_column($indexMatch['key_conditions'], 'column');
                    $remainingWheres = array_filter($wheres, function ($where) use ($keyColumns) {
                        $col = $where['column'] ?? null;
                        return $col === null || !in_array($col, $keyColumns);
                    });
                    if (empty($remainingWheres)) {
                        return 'GetItem'; // Primary key simples sem sort key e sem filtros extras
                    }
                }
                return 'Query'; // Usar Query com índice (e FilterExpression quando houver outras condições)
            }
        }

        // Por último, usar Scan (menos eficiente)
        return 'Scan';
    }

    /**
     * Compile GetItem operation.
     *
     * @param BaseBuilder $query
     * @param array $params
     * @return array
     */
    protected function compileGetItem(BaseBuilder $query, array $params)
    {
        // Montar a Key a partir da partition/sort key REAIS da tabela, e não de
        // $wheres[0]: a ordem das cláusulas where não é garantida, então usar a
        // primeira condição pode produzir uma Key que não bate com o schema
        // (ValidationException "The provided key element does not match the schema").
        $resolver = $this->getIndexResolver($query);
        $partitionKey = $resolver ? $resolver->getPartitionKey() : null;
        $sortKey = $resolver ? $resolver->getSortKey() : null;

        $key = [];
        foreach ($query->wheres as $where) {
            if (($where['type'] ?? null) !== 'Basic' || ($where['operator'] ?? null) !== '=') {
                continue;
            }
            if ($partitionKey && $where['column'] === $partitionKey) {
                $key[$partitionKey] = $where['value'];
            } elseif ($sortKey && $where['column'] === $sortKey) {
                $key[$sortKey] = $where['value'];
            }
        }

        // Fallback defensivo: se não foi possível resolver a partition key,
        // preserva o comportamento anterior (primeira condição como chave).
        if (empty($key) && !empty($query->wheres)) {
            $first = $query->wheres[0];
            $key = [$first['column'] => $first['value']];
        }

        $params['Key'] = $key;

        // Adicionar ProjectionExpression se houver select específico
        $this->addProjectionExpression($query, $params);

        return $params;
    }

    /**
     * Compile Query operation.
     *
     * @param BaseBuilder $query
     * @param array $params
     * @return array
     */
    protected function compileQuery(BaseBuilder $query, array $params)
    {
        $resolver = $this->getIndexResolver($query);

        if (!$resolver) {
            // Fallback para Scan se não conseguir resolver índices
            return $this->compileScan($query, $params);
        }

        $indexMatch = $resolver->findBestIndex($query);

        if (!$indexMatch) {
            return $this->compileScan($query, $params);
        }

        // Compilar KeyConditionExpression a partir das key conditions
        $keyConditions = $this->compileKeyConditions(
            $indexMatch['key_conditions'],
            $params
        );

        // Se usar GSI ou LSI, especificar IndexName
        if ($indexMatch['index_type'] !== 'primary' && $indexMatch['index_name']) {
            $params['IndexName'] = $indexMatch['index_name'];
        }

        // KeyConditionExpression é obrigatório para Query
        if (!empty($keyConditions['expression'])) {
            $params['KeyConditionExpression'] = $keyConditions['expression'];
            $params['ExpressionAttributeNames'] = array_merge(
                $params['ExpressionAttributeNames'] ?? [],
                $keyConditions['attributeNames']
            );
            $params['ExpressionAttributeValues'] = array_merge(
                $params['ExpressionAttributeValues'] ?? [],
                $keyConditions['attributeValues']
            );
        }

        // Compilar FilterExpression a partir das filter conditions
        // Usar contador maior que o usado em KeyConditionExpression para evitar conflitos
        $baseCounter = count($keyConditions['attributeNames'] ?? []);

        if (!empty($indexMatch['filter_conditions'])) {
            $filterConditions = $this->compileWheresForDynamoDb(
                $this->createQueryFromWheres($query, $indexMatch['filter_conditions']),
                $baseCounter
            );

            if (!empty($filterConditions['expression'])) {
                $params['FilterExpression'] = $filterConditions['expression'];
                $params['ExpressionAttributeNames'] = array_merge(
                    $params['ExpressionAttributeNames'] ?? [],
                    $filterConditions['attributeNames']
                );
                $params['ExpressionAttributeValues'] = array_merge(
                    $params['ExpressionAttributeValues'] ?? [],
                    $filterConditions['attributeValues']
                );
            }
        } else {
            // Se não há filter conditions específicas, compilar todas as condições
            // que não foram usadas como key conditions
            $remainingFilters = $this->getRemainingFilters(
                $query,
                $indexMatch['key_conditions']
            );

            if (!empty($remainingFilters)) {
                $filterConditions = $this->compileWheresForDynamoDb(
                    $this->createQueryFromWheres($query, $remainingFilters),
                    $baseCounter
                );
                if (!empty($filterConditions['expression'])) {
                    $params['FilterExpression'] = $filterConditions['expression'];
                    $params['ExpressionAttributeNames'] = array_merge(
                        $params['ExpressionAttributeNames'] ?? [],
                        $filterConditions['attributeNames']
                    );
                    $params['ExpressionAttributeValues'] = array_merge(
                        $params['ExpressionAttributeValues'] ?? [],
                        $filterConditions['attributeValues']
                    );
                }
            }
        }

        // Se for count, usar Select COUNT
        if (! is_null($query->aggregate) && isset($query->aggregate['function']) && $query->aggregate['function'] === 'count') {
            $params['Select'] = 'COUNT';
        } else {
            // Adicionar ProjectionExpression se houver select específico (apenas se não for COUNT)
            $this->addProjectionExpression($query, $params);
        }

        // Ordenação: no DynamoDB a ordenação só acontece pela sort key do índice/tabela,
        // controlada por ScanIndexForward (asc => true, desc => false). Traduz o orderBy.
        if (! empty($query->orders)) {
            $model = $this->getModelFromQuery($query);
            $indexSortKey = null;
            $indexType = $indexMatch['index_type'] ?? null;
            $indexName = $indexMatch['index_name'] ?? null;

            if ($indexType === 'gsi' && $indexName && $model) {
                $indexSortKey = $model->getGsiIndexes()[$indexName]['sort_key'] ?? null;
            } elseif ($indexType === 'lsi' && $indexName && $model) {
                $indexSortKey = $model->getLsiIndexes()[$indexName]['sort_key'] ?? null;
            } elseif ($indexType === 'primary') {
                $indexSortKey = $resolver->getSortKey();
            }

            foreach ($query->orders as $order) {
                $column = $order['column'] ?? null;
                if ($indexSortKey === null || $column === $indexSortKey) {
                    $params['ScanIndexForward'] = strtolower($order['direction'] ?? 'asc') !== 'desc';
                    break;
                }
            }
        }

        // Limit
        if ($query->limit !== null) {
            $params['Limit'] = $query->limit;
        }

        return $params;
    }

    /**
     * Compile key conditions into KeyConditionExpression.
     *
     * @param array $keyConditions
     * @param array $params
     * @return array
     */
    protected function compileKeyConditions(array $keyConditions, array &$params): array
    {
        $expression = [];
        $attributeNames = [];
        $attributeValues = [];
        $counter = 0;

        foreach ($keyConditions as $condition) {
            $counter++;
            $nameKey = "#attr{$counter}";
            $valueKey = ":val{$counter}";

            $column = $condition['column'];
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'];

            // Partition key sempre usa igualdade
            if (($condition['key_type'] ?? null) === 'partition' || $operator === '=') {
                $expression[] = "{$nameKey} = {$valueKey}";
            }
            // Sort key pode usar range operators
            elseif (in_array($operator, ['<', '<=', '>', '>=', 'between', 'begins_with'])) {
                if ($operator === 'begins_with') {
                    $expression[] = "begins_with({$nameKey}, {$valueKey})";
                } elseif ($operator === 'between') {
                    // Between precisa de 2 valores
                    $counter++;
                    $valueKey2 = ":val{$counter}";
                    $value2 = is_array($value) ? $value[1] : $value;
                    $expression[] = "{$nameKey} BETWEEN {$valueKey} AND {$valueKey2}";
                    $attributeValues[$valueKey2] = $value2;
                } else {
                    $expression[] = "{$nameKey} {$operator} {$valueKey}";
                }
            } else {
                $expression[] = "{$nameKey} = {$valueKey}";
            }

            $attributeNames[$nameKey] = $column;
            $attributeValues[$valueKey] = $value;
        }

        return [
            'expression' => implode(' AND ', $expression),
            'attributeNames' => $attributeNames,
            'attributeValues' => $attributeValues,
        ];
    }

    /**
     * Get remaining filters that weren't used in key conditions.
     *
     * @param BaseBuilder $query
     * @param array $keyConditions
     * @return array
     */
    protected function getRemainingFilters(BaseBuilder $query, array $keyConditions): array
    {
        $keyColumns = array_map(fn($kc) => $kc['column'], $keyConditions);

        return array_filter($query->wheres, function($where) use ($keyColumns) {
            return !in_array($where['column'], $keyColumns);
        });
    }

    /**
     * Create a query builder with specific where clauses.
     *
     * @param BaseBuilder $query
     * @param array $wheres
     * @return BaseBuilder
     */
    protected function createQueryFromWheres(BaseBuilder $query, array $wheres): BaseBuilder
    {
        $newQuery = clone $query;
        $newQuery->wheres = $wheres;
        return $newQuery;
    }

    /**
     * Compile Scan operation.
     *
     * @param BaseBuilder $query
     * @param array $params
     * @return array
     */
    protected function compileScan(BaseBuilder $query, array $params)
    {
        // FilterExpression será compilado a partir dos wheres
        $filterExpression = $this->compileWheresForDynamoDb($query, 0);

        if (!empty($filterExpression['expression'])) {
            $params['FilterExpression'] = $filterExpression['expression'];
            $params['ExpressionAttributeNames'] = $filterExpression['attributeNames'] ?? [];
            $params['ExpressionAttributeValues'] = $filterExpression['attributeValues'] ?? [];
        }

        // Se for count, usar Select COUNT (mais eficiente)
        if (! is_null($query->aggregate) && isset($query->aggregate['function']) && $query->aggregate['function'] === 'count') {
            $params['Select'] = 'COUNT';
        } else {
            // Adicionar ProjectionExpression se houver select específico (apenas se não for COUNT)
            $this->addProjectionExpression($query, $params);
        }

        // Limit
        if ($query->limit !== null) {
            $params['Limit'] = $query->limit;
        }

        return $params;
    }

    /**
     * Add ProjectionExpression to params if query has specific columns selected.
     *
     * @param BaseBuilder $query
     * @param array $params
     * @return void
     */
    protected function addProjectionExpression(BaseBuilder $query, array &$params): void
    {
        // Verificar se há select específico (não é ['*'] ou vazio)
        if (empty($query->columns) || $query->columns === ['*']) {
            return;
        }

        $projectionParts = [];
        $attributeNames = $params['ExpressionAttributeNames'] ?? [];
        $counter = count($attributeNames);

        foreach ($query->columns as $column) {
            // Ignorar colunas com alias ou funções agregadas
            if (str_contains($column, ' as ') || str_contains($column, '(')) {
                continue;
            }

            // Extrair nome da coluna (remover alias se houver)
            $columnName = trim(explode(' as ', $column)[0]);

            // Ignorar colunas inválidas
            if (empty($columnName) || $columnName === '*') {
                continue;
            }

            $counter++;
            $nameKey = "#attr{$counter}";

            $projectionParts[] = $nameKey;
            $attributeNames[$nameKey] = $columnName;
        }

        // Apenas adicionar ProjectionExpression se houver colunas válidas
        if (!empty($projectionParts)) {
            $params['ProjectionExpression'] = implode(', ', $projectionParts);
            $params['ExpressionAttributeNames'] = $attributeNames;
        }
    }

    /**
     * Compile where clauses to FilterExpression for DynamoDB.
     *
     * @param BaseBuilder $query
     * @param int $baseCounter Contador base para evitar conflitos com KeyConditionExpression
     * @return array
     */
    protected function compileWheresForDynamoDb(BaseBuilder $query, int $baseCounter = 0): array
    {
        $expression = [];
        $attributeNames = [];
        $attributeValues = [];
        $counter = $baseCounter;

        foreach ($query->wheres as $where) {
            $counter++;
            $nameKey = "#attr{$counter}";
            $valueKey = ":val{$counter}";

            switch ($where['type']) {
                case 'Basic':
                    $operator = $where['operator'];
                    $column = $where['column'];
                    $value = $where['value'];

                    // Tratar LIKE para FilterExpression (DynamoDB usa contains() para %texto%)
                    if ($operator === 'like') {
                        // Se começa e termina com %, usar contains()
                        if (str_starts_with($value, '%') && str_ends_with($value, '%')) {
                            $value = trim($value, '%');
                            $expression[] = "contains({$nameKey}, {$valueKey})";
                        }
                        // Se começa com %, usar ends_with() (não suportado diretamente, usar Scan)
                        // Por enquanto, usar contains como fallback
                        elseif (str_starts_with($value, '%')) {
                            $value = trim($value, '%');
                            $expression[] = "contains({$nameKey}, {$valueKey})";
                        }
                        // Se termina com %, usar begins_with() (não é FilterExpression, seria KeyConditionExpression)
                        // Por enquanto, usar contains como fallback
                        elseif (str_ends_with($value, '%')) {
                            $value = rtrim($value, '%');
                            $expression[] = "contains({$nameKey}, {$valueKey})";
                        }
                        // Sem %, tratar como igualdade
                        else {
                            $expression[] = "{$nameKey} = {$valueKey}";
                        }
                    } else {
                        $operator = $this->convertOperator($operator);
                        $expression[] = "{$nameKey} {$operator} {$valueKey}";
                    }

                    $attributeNames[$nameKey] = $column;
                    $attributeValues[$valueKey] = $value;
                    break;

                case 'In':
                    // whereIn: DynamoDB suporta "#attr IN (:v1, :v2, ...)" em FilterExpression
                    $values = array_values($where['values'] ?? []);

                    if (empty($values)) {
                        // IN vazio nunca casa: mantém o comportamento do SQL (nenhum resultado)
                        $expression[] = 'attribute_not_exists(' . $nameKey . ')';
                        $attributeNames[$nameKey] = $where['column'];
                        break;
                    }

                    $placeholders = [];
                    foreach ($values as $i => $inValue) {
                        $placeholder = "{$valueKey}_{$i}";
                        $placeholders[] = $placeholder;
                        $attributeValues[$placeholder] = $inValue;
                    }

                    $expression[] = "{$nameKey} IN (" . implode(', ', $placeholders) . ')';
                    $attributeNames[$nameKey] = $where['column'];
                    break;

                case 'Null':
                    // whereNull: no DynamoDB "nulo" pode ser atributo ausente
                    // ou atributo gravado com o tipo NULL - os dois precisam casar.
                    $expression[] = "(attribute_not_exists({$nameKey}) OR {$nameKey} = {$valueKey})";
                    $attributeNames[$nameKey] = $where['column'];
                    $attributeValues[$valueKey] = null;
                    break;

                case 'NotNull':
                    // whereNotNull: no DynamoDB "nulo" pode ser atributo ausente
                    // ou atributo gravado com o tipo NULL - os dois precisam ser excluidos
                    $expression[] = "(attribute_exists({$nameKey}) AND {$nameKey} <> {$valueKey})";
                    $attributeNames[$nameKey] = $where['column'];
                    $attributeValues[$valueKey] = null;
                    break;
            }
        }

        return [
            'expression' => implode(' AND ', $expression),
            'attributeNames' => $attributeNames,
            'attributeValues' => $attributeValues,
        ];
    }

    /**
     * Convert SQL operator to DynamoDB operator.
     *
     * @param string $operator
     * @return string
     */
    protected function convertOperator(string $operator): string
    {
        return match ($operator) {
            '=', '==', '===' => '=',
            '!=' => '<>',
            '<' => '<',
            '<=' => '<=',
            '>' => '>',
            '>=' => '>=',
            default => '=',
        };
    }

    /**
     * Compile an insert statement.
     *
     * @param BaseBuilder $query
     * @param array $values
     * @return array
     */
    public function compileInsert(BaseBuilder $query, array $values)
    {
        $table = $this->getTableName($query);

        // DynamoDB não aceita NULL em atributo de chave de índice (GSI/LSI).
        // Omitir atributos nulos do Item antes do PutItem (o item simplesmente
        // não aparece nesses índices), evitando ValidationException.
        $stripNulls = static fn (array $item): array => array_filter(
            $item,
            static fn ($value) => $value !== null
        );

        // Se for array de arrays (batch insert), retorna todos
        if (isset($values[0]) && is_array($values[0])) {
            return array_map(fn ($value) => [
                'params' => [
                    'TableName' => $table,
                    'Item' => $stripNulls($value),
                ],
            ], $values);
        }

        // Single insert
        return [
            'params' => [
                'TableName' => $table,
                'Item' => $stripNulls($values),
            ],
        ];
    }

    /**
     * Compile an update statement.
     *
     * @param BaseBuilder $query
     * @param array $values
     * @return array
     */
    public function compileUpdate(BaseBuilder $query, array $values)
    {
        $key = $this->extractKeyFromWheres($query);

        $setExpressions = [];
        $expressionAttributeNames = [];
        $expressionAttributeValues = [];
        $counter = 0;

        foreach ($values as $column => $value) {
            // Campo vazio (null ou string vazia) é removido do payload: o atributo não é alterado.
            // Evita o erro do DynamoDB ao gravar '' em atributo que é chave de índice (GSI/LSI),
            // onde string vazia não é aceita.
            if ($value === null || $value === '') {
                continue;
            }

            $counter++;
            $nameKey = "#attr{$counter}";
            $valueKey = ":val{$counter}";

            $expressionAttributeNames[$nameKey] = $column;
            $setExpressions[] = "{$nameKey} = {$valueKey}";
            $expressionAttributeValues[$valueKey] = $value;
        }

        $clauses = [];
        if (! empty($setExpressions)) {
            $clauses[] = 'SET ' . implode(', ', $setExpressions);
        }

        $params = [
            'TableName' => $this->getTableName($query),
            'Key' => $key,
            'UpdateExpression' => implode(' ', $clauses),
            'ExpressionAttributeNames' => $expressionAttributeNames,
        ];

        if (! empty($expressionAttributeValues)) {
            $params['ExpressionAttributeValues'] = $expressionAttributeValues;
        }

        return [
            'params' => $params,
        ];
    }

    /**
     * Compile a delete statement.
     *
     * @param BaseBuilder $query
     * @return array
     */
    public function compileDelete(BaseBuilder $query)
    {
        $key = $this->extractKeyFromWheres($query);

        return [
            'params' => [
                'TableName' => $this->getTableName($query),
                'Key' => $key,
            ],
        ];
    }

    /**
     * Get table name from query.
     *
     * @param BaseBuilder $query
     * @return string
     */
    protected function getTableName(BaseBuilder $query): string
    {
        return $query->from;
    }

    /**
     * Extract key from where clauses (simplificado).
     *
     * @param BaseBuilder $query
     * @return array
     */
    protected function extractKeyFromWheres(BaseBuilder $query): array
    {
        $key = [];
        foreach ($query->wheres as $where) {
            if ($where['type'] === 'Basic' && $where['operator'] === '=') {
                $key[$where['column']] = $where['value'];
            }
        }
        return $key;
    }
}
