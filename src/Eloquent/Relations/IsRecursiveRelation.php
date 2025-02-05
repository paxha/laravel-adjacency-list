<?php

namespace Paxha\LaravelAdjacencyList\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait IsRecursiveRelation
{
    /**
     * Whether to include the parent model.
     *
     * @var bool
     */
    protected $andSelf;

    /**
     * Create a new recursive relationship instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @param bool $andSelf
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey, $andSelf)
    {
        $this->andSelf = $andSelf;

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Build model dictionary.
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        return $results->mapToDictionary(function (Model $result) {
            return [$result->getFirstPathSegment() => $result];
        })->all();
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $methods = ['update', 'increment', 'decrement', 'delete', 'forceDelete'];

        if (in_array($method, $methods)) {
            $expression = $this->query->getQuery()->from;

            $table = $this->parent->getTable();

            $this->query->getQuery()->from = $table;

            $this->query->getModel()->setTable($table);

            $keys = $this->query->getQuery()->newQuery()->from($expression)->select($this->localKey);

            return $this->query->whereIn($this->getQualifiedParentKeyName(), $keys)->$method(...$parameters);
        }

        return parent::__call($method, $parameters);
    }
}
