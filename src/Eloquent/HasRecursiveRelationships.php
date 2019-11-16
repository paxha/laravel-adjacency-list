<?php

namespace Paxha\LaravelAdjacencyList\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Grammar;
use Illuminate\Support\Str;
use RuntimeException;
use Paxha\LaravelAdjacencyList\Eloquent\Relations\Ancestors;
use Paxha\LaravelAdjacencyList\Eloquent\Relations\Descendants;
use Paxha\LaravelAdjacencyList\Eloquent\Relations\Siblings;
use Paxha\LaravelAdjacencyList\Query\Grammars\ExpressionGrammar;
use Paxha\LaravelAdjacencyList\Query\Grammars\MySqlGrammar;
use Paxha\LaravelAdjacencyList\Query\Grammars\PostgresGrammar;
use Paxha\LaravelAdjacencyList\Query\Grammars\SQLiteGrammar;
use Paxha\LaravelAdjacencyList\Query\Grammars\SqlServerGrammar;
use Paxha\LaravelCte\Eloquent\QueriesExpressions;

trait HasRecursiveRelationships
{
    use QueriesExpressions;

    /**
     * Get the name of the parent key column.
     *
     * @return string
     */
    public function getParentKeyName()
    {
        return 'parent_id';
    }

    /**
     * Get the qualified parent key column.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return (new static)->getTable().'.'.$this->getParentKeyName();
    }

    /**
     * Get the name of the depth column.
     *
     * @return string
     */
    public function getDepthName()
    {
        return 'depth';
    }

    /**
     * Get the name of the path column.
     *
     * @return string
     */
    public function getPathName()
    {
        return 'path';
    }

    /**
     * Get the path separator.
     *
     * @return string
     */
    public function getPathSeparator()
    {
        return '.';
    }

    /**
     * Get the name of the common table expression.
     *
     * @return string
     */
    public function getExpressionName()
    {
        return 'laravel_cte';
    }

    /**
     * Get the model's ancestors.
     *
     * @return Ancestors
     */
    public function ancestors()
    {
        return $this->newAncestors(
            (new static)->newQuery(),
            $this,
            $this->getQualifiedParentKeyName(),
            $this->getKeyName(),
            false
        );
    }

    /**
     * Get the model's ancestors and itself.
     *
     * @return Ancestors
     */
    public function ancestorsAndSelf()
    {
        return $this->newAncestors(
            (new static)->newQuery(),
            $this,
            $this->getQualifiedParentKeyName(),
            $this->getKeyName(),
            true
        );
    }

    /**
     * Instantiate a new Ancestors relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @param bool $andSelf
     * @return Ancestors
     */
    protected function newAncestors(Builder $query, Model $parent, $foreignKey, $localKey, $andSelf)
    {
        return new Ancestors($query, $parent, $foreignKey, $localKey, $andSelf);
    }

    /**
     * Get the model's children.
     *
     * @return HasMany
     */
    public function children()
    {
        return $this->hasMany(static::class, $this->getParentKeyName());
    }

    /**
     * Get the model's children and itself.
     *
     * @return Descendants
     */
    public function childrenAndSelf()
    {
        return $this->descendantsAndSelf()->whereDepth('<=', 1);
    }

    /**
     * Get the model's descendants.
     *
     * @return Descendants
     */
    public function descendants()
    {
        return $this->newDescendants(
            (new static)->newQuery(),
            $this,
            $this->getQualifiedParentKeyName(),
            $this->getKeyName(),
            false
        );
    }

    /**
     * Get the model's descendants and itself.
     *
     * @return Descendants
     */
    public function descendantsAndSelf()
    {
        return $this->newDescendants(
            (new static)->newQuery(),
            $this,
            $this->getQualifiedParentKeyName(),
            $this->getKeyName(),
            true
        );
    }

    /**
     * Instantiate a new Descendants relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @param bool $andSelf
     * @return Descendants
     */
    protected function newDescendants(Builder $query, Model $parent, $foreignKey, $localKey, $andSelf)
    {
        return new Descendants($query, $parent, $foreignKey, $localKey, $andSelf);
    }

    /**
     * Get the model's parent.
     *
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(static::class, $this->getParentKeyName());
    }

    /**
     * Get the model's parent and itself.
     *
     * @return Ancestors
     */
    public function parentAndSelf()
    {
        return $this->ancestorsAndSelf()->whereDepth('>=', -1);
    }

    /**
     * Get the model's siblings.
     *
     * @return Siblings
     */
    public function siblings()
    {
        return $this->newSiblings(
            (new static)->newQuery(),
            $this,
            $this->getQualifiedParentKeyName(),
            $this->getParentKeyName(),
            false
        );
    }

    /**
     * Get the model's siblings and itself.
     *
     * @return Siblings
     */
    public function siblingsAndSelf()
    {
        return $this->newSiblings(
            (new static)->newQuery(),
            $this,
            $this->getQualifiedParentKeyName(),
            $this->getParentKeyName(),
            true
        );
    }

    /**
     * Instantiate a new Siblings relationship.
     *
     * @param Builder $query
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @param bool $andSelf
     * @return Siblings
     */
    protected function newSiblings(Builder $query, Model $parent, $foreignKey, $localKey, $andSelf)
    {
        return new Siblings($query, $parent, $foreignKey, $localKey, $andSelf);
    }

    /**
     * Add a recursive expression for the relationship's whole tree to the query.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeTree(Builder $query)
    {
        $constraint = function (Builder $query) {
            $query->isRoot();
        };

        return $query->withRelationshipExpression('desc', $constraint, 0);
    }

    /**
     * Limit the query to models with children.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeHasChildren(Builder $query)
    {
        $keys = (new static)->newQuery()
            ->select($this->getParentKeyName())
            ->hasParent();

        return $query->whereIn($this->getKeyName(), $keys);
    }

    /**
     * Limit the query to models with a parent.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeHasParent(Builder $query)
    {
        return $query->whereNotNull($this->getParentKeyName());
    }

    /**
     * Limit the query to leaf models.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeIsLeaf(Builder $query)
    {
        $keys = (new static)->newQuery()
            ->select($this->getParentKeyName())
            ->hasParent();

        return $query->whereNotIn($this->getKeyName(), $keys);
    }

    /**
     * Limit the query to root models.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeIsRoot(Builder $query)
    {
        return $query->whereNull($this->getParentKeyName());
    }

    /**
     * Limit the query by depth.
     *
     * @param Builder $query
     * @param mixed $operator
     * @param mixed $value
     * @return Builder
     */
    public function scopeWhereDepth(Builder $query, $operator, $value = null)
    {
        $arguments = array_slice(func_get_args(), 1);

        return $query->where($this->getDepthName(), ...$arguments);
    }

    /**
     * Order the query breadth-first.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeBreadthFirst(Builder $query)
    {
        return $query->orderBy($this->getDepthName());
    }

    /**
     * Order the query depth-first.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDepthFirst(Builder $query)
    {
        return $query->orderBy($this->getPathName());
    }

    /**
     * Add a recursive expression for the relationship to the query.
     *
     * @param Builder $query
     * @param string $direction
     * @param callable $constraint
     * @param int $initialDepth
     * @param string|null $from
     * @return Builder
     */
    public function scopeWithRelationshipExpression(Builder $query, $direction, callable $constraint, $initialDepth, $from = null)
    {
        $from = $from ?: $this->getTable();

        $grammar = $this->getExpressionGrammar($query);

        $expression = $this->getInitialQuery($grammar, $constraint, $initialDepth, $from)
            ->unionAll(
                $this->getRecursiveQuery($grammar, $direction, $from)
            );

        $name = $this->getExpressionName();

        $query->getModel()->setTable($name);

        return $query->withRecursiveExpression($name, $expression)->from($name);
    }

    /**
     * Get the initial query for a relationship expression.
     *
     * @param ExpressionGrammar|Grammar $grammar
     * @param callable $constraint
     * @param int $initialDepth
     * @param string $from
     * @return Builder $query
     */
    protected function getInitialQuery(ExpressionGrammar $grammar, callable $constraint, $initialDepth, $from)
    {
        $depth = $grammar->wrap($this->getDepthName());

        $initialPath = $grammar->compileInitialPath(
            $this->getKeyName(),
            $this->getPathName()
        );

        $query = $this->newModelQuery()
            ->select('*')
            ->selectRaw($initialDepth.' as '.$depth)
            ->selectRaw($initialPath)
            ->from($from);

        $constraint($query);

        return $query;
    }

    /**
     * Get the recursive query for a relationship expression.
     *
     * @param ExpressionGrammar|Grammar $grammar
     * @param string $direction
     * @param string $from
     * @return Builder $query
     */
    protected function getRecursiveQuery(ExpressionGrammar $grammar, $direction, $from)
    {
        $name = $this->getExpressionName();

        $table = explode(' as ', $from)[1] ?? $from;

        $depth = $grammar->wrap($this->getDepthName());

        $recursiveDepth = $grammar->wrap($this->getDepthName()).' '.($direction === 'asc' ? '-' : '+').' 1';

        $recursivePath = $grammar->compileRecursivePath(
            $this->getQualifiedKeyName(),
            $this->getPathName(),
            $this->getPathSeparator()
        );

        $query = $this->newModelQuery()
            ->select($table.'.*')
            ->selectRaw($recursiveDepth.' as '.$depth)
            ->selectRaw($recursivePath)
            ->from($from);

        if ($direction === 'asc') {
            $first = $this->getParentKeyName();
            $second = $this->getQualifiedKeyName();
        } else {
            $first = $this->getKeyName();
            $second = $this->qualifyColumn($this->getParentKeyName());
        }

        $query->join($name, $name.'.'.$first, '=', $second);

        return $query;
    }

    /**
     * Get the expression grammar.
     *
     * @param Builder $query
     * @return ExpressionGrammar
     */
    protected function getExpressionGrammar(Builder $query)
    {
        $driver = $query->getConnection()->getDriverName();

        switch ($driver) {
            case 'mysql':
                return $query->getConnection()->withTablePrefix(new MySqlGrammar);
            case 'pgsql':
                return $query->getConnection()->withTablePrefix(new PostgresGrammar);
            case 'sqlite':
                return $query->getConnection()->withTablePrefix(new SQLiteGrammar);
            case 'sqlsrv':
                return $query->getConnection()->withTablePrefix(new SqlServerGrammar);
        }

        throw new RuntimeException('This database is not supported.'); // @codeCoverageIgnore
    }

    /**
     * Get the first segment of the model's path.
     *
     * @return string
     */
    public function getFirstPathSegment()
    {
        $path = $this->attributes[$this->getPathName()];

        return explode($this->getPathSeparator(), $path)[0];
    }

    /**
     * Determine whether the model's path is nested.
     *
     * @return bool
     */
    public function hasNestedPath()
    {
        $path = $this->attributes[$this->getPathName()];

        return Str::contains($path, $this->getPathSeparator());
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new \Paxha\LaravelAdjacencyList\Eloquent\Builder($query);
    }
}
