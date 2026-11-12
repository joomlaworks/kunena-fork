<?php

/**
 * Kunena Component
 * @package        Kunena.Framework
 *
 * @copyright      Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license        https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link           https://www.kunena.org
 *
 * Derived from Joomla 1.6
 * @copyright      Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die();

/**
 * Query Element Class.
 * @since Kunena
 */
#[\AllowDynamicProperties]
class KunenaDatabaseQueryElement
{
    /**
     * @var        string    The name of the element.
     * @since    1.6
     */
    protected $_name = null;

    /**
     * @var        array    An array of elements.
     * @since    1.6
     */
    protected $_elements = null;

    /**
     * @var        string    Glue piece.
     * @since    1.6
     */
    protected $_glue = null;

    /**
     * Constructor.
     *
     * @param   string $name     The name of the element.
     * @param   mixed  $elements String or array.
     * @param   string $glue     The glue for elements.
     *
     * @since    1.6
     */
    public function __construct($name, $elements, $glue = ',')
    {
        $this->_elements = array();
        $this->_name     = $name;
        $this->_glue     = $glue;

        $this->append($elements);
    }

    /**
     * Appends element parts to the internal list.
     *
     * @param   mixed $elements String or array.
     *
     * @return    void
     * @since    1.6
     */
    public function append($elements)
    {
        if (is_array($elements)) {
            $this->_elements = array_unique(array_merge($this->_elements, $elements));
        } else {
            $this->_elements = array_unique(array_merge($this->_elements, array($elements)));
        }
    }

    /**
     * Magic function to convert the query element to a string.
     *
     * @return    string
     * @since    1.6
     */
    public function __toString()
    {
        return PHP_EOL . $this->_name . ' ' . implode($this->_glue, $this->_elements);
    }

    /**
     * The parts of the element.
     *
     * @return array
     * @since 5.3.0
     */
    public function getElements()
    {
        return $this->_elements;
    }
}

/**
 * Query Building Class.
 * @since Kunena
 */
#[\AllowDynamicProperties]
class KunenaDatabaseQuery
{
    /**
     * @var        string    The query type.
     * @since    1.6
     */
    protected $_type = '';

    /**
     * @var        object    The select element.
     * @since    1.6
     */
    protected $_select = null;

    /**
     * @var        object    The delete element.
     * @since    1.6
     */
    protected $_delete = null;

    /**
     * @var        object    The update element.
     * @since    1.6
     */
    protected $_update = null;

    /**
     * @var        object    The insert element.
     * @since    1.6
     */
    protected $_insert = null;

    /**
     * @var        object    The from element.
     * @since    1.6
     */
    protected $_from = null;

    /**
     * @var        object    The join element.
     * @since    1.6
     */
    protected $_join = null;

    /**
     * @var        object    The set element.
     * @since    1.6
     */
    protected $_set = null;

    /**
     * @var        object    The where element.
     * @since    1.6
     */
    protected $_where = null;

    /**
     * @var        object    The group by element.
     * @since    1.6
     */
    protected $_group = null;

    /**
     * @var        object    The having element.
     * @since    1.6
     */
    protected $_having = null;

    /**
     * @var        object    The order element.
     * @since    1.6
     */
    protected $_order = null;

    /**
     * Clear data from the query or a specific clause of the query.
     *
     * @param   string $clause Optionally, the name of the clause to clear, or nothing to clear the whole query.
     *
     * @return KunenaDatabaseQuery|void
     * @since    1.6
     */
    public function clear($clause = null)
    {
        switch ($clause) {
            case 'select':
                $this->_select = null;
                $this->_type   = null;
                break;

            case 'delete':
                $this->_delete = null;
                $this->_type   = null;
                break;

            case 'update':
                $this->_update = null;
                $this->_type   = null;
                break;

            case 'insert':
                $this->_insert = null;
                $this->_type   = null;
                break;

            case 'from':
                $this->_from = null;
                break;

            case 'join':
                $this->_join = null;
                break;

            case 'set':
                $this->_set = null;
                break;

            case 'where':
                $this->_where = null;
                break;

            case 'group':
                $this->_group = null;
                break;

            case 'having':
                $this->_having = null;
                break;

            case 'order':
                $this->_order = null;
                break;

            default:
                $this->_type   = null;
                $this->_select = null;
                $this->_delete = null;
                $this->_update = null;
                $this->_insert = null;
                $this->_from   = null;
                $this->_join   = null;
                $this->_set    = null;
                $this->_where  = null;
                $this->_group  = null;
                $this->_having = null;
                $this->_order  = null;
                break;
        }

        return $this;
    }

    /**
     * @param   mixed $columns A string or an array of field names.
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function select($columns)
    {
        $this->_type = 'select';

        if (is_null($this->_select)) {
            $this->_select = new KunenaDatabaseQueryElement('SELECT', $columns);
        } else {
            $this->_select->append($columns);
        }

        return $this;
    }

    /**
     * @param   string $tables The name of the table to delete from.
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function delete($tables)
    {
        $this->_type   = 'delete';
        $this->_delete = new KunenaDatabaseQueryElement('DELETE', $tables);

        return $this;
    }

    /**
     * @param   mixed $tables A string or array of table names.
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function insert($tables)
    {
        $this->_type   = 'insert';
        $this->_insert = new KunenaDatabaseQueryElement('INSERT INTO', $tables);

        return $this;
    }

    /**
     * @param   mixed $tables A string or array of table names.
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function update($tables = null)
    {
        $this->_type   = 'update';
        $this->_update = new KunenaDatabaseQueryElement('UPDATE', $tables);

        return $this;
    }

    /**
     * @param   mixed $tables A string or array of table names.
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function from($tables)
    {
        if (is_null($this->_from)) {
            $this->_from = new KunenaDatabaseQueryElement('FROM', $tables);
        } else {
            $this->_from->append($tables);
        }

        return $this;
    }

    /**
     * @param   string $conditions conditions
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function innerJoin($conditions)
    {
        $this->join('INNER', $conditions);

        return $this;
    }

    /**
     * @param   string $type       type
     * @param   string $conditions conditions
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function join($type, $conditions)
    {
        if (is_null($this->_join)) {
            $this->_join = array();
        }

        $this->_join[] = new KunenaDatabaseQueryElement(strtoupper($type) . ' JOIN', $conditions);

        return $this;
    }

    /**
     * @param   string $conditions conditions
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function outerJoin($conditions)
    {
        $this->join('OUTER', $conditions);

        return $this;
    }

    /**
     * @param   string $conditions conditions
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function leftJoin($conditions)
    {
        $this->join('LEFT', $conditions);

        return $this;
    }

    /**
     * @param   string $conditions conditions
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function rightJoin($conditions)
    {
        $this->join('RIGHT', $conditions);

        return $this;
    }

    /**
     * @param   mixed  $conditions A string or array of conditions.
     * @param   string $glue       qlue
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function set($conditions, $glue = ',')
    {
        if (is_null($this->_set)) {
            $glue       = strtoupper($glue);
            $this->_set = new KunenaDatabaseQueryElement('SET', $conditions, "\n\t$glue ");
        } else {
            $this->_set->append($conditions);
        }

        return $this;
    }

    /**
     * @param   mixed  $conditions A string or array of where conditions.
     * @param   string $glue       glue
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function where($conditions, $glue = 'AND')
    {
        if (is_null($this->_where)) {
            $glue         = strtoupper($glue);
            $this->_where = new KunenaDatabaseQueryElement('WHERE', $conditions, " $glue ");
        } else {
            $this->_where->append($conditions);
        }

        return $this;
    }

    /**
     * @param   mixed $columns A string or array of ordering columns.
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function group($columns)
    {
        if (is_null($this->_group)) {
            $this->_group = new KunenaDatabaseQueryElement('GROUP BY', $columns);
        } else {
            $this->_group->append($columns);
        }

        return $this;
    }

    /**
     * @param   mixed  $conditions A string or array of columns.
     * @param   string $glue       glue
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function having($conditions, $glue = 'AND')
    {
        if (is_null($this->_having)) {
            $glue          = strtoupper($glue);
            $this->_having = new KunenaDatabaseQueryElement('HAVING', $conditions, " $glue ");
        } else {
            $this->_having->append($conditions);
        }

        return $this;
    }

    /**
     * @param   mixed $columns A string or array of ordering columns.
     *
     * @return    KunenaDatabaseQuery    Returns this object to allow chaining.
     * @since    1.6
     */
    public function order($columns)
    {
        if (is_null($this->_order)) {
            $this->_order = new KunenaDatabaseQueryElement('ORDER BY', $columns);
        } else {
            $this->_order->append($columns);
        }

        return $this;
    }

    /**
     * The query for the database: MySQL's DELETE ... JOIN, which SQLite's MySQL emulation lacks, becomes a DELETE of the
     * rows (by their rowid) which the same joins select; PostgreSQL gets toPostgresql().
     *
     * @param   JDatabaseDriver $db Database
     *
     * @return string
     * @since 5.3.0
     */
    public function toDatabase($db)
    {
        if (KunenaDatabaseHelper::isPostgresql($db)) {
            return $this->toPostgresql();
        }

        if (KunenaDatabaseHelper::isSqlite($db) && $this->_join && $this->_type == 'delete') {
            return $this->toRowDelete('rowid');
        }

        return (string) $this;
    }

    /**
     * DELETE of the rows, identified by a row id column (PostgreSQL's ctid, SQLite's rowid), selected by the query's joins.
     *
     * @param   string $rowid Row id column
     *
     * @return string
     * @since 5.3.0
     */
    protected function toRowDelete($rowid)
    {
        $joins = '';

        foreach ($this->_join as $join) {
            $joins .= (string) $join;
        }

        $where = $this->_where ? (string) $this->_where : '';
        $alias = trim(implode(',', $this->_delete->getElements()));
        $from  = implode(', ', $this->_from->getElements());

        return "DELETE FROM {$from} WHERE {$alias}.{$rowid} IN (SELECT {$alias}.{$rowid} FROM {$from}{$joins}{$where})";
    }

    /**
     * The query in PostgreSQL's SQL. MySQL's DELETE and UPDATE with joins (DELETE a FROM t AS a LEFT JOIN ...,
     * UPDATE t AS a INNER JOIN ... SET a.x=...) become a DELETE / UPDATE of the rows (by their ctid) which the same joins
     * select. Other queries are the same as in MySQL.
     *
     * @return string
     * @since 5.3.0
     */
    public function toPostgresql()
    {
        if (!$this->_join || !in_array($this->_type, array('delete', 'update'), true)) {
            return (string) $this;
        }

        $joins = '';

        foreach ($this->_join as $join) {
            $joins .= (string) $join;
        }

        $where = $this->_where ? (string) $this->_where : '';

        if ($this->_type == 'delete') {
            return $this->toRowDelete('ctid');
        }

        // UPDATE: SET a.column=expression, from a select of the new values
        $table = implode(', ', $this->_update->getElements());
        $alias = preg_match('/\s(?:AS\s+)?(\w+)\s*$/i', $table, $match) ? $match[1] : $table;
        $set   = array();
        $cols  = array();

        foreach ($this->_set ? $this->_set->getElements() : array() as $i => $assignment) {
            list($column, $value) = array_map('trim', explode('=', $assignment, 2));
            $column = preg_replace('/^' . preg_quote($alias, '/') . '\./', '', $column);
            $set[]  = "{$column} = kunena_new.v{$i}";
            $cols[] = "{$value} AS v{$i}";
        }

        return "UPDATE {$table} SET " . implode(', ', $set) . " FROM (SELECT {$alias}.ctid AS kunena_ctid, " . implode(', ', $cols)
            . " FROM {$table}{$joins}{$where}) AS kunena_new WHERE {$alias}.ctid = kunena_new.kunena_ctid";
    }

    /**
     * Legacy function to return a string representation of the query element.
     *
     * @return    string    The query element.
     * @since    1.0
     */
    public function toString()
    {
        return (string) $this;
    }

    /**
     * Magic function to convert the query to a string.
     *
     * @return    string    The completed query.
     * @since    1.6
     */
    public function __toString()
    {
        $query = '';

        switch ($this->_type) {
            case 'select':
                $query .= (string) $this->_select;
                $query .= (string) $this->_from;

                if ($this->_join) {
                    // Special case for joins
                    foreach ($this->_join as $join) {
                        $query .= (string) $join;
                    }
                }

                if ($this->_where) {
                    $query .= (string) $this->_where;
                }

                if ($this->_group) {
                    $query .= (string) $this->_group;
                }

                if ($this->_having) {
                    $query .= (string) $this->_having;
                }

                if ($this->_order) {
                    $query .= (string) $this->_order;
                }
                break;

            case 'delete':
                $query .= (string) $this->_delete;
                $query .= (string) $this->_from;

                if ($this->_join) {
                    // Special case for joins
                    foreach ($this->_join as $join) {
                        $query .= (string) $join;
                    }
                }

                if ($this->_where) {
                    $query .= (string) $this->_where;
                }
                break;

            case 'update':
                $query .= (string) $this->_update;

                if ($this->_join) {
                    // Special case for joins
                    foreach ($this->_join as $join) {
                        $query .= (string) $join;
                    }
                }

                $query .= (string) $this->_set;

                if ($this->_where) {
                    $query .= (string) $this->_where;
                }
                break;

            case 'insert':
                $query .= (string) $this->_insert;
                $query .= (string) $this->_set;

                if ($this->_where) {
                    $query .= (string) $this->_where;
                }
                break;
        }

        return $query;
    }
}
