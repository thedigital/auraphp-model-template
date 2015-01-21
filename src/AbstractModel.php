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

    public function __construct(Db $db_connection)
    {
        $this->db_connection = $db_connection;

        $this->column_factory = new ColumnFactory();
        $this->schema_described = new PgsqlSchema($this->db_connection, $this->column_factory);
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

    private function getPrimaryKey()
    {
        $cols = $this->schema_described->fetchTableCols($this->schema . '.' . $this->table);

        foreach ($cols as $name => $col) {
            if($col->primary === true) {
                $this->primaryKey = $name;
                continue;
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

    public function find($value)
    {
        $text = 'SELECT * FROM "' . $this->schema . '"."' . $this->table . '" where ' . $this->getPrimaryKey() . ' = :primaryValue';

        // values to bind to query placeholders
        $bind = [
            'primaryValue' => $value,
        ];

        $data = $this->getConnection()->fetchOne($text, $bind);

        return $this->fill($data);
    }

    public function fetchAll()
    {
        $text = 'SELECT * FROM "' . $this->schema . '"."' . $this->table . '"';

        $data = $this->getConnection()->fetchAll($text);

        return $data;
    }
}
