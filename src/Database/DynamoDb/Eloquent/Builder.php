<?php

namespace AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    /**
     * Paginate the given query using cursor-based pagination for DynamoDB.
     *
     * DynamoDB não suporta OFFSET, então usamos LastEvaluatedKey (cursor).
     * Este método sobrescreve o comportamento padrão do Eloquent Builder
     * para delegar diretamente ao Query Builder customizado do DynamoDB.
     *
     * @param  int|null  $perPage
     * @param  array|string  $columns
     * @param  string  $cursorName
     * @param  string|null  $cursor
     * @return Paginator
     */
    public function simplePaginate($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        // Garantir que columns seja array
        if (! is_array($columns)) {
            $columns = ['*'];
        }

        // Delegar ao Query Builder customizado (paginação por cursor correta).
        $paginator = $this->query->simplePaginate($perPage, $columns, $cursorName, $cursor);

        // O Query Builder devolve stdClass; hidratamos em instâncias do model para
        // preservar accessors/casts/relations que os consumidores esperam (o Eloquent
        // base hidratava). O cursor e o hasMorePages já calculados são mantidos.
        $paginator->setCollection(
            $this->hydrate(array_map(static fn ($item) => (array) $item, $paginator->items()))
        );

        return $paginator;
    }
}
