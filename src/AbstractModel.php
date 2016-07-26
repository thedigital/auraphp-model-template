<?php
namespace Thedigital\Model_Template;

use Mbrevda\Queryproxy\Db;
use Aura\SqlSchema\ColumnFactory;
use Aura\SqlSchema\PgsqlSchema;

abstract class AbstractModel
{
    protected $db_connection;

    protected $schema_described;

    protected $column_factory;

    protected $primaryKey = null;

    protected $context = null;

    protected $schema = null;

    protected $table = null;


    public function getTable()
    {
        return $this->table;
    }

    public function __construct(Db $db_connection)
    {
        $this->db_connection = $db_connection;

        $this->column_factory = new ColumnFactory();
        $this->schema_described = new PgsqlSchema($this->db_connection, $this->column_factory);

        // if primaryKey not known yet, we look for it (so it's done only once)
        if ($this->primaryKey == null) {
            $this->getPrimaryKey();
        }
    }

    public function setApplicationName($application_name)
    {
        $this->db_connection->exec('SET application_name = "' . $application_name . ' : ' . get_called_class() . '"');
    }

    public function setSleep($sleep_time)
    {
        if (!is_numeric($sleep_time) || $sleep_time > 30) {
            $sleep_time = 30;
        }
        $this->db_connection->exec('select pg_sleep('.$sleep_time.')');
    }

    public function getConnection()
    {
        return $this->db_connection;
    }

    public function setContext($context)
    {
        $this->context = $context;
    }

/*
    public function getConnection($type = null, $name = null)
    {
        switch ($type) {
            case 'master':
                $connection = 'getMaster';
                break;
            case 'slave':
                $connection = 'getSlave';
                break;
            default:
                $connection = 'getDefault';
                break;
        }
        return $this->db_connection->$connection($name);
    }*/

    public function getPrimaryKey()
    {
        // if primaryKey not known yet, we look for it (so it's done only once)
        if ($this->primaryKey == null) {
            $cols = $this->schema_described->fetchTableCols($this->schema . '.' . $this->table);

            foreach ($cols as $name => $col) {
                if($col->primary === true) {
                    $this->primaryKey = $name;
                    continue;
                }
            }
        }

        return $this->primaryKey;
    }

    protected function fill($data = array())
    {
        if ($data) {
            foreach ($data as $key => $value) {
                // on verifie si la propriete est definie dans l'object
                //if ($this->{$key}) {
                    $this->{$key} = $value;
                //}
            }
        }
        return $this;
    }

    /*
     * Generic select for one line with primary key
     * */
    public function find($value)
    {
        return $this->fetch([[$this->primaryKey, '=', $value]])->fetchObject();
    }


    /*
     * Generic update with clauses et datas
     * */
    final public function updateAbstract($clauses, $datas)
    {
        return $this->getConnection()
            ->update()
            ->table($this->schema.'.'.$this->table)
            ->cols($data)
            ->where($clauses)
            //->bindValues($datas)
            ->returning(['*'])
            ->fetchObject();
    }

    /*
     * Generic insert with 1-N datas
     * */
    final public function insert($data)
    {
        // if empty $data given
        if (count($data) == 0) {
            return null;
        } else {
            $datas = $data;
            $results = [];

            // if array dimension == 1, we put the $data in any array so we can iterate just like a N $data
            if (count($data) == count($data, COUNT_RECURSIVE)) {
                $datas = [$data];
            }

            // we iterate on $datas
            foreach ($datas as $data_iter) {
                $result = $this->getConnection()
                    ->insert()
                    ->into($this->schema.'.'.$this->table)
                    ->cols($data_iter)
                    ->returning(['*'])
                    ->fetchObject();
                $results[] = $result;
            }
        }

        // if only one result : we give results
        if (count($results) == 1) {
            return $results[0];
        } else { // else we give array of results
            return $results;
        }
    }

    /*
     * Generic select for 1 line with 1-N clauses
     *
     * $clauses = [
     *      ['key', 'operator', 'value'],
     *      ['key', 'operator', 'value'],
     *      ['key', 'is null'],
     * ]
     *
     * $cols = ['col1', 'col2', 'col3', ... ]
     *
     * */
    final public function fetchOne($clauses = [], $cols = [])
    {
        return $this->fetch($clauses, $cols)->fetchObject();
    }


    /*
     * Generic select for N line with 1-N clauses
     *
     * $clauses = [
     *      ['key', 'operator', 'value'],
     *      ['key', 'operator', 'value'],
     *      ['key', 'is null'],
     * ]
     *
     * $cols = ['col1', 'col2', 'col3', ... ]
     *
     * */
    final public function fetchAll($clauses = [], $cols = [])
    {
        return $this->fetch($clauses, $cols)->fetchObjects();
    }


    /*
     * Generic fetch for any SQL select
     * */
    final private function fetch($clauses = [], $cols = ['*']) {
        $select_stmt = $this->getConnection()->select();

        if (count($cols) > 0) {
            $select_stmt->cols($cols);
        } else {
            $select_stmt->cols(['*']);
        }

        $select_stmt->from($this->schema.'.'.$this->table);

        if (count($clauses) > 0) {
            foreach ($clauses as $clause) {
                // $select_stmt->where(implode(' ', $clause));
                if (count($clause) == 3) {
                    $select_stmt->where($clause[0] . ' ' . $clause[1] . ' ?', $clause[2]);
                } else {
                    $select_stmt->where(implode(' ', $clause));
                }
            }
        }

        return $select_stmt;
    }
}
