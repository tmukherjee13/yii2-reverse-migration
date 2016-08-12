<?php

namespace tmukherjee13\migration\console\components;

/**
 * Table class
 */
class Formatter
{

    /**
     * @var array column enclosing literal
     */
    protected static $column_encloser = ["[", "]"];

    /**
     * @var array data enclosing literal
     */
    protected static $data_encloser = ["[", "]"];

    /**
     * @var string column string
     */
    protected static $_columns = '';

    /**
     * @var string row string
     */
    protected static $_rows = '';

    protected static $_colTypes = ['smallint'];




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
        self::$_rows = '';
        foreach ($data as $key => $row) {
            $rows = '';
            foreach ($row as $column => $value) {
                $rows .= "'" . addslashes($value) . "',";
            }
            self::$_rows .= "\n\t\t\t" . self::dataFormat($rows) . ",";
        }
        if (!empty(self::$_rows)) {
            return self::dataFormat(self::$_rows);
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
        return self::$data_encloser[0] . $data . self::$data_encloser[1];
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

        return self::$column_encloser[0] . rtrim($data, $trim) . self::$column_encloser[1];
    }

    public function getColType($col)
    {

        if ($col->isPrimaryKey && $col->autoIncrement) {
            // $result = $col->dbType;
            // $result .= ' NOT NULL AUTO_INCREMENT';
            // return $result;

            return 'pk';
        }
        $result = $col->dbType;
        // die;
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

    public function formatCol($col)
    {
        $decorator = [];

        if ($col->isPrimaryKey && $col->autoIncrement) {
            $decorator[] = 'primaryKey';
        } elseif (in_array($col->type, self::$_colTypes)) {
            $decorator[] = "{$col->phpType}";
        } else {

            if (!empty($col->size) && $col->size == 1) {
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

        return $decorator;
    }
}
