<?php
namespace database;

trait QueryBuilder {
    protected $table;
    protected $connection;
    protected $query = [
        'select' => '*',
        'wheres' => [],
        'joins' => [],
        'groups' => [],
        'havings' => [],
        'orders' => [],
        'limit' => null,
        'offset' => null,
        'unions' => [],
        'params' => [],
        'bindings' => [
            'select' => [],
            'join' => [],
            'where' => [],
            'having' => [],
            'order' => [],
            'union' => []
        ]
    ];

    /**
     * Define a tabela para a consulta
     */
    public function setTable($table) {
        $this->table = $table;
        $this->resetQuery();
        return $this;
    }

    /**
     * Reseta a query para estado inicial
     */
    protected function resetQuery() {
        $this->query = [
            'select' => '*',
            'wheres' => [],
            'joins' => [],
            'groups' => [],
            'havings' => [],
            'orders' => [],
            'limit' => null,
            'offset' => null,
            'unions' => [],
            'params' => [],
            'bindings' => [
                'select' => [],
                'join' => [],
                'where' => [],
                'having' => [],
                'order' => [],
                'union' => []
            ]
        ];
    }

    /**
     * Seleciona colunas específicas
     */
    public function select($columns = ['*']) {
        $this->query['select'] = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    /**
     * Adiciona uma expressão RAW ao SELECT
     */
    public function selectRaw($expression, array $bindings = []) {
        $this->query['select'] = $expression;
        $this->addBindings($bindings, 'select');
        return $this;
    }

    /**
     * Adiciona condição WHERE básica
     */
    public function where($column, $operator = null, $value = null, $boolean = 'AND') {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $type = 'Basic';
        $this->query['wheres'][] = compact('type', 'column', 'operator', 'value', 'boolean');
        $this->addBinding($value, 'where');
        return $this;
    }

    /**
     * Adiciona condição OR WHERE
     */
    public function orWhere($column, $operator = null, $value = null) {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Adiciona condição WHERE IN
     */
    public function whereIn($column, $values, $boolean = 'AND', $not = false) {
        $type = $not ? 'NotIn' : 'In';
        $this->query['wheres'][] = compact('type', 'column', 'values', 'boolean');
        $this->addBindings($values, 'where');
        return $this;
    }

    /**
     * Adiciona condição WHERE NOT IN
     */
    public function whereNotIn($column, $values, $boolean = 'AND') {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Adiciona condição WHERE NULL
     */
    public function whereNull($column, $boolean = 'AND', $not = false) {
        $type = $not ? 'NotNull' : 'Null';
        $this->query['wheres'][] = compact('type', 'column', 'boolean');
        return $this;
    }

    /**
     * Adiciona condição WHERE NOT NULL
     */
    public function whereNotNull($column, $boolean = 'AND') {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Adiciona JOIN básico
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'INNER') {
        if (func_num_args() === 3) {
            $second = $operator;
            $operator = '=';
        }

        $this->query['joins'][] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    /**
     * Adiciona LEFT JOIN
     */
    public function leftJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Adiciona RIGHT JOIN
     */
    public function rightJoin($table, $first, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Adiciona GROUP BY
     */
    public function groupBy(...$groups) {
        $this->query['groups'] = array_merge(
            $this->query['groups'],
            is_array($groups[0]) ? $groups[0] : $groups
        );
        return $this;
    }

    /**
     * Adiciona HAVING
     */
    public function having($column, $operator, $value = null, $boolean = 'AND') {
        if (func_num_args() === 3) {
            $value = $operator;
            $operator = '=';
        }

        $this->query['havings'][] = compact('column', 'operator', 'value', 'boolean');
        $this->addBinding($value, 'having');
        return $this;
    }

    /**
     * Adiciona ORDER BY
     */
    public function orderBy($column, $direction = 'ASC') {
        $this->query['orders'][] = compact('column', 'direction');
        return $this;
    }

    /**
     * Adiciona ORDER BY DESC
     */
    public function orderByDesc($column) {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Adiciona LIMIT
     */
    public function limit($value) {
        $this->query['limit'] = (int)$value;
        return $this;
    }

    /**
     * Adiciona OFFSET
     */
    public function offset($value) {
        $this->query['offset'] = (int)$value;
        return $this;
    }

    /**
     * Adiciona UNION
     */
    public function union($query) {
        $this->query['unions'][] = $query;
        return $this;
    }

    /**
     * Executa a query e retorna todos os resultados
     */
    public function get() {
        $sql = $this->toSql();
        $stmt = $this->getConnection()->prepare($sql);
        
        foreach ($this->getBindings() as $key => $value) {
            $stmt->bindValue($key + 1, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Executa a query e retorna o primeiro resultado
     */
    public function first() {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    /**
     * Retorna a SQL gerada
     */
    public function toSql() {
        return $this->compileSelect();
    }

    /**
     * Retorna os bindings da query
     */
    public function getBindings() {
        return array_merge(
            $this->query['bindings']['select'],
            $this->query['bindings']['join'],
            $this->query['bindings']['where'],
            $this->query['bindings']['having'],
            $this->query['bindings']['order'],
            $this->query['bindings']['union']
        );
    }

    /**
     * Compila a query SELECT
     */
    protected function compileSelect() {
        $sql = "SELECT {$this->query['select']} FROM {$this->table}";

        // JOINs
        if (!empty($this->query['joins'])) {
            foreach ($this->query['joins'] as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        // WHERE
        if (!empty($this->query['wheres'])) {
            $sql .= ' WHERE '.$this->compileWheres($this->query['wheres']);
        }

        // GROUP BY
        if (!empty($this->query['groups'])) {
            $sql .= ' GROUP BY '.implode(', ', $this->query['groups']);
        }

        // HAVING
        if (!empty($this->query['havings'])) {
            $sql .= ' HAVING '.$this->compileHavings($this->query['havings']);
        }

        // ORDER BY
        if (!empty($this->query['orders'])) {
            $sql .= ' ORDER BY '.$this->compileOrders($this->query['orders']);
        }

        // LIMIT e OFFSET
        if (!is_null($this->query['limit'])) {
            $sql .= " LIMIT {$this->query['limit']}";
            
            if (!is_null($this->query['offset'])) {
                $sql .= " OFFSET {$this->query['offset']}";
            }
        }

        // UNION
        if (!empty($this->query['unions'])) {
            foreach ($this->query['unions'] as $union) {
                $sql .= " UNION ({$union})";
            }
        }

        return $sql;
    }

    /**
     * Compila as cláusulas WHERE
     */
    protected function compileWheres($wheres) {
        $whereClauses = [];
        
        foreach ($wheres as $where) {
            switch ($where['type']) {
                case 'Basic':
                    $whereClauses[] = "{$where['column']} {$where['operator']} ?";
                    break;
                    
                case 'In':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $whereClauses[] = "{$where['column']} IN ({$placeholders})";
                    break;
                    
                case 'NotIn':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $whereClauses[] = "{$where['column']} NOT IN ({$placeholders})";
                    break;
                    
                case 'Null':
                    $whereClauses[] = "{$where['column']} IS NULL";
                    break;
                    
                case 'NotNull':
                    $whereClauses[] = "{$where['column']} IS NOT NULL";
                    break;
            }
        }
        
        return implode(' '.$wheres[0]['boolean'].' ', $whereClauses);
    }

    /**
     * Compila as cláusulas HAVING
     */
    protected function compileHavings($havings) {
        $havingClauses = [];
        
        foreach ($havings as $having) {
            $havingClauses[] = "{$having['column']} {$having['operator']} ?";
        }
        
        return implode(' AND ', $havingClauses);
    }

    /**
     * Compila as cláusulas ORDER BY
     */
    protected function compileOrders($orders) {
        $orderClauses = [];
        
        foreach ($orders as $order) {
            $orderClauses[] = "{$order['column']} {$order['direction']}";
        }
        
        return implode(', ', $orderClauses);
    }

    /**
     * Adiciona binding
     */
    protected function addBinding($value, $type = 'where') {
        $this->query['bindings'][$type][] = $value;
    }

    /**
     * Adiciona múltiplos bindings
     */
    protected function addBindings(array $values, $type = 'where') {
        $this->query['bindings'][$type] = array_merge($this->query['bindings'][$type], $values);
    }

    /**
     * Métodos de agregação
     */
    public function count() {
        $result = $this->selectRaw('COUNT(*) as aggregate')->first();
        return (int)($result['aggregate'] ?? 0);
    }

    public function max($column) {
        $result = $this->selectRaw("MAX({$column}) as aggregate")->first();
        return $result['aggregate'] ?? null;
    }

    public function min($column) {
        $result = $this->selectRaw("MIN({$column}) as aggregate")->first();
        return $result['aggregate'] ?? null;
    }

    public function sum($column) {
        $result = $this->selectRaw("SUM({$column}) as aggregate")->first();
        return $result['aggregate'] ?? 0;
    }

    public function avg($column) {
        $result = $this->selectRaw("AVG({$column}) as aggregate")->first();
        return $result['aggregate'] ?? 0;
    }

    /**
     * Define a conexão PDO
     */
    public function setConnection(\PDO $connection) {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Obtém a conexão PDO
     */
    protected function getConnection() {
        return $this->connection;
    }
}