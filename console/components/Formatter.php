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
    protected $column_encloser = ["[", "]"];

    /**
     * @var array data enclosing literal
     */
    protected $data_encloser = ["[", "]"];

    /**
     * @var string column string
     */
    protected $_columns = '';

    /**
     * @var string row string
     */
    protected $_rows = '';

    protected static $_colTypes = ['smallint'];
    /**
     * Returns the prepared column string
     * @param string $data the column string|$trim the literal to trim
     * @return string
     */
    public function prepareColumns($data, $trim = ',')
    {
        return $this->columnFormat($data, $trim);
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

        return "{$this->column_encloser[0]}" . rtrim($data, $trim) . "{$this->column_encloser[1]}";
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

        echo "<pre>";
        print_r($col);
        echo "</pre>";
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
