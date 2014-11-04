<?php
namespace Adminko\Db\Driver;

use PDO;

abstract class Driver
{
    protected $dbh = null;

    public static function factory($db_type = DB_TYPE, $db_host = DB_HOST, $db_port = '', 
        $db_name = DB_NAME, $db_user = DB_USER, $db_password = DB_PASSWORD)
    {
        $class_namespace = 'Driver';
        $class_name = __NAMESPACE__ . '\\' . to_class_name($db_type) . $class_namespace;

        if (!class_exists($class_name)) {
            throw new \Exception('Ошибка. Класс "' . $class_name . '" не найден.');
        }

        return new $class_name($db_type, $db_host, $db_port, $db_name, $db_user, $db_password);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////

    protected function execute($query, $fields = array())
    {
        $sth = $this->dbh->prepare($query);

        foreach ($fields as $name => $value) {
            $sth->bindValue(":" . $name, $value);
        }
        $sth->execute();

        return $sth;
    }

    public function query($query, $fields = array())
    {
        return $this->execute($query, $fields)->rowCount();
    }

    public function selectCell($query, $fields = array())
    {
        return $this->execute($query, $fields)->fetchColumn(0);
    }

    public function selectRow($query, $fields = array())
    {
        return $this->execute($query, $fields)->fetch(PDO::FETCH_ASSOC);
    }

    public function selectAll($query, $fields = array())
    {
        return $this->execute($query, $fields)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $fields = array())
    {
        $columns = array();
        $values = array();
        foreach ($fields as $name => $value) {
            $columns[] = "{$name}";
            $values[] = ":$name";
        }
        $columns = join(", ", $columns);
        $values = join(", ", $values);

        $query = "insert into {$table} ({$columns}) values ({$values})";

        return $this->execute($query, $fields)->rowCount();
    }

    public function update($table, $fields = array(), $where = array())
    {
        $pairs = array();
        foreach ($fields as $name => $value) {
            $pairs[] = "{$name} = :{$name}";
        }
        $pairs = join(", ", $pairs);

        $conds = array();
        foreach ($where as $name => $value) {
            $conds[] = "{$name} = :conds_{$name}";
            $fields["conds_" . $name] = $value;
        }
        $conds = join(" and ", $conds);

        $query = "update {$table} set {$pairs}" . ($conds ? " where " : " ") . $conds;

        return $this->execute($query, $fields)->rowCount();
    }

    public function delete($table, $where = array())
    {
        $conds = array();
        $fields = array();
        foreach ($where as $name => $value) {
            $conds[] = "{$name} = :conds_{$name}";
            $fields["conds_" . $name] = $value;
        }
        $conds = join(" and ", $conds);

        $query = "delete from {$table}" . ($conds ? " where " : " ") . $conds;

        return $this->execute($query, $fields)->rowCount();
    }

    public function lastInsertId($sequence = null)
    {
        return $this->dbh->lastInsertId($sequence);
    }

    public function beginTransaction()
    {
        return $this->dbh->beginTransaction();
    }

    public function commit()
    {
        return $this->dbh->commit();
    }

    public function rollBack()
    {
        return $this->dbh->rollBack();
    }

    public function create()
    {
        return $this->dbh->create();
    }
}
