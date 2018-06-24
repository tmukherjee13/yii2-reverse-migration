<?php

namespace tmukherjee13\migration\console\components;

/**
 * Table class
 */
trait Formatter
{

    /**
     * @var array column enclosing literal
     */
    protected static $columnEncloser = ["[", "]"];

    /**
     * @var array data enclosing literal
     */
    protected static $dataEncloser = ["[", "]"];

    /**
     * @var string column string
     */
    protected static $columns = '';

    /**
     * @var string row string
     */
    protected static $rows = '';

    /** @var array conflicting column types */
    protected static $colTypes = ['tinyint', 'smallint'];



    /**
     * Prepared the table name
     * @method getTableName
     * @param  object       $table
     * @return string
     * @author Tarun Mukherjee (https://github.com/tmukherjee13)
     */

    public function getTableName($table)
    {
        return '{{%' . str_replace($this->db->tablePrefix, '', $table->name) . '}}';
    }


    
    public function prepareInsert($rows, $columns)
    {

        
        return '$this->batchInsert("{{%test}}", ' . $rows . ', ' . $columns . ');';
    }

    /**
     * Returns the prepared column string
     * @param string $data the column string|$trim the literal to trim
     * @return string
     */
    public function prepareColumns($data, $trim = ',')
    {
        return self::columnFormat($data, $trim);
    }

    /**
     * Returns the prepared data string
     * @param array $data the data array
     * @return string
     */
    public function prepareData($data = [])
    {
        self::$rows = '';
        foreach ($data as $row) {
            $rows = '';
            foreach ($row as $value) {
                $rows .= "'" . addslashes($value) . "',";
            }
            self::$rows .= "\n\t\t\t" . self::dataFormat($rows) . ",";
        }
        if (!empty(self::$rows)) {
            return self::dataFormat(self::$rows);
        }
        return '';
    }

    /**
     * Returns the formatted data string
     * @param string $data the column string|$trim the literal to trim
     * @return string
     */
    public function dataFormat($data, $trim = ',')
    {
        if (null !== $trim) {
            $data = rtrim($data, $trim);
        }
        return self::$dataEncloser[0] . $data . self::$dataEncloser[1];
    }

    /**
     * Returns the formatted column string
     * @param string $data the column string|$trim the literal to trim
     * @return string
     */
    public function columnFormat($data, $trim = ',')
    {
        if (null !== $trim) {
            $data = rtrim($data, $trim);
        }

        return self::$columnEncloser[0] . rtrim($data, $trim) . self::$columnEncloser[1];
    }

    /**
     * returns the correct column type for given column
     *
     * @method getColType
     * @param  yii\db\TableSchema     $col
     * @return string
     * @author Tarun Mukherjee (https://github.com/tmukherjee13)
     */

    public function getColType($col)
    {

        if ($col->isPrimaryKey && $col->autoIncrement) {
            return 'pk';
        }
        $result = $col->dbType;

        if (!$col->allowNull) {
            $result .= ' NOT NULL';
        }
        if ($col->defaultValue != null && 'timestamp' != $col->dbType) {
            $result .= " DEFAULT '{$col->defaultValue}'";
        } elseif ($col->defaultValue == 'CURRENT_TIMESTAMP' && 'timestamp' == $col->dbType) {
            $result .= " DEFAULT {$col->defaultValue}";
        } elseif ($col->defaultValue != null && 'timestamp' == $col->dbType) {
            $result .= " DEFAULT '{$col->defaultValue}'";
        } elseif ($col->allowNull) {
            $result .= ' DEFAULT NULL';
        }
        return $result;
    }

    /**
     * Заменяет некоторые типы данных
     *
     * @param string $dbType
     *
     * @return string
     */
    public function modifyColType(string $dbType): string
    {
        $dbType = mb_ereg_replace('tinyint', 'tinyInteger', $dbType);
        $dbType = mb_ereg_replace('smallint', 'smallInteger', $dbType);
        return $dbType;
    }

    /**
     * Formats the given column with appropriate decorators.
     *
     * @method formatCol
     * @param  yii\db\TableSchema    $col
     * @return mixed
     * @author Tarun Mukherjee (https://github.com/tmukherjee13)
     */

    public function formatCol($col)
    {
        $decorator = [];
        if ($col->isPrimaryKey && $col->autoIncrement) {
            $decorator[] = 'primaryKey';
        } elseif (in_array($col->type, self::$colTypes)) {
            $decorator[] = "{$this->modifyColType($col->dbType)}";
        } elseif ($col->type == 'decimal') {
            $decorator[] = "{$col->dbType}";
        } else {
            if (!empty($col->size) && $col->size == 1 && $col->type != 'char') {
                $column = "boolean";
            } else {
                $column = "{$col->type}";
                if (!empty($col->size)) {
                    $column .= "({$col->size})";
                }
            }
            $decorator[] = $column;
        }
        if ($col->unsigned) {
            $decorator[] = 'unsigned';
        }
        if (!$col->allowNull) {
            $decorator[] = 'notNull';
        }
        if (!empty($col->defaultValue)) {
            $decorator[] = "defaultValue({$col->defaultValue})";
        }

        if (!empty($col->comment)) {
            $decorator[] = "comment(\"{$col->comment}\")";
        }

        return $decorator;
    }

}
