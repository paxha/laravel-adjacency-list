<?php

namespace Pahxa\LaravelAdjacencyList\Query\Grammars;

use Illuminate\Database\Query\Grammars\MySqlGrammar as Base;

class MySqlGrammar extends Base implements ExpressionGrammar
{
    /**
     * Compile an initial path.
     *
     * @param string $column
     * @param string $alias
     * @return string
     */
    public function compileInitialPath($column, $alias)
    {
        return 'cast('.$this->wrap($column).' as char(65535)) as '.$this->wrap($alias);
    }

    /**
     * Compile a recursive path.
     *
     * @param string $column
     * @param string $alias
     * @param string $separator
     * @return string
     */
    public function compileRecursivePath($column, $alias, $separator)
    {
        return "concat(".$this->wrap($alias).", '".$separator."', ".$this->wrap($column).")";
    }
}
