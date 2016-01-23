<?php
namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;
use yii\console\Exception;
use yii\db\Connection;

// use yii\console\controllers\BaseMigrateController as Migrate;
use yii\helpers\FileHelper;

class MigrationController extends Controller
{
    
    /**
     * @inheritdoc
     */
    public $defaultAction = 'migrate';
    
    /**
     * @var string a migration table name
     */
    protected $migrationTable = 'migration';
    
    /**
     * @var string a migration path
     */
    protected $migrationPath = "@app/testmigrations";
    
    /**
     * @var string class name
     */
    protected $class = "";
    
    /**
     * @var string table name
     */
    protected $table = "";
    
    /**
     * @var string file name
     */
    protected $fileName = '';
    
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
    
    /**
     * @var Connection|string the DB connection object or the application component ID of the DB connection.
     */
    public $db = 'db';
    
    public $test = "";
    
    /**
     * @inheritdoc
     */
    public function options($actionID) {
        return array_merge(parent::options($actionID), ['migrationTable', 'db', ]);
    }
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action) {
        
        if (parent::beforeAction($action)) {
            if (is_string($this->db)) {
                $this->db = Yii::$app->get($this->db);
            }
            if (!$this->db instanceof Connection) {
                throw new Exception("The 'db' option must refer to the application component ID of a DB connection.");
            }
            
            $path = Yii::getAlias($this->migrationPath);
            if (!is_dir($path)) {
                if ($action->id !== 'create') {
                    throw new Exception("Migration failed. Directory specified in migrationPath doesn't exist: {$this->migrationPath}");
                }
                FileHelper::createDirectory($path);
            }
            $this->migrationPath = $path;

            $version = Yii::getVersion();
            $this->stdout("Yii Database Migration Tool (based on Yii v{$version})\n\n", Console::FG_YELLOW);
            // $this->stdout("No new migration found. Your system is up-to-date.\n", Console::FG_GREEN);
            // $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
            
            return true;
        }
        return false;
    }
    
    /**
     * Returns the constant strings of yii\db\Schema class. e.g. Schema::TYPE_PK
     * @param string $type the column type
     * @return string
     */
    private function type($type) {
        $class = new \ReflectionClass('yii\db\Schema');
        return $class->getShortName() . '::' . implode(array_keys($class->getConstants(), $type));
    }
    
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table) {
        $sql = <<<SQL
            SELECT
                kcu.constraint_name,
                kcu.column_name,
                kcu.referenced_table_name,
                kcu.referenced_column_name,
                rc.DELETE_RULE,
                rc.UPDATE_RULE

            FROM information_schema.referential_constraints AS rc
            JOIN information_schema.key_column_usage AS kcu ON
                (
                    kcu.constraint_catalog = rc.constraint_catalog OR
                    (kcu.constraint_catalog IS NULL AND rc.constraint_catalog IS NULL)
                ) AND
                kcu.constraint_schema = rc.constraint_schema AND
                kcu.constraint_name = rc.constraint_name
            WHERE rc.constraint_schema = database() AND kcu.table_schema = database()
            AND rc.table_name = :tableName AND kcu.table_name = :tableName
SQL;
        try {
            
            // $rows = $this->db->createCommand($sql, [':tableName' => $table->name])->queryAll();
            $rows = \Yii::$app->db->createCommand($sql, [':tableName' => $table->name])->queryAll();

            $constraints = [];
            $table->foreignKeys = [];
            foreach ($rows as $row) {
                $constraints[$row['constraint_name']]['referenced_table_name'] = $row['referenced_table_name'];
                $constraints[$row['constraint_name']]['columns'][$row['column_name']] = $row['referenced_column_name'];

                // $table->foreignKeys[$row['constraint_name']]['name'] = $row['constraint_name'];
                $table->foreignKeys[$row['constraint_name']]['table'] = $row['referenced_table_name'];
                $table->foreignKeys[$row['constraint_name']]['column'] = $row['column_name'];
                $table->foreignKeys[$row['constraint_name']]['ref_column'] = $row['referenced_column_name'];
                $table->foreignKeys[$row['constraint_name']]['delete'] = $row['DELETE_RULE'];
                $table->foreignKeys[$row['constraint_name']]['update'] = $row['UPDATE_RULE'];
            }
            // $table->foreignKeys = [];
            // foreach ($constraints as $constraint) {
            //     $table->foreignKeys[] = array_merge([$constraint['referenced_table_name']], $constraint['columns']);
            // }
            return $constraints;
        }
        catch(\Exception $e) {
            $previous = $e->getPrevious();
            if (!$previous instanceof \PDOException || strpos($previous->getMessage(), 'SQLSTATE[42S02') === false) {
                throw $e;
            }
            
            // table does not exist, try to determine the foreign keys using the table creation sql
            $sql = $this->getCreateTableSql($table);
            $regexp = '/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
            if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fks = array_map('trim', explode(',', str_replace('`', '', $match[1])));
                    $pks = array_map('trim', explode(',', str_replace('`', '', $match[3])));
                    $constraint = [str_replace('`', '', $match[2]) ];
                    foreach ($fks as $k => $name) {
                        $constraint[$name] = $pks[$k];
                    }
                    $table->foreignKeys[md5(serialize($constraint)) ] = $constraint;
                }
                $table->foreignKeys = array_values($table->foreignKeys);
            }
        }
    }
    
    /**
     * Gets the CREATE TABLE sql string.
     * @param TableSchema $table the table metadata
     * @return string $sql the result of 'SHOW CREATE TABLE'
     */
    protected function getCreateTableSql($table) {
        $row = \Yii::$app->db->createCommand('SHOW CREATE TABLE ' . $this->quoteTableName($table->fullName))->queryOne();
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } 
        else {
            $row = array_values($row);
            $sql = $row[1];
        }
        return $sql;
    }
    
    public function quoteTableName($name) {
        return strpos($name, '`') !== false ? $name : "`$name`";
    }
    
    /* Add method to create reverse migration for individual tables here
     if you don't your life will become HELL!!! */
    
    public function actionTable(array $tables, $test = "hello") {
        
        if ($this->confirm('Create the migration ' . "?")) {
        
            foreach ($tables as $key => $tableName) {
            
                    $this->class = 'create_table_' . $tableName;
                    $this->table = $tableName;
                    
                    $table = \Yii::$app->db->getTableSchema($tableName);
                    
                    $tsch = $this->findConstraints($table);
                    
                    $sql = $this->getCreateTableSql($table);
                    // $constraint = [];
                    
                    $hasPrimaryKey = false;
                    $compositePrimaryKeyCols = array();
                    
                    $addForeignKeys = "";
                    $dropForeignKeys = "";
                    $name = $this->getFileName();
                    
                    $result = "<?php \n\n";
                    $result.= "use yii\db\Schema;\n";
                    $result.= "use yii\db\Migration;\n\n";
                    $result.= "class {$name} extends Migration\n{\n\n";
                    
                    $result.= "\tpublic function safeUp()\n\t\t{\n";
                    $result.= "\t\t\t" . '$this->execute("SET foreign_key_checks = 0;");' . "\n";
                    
                    // Create table
                    $result.= "\t\t\t" . '$this->createTable(\'' . $table->name . '\', array(' . "\n";
                    
                    foreach ($table->columns as $col) {
                        $result.= "\t\t\t\t" . '\'' . $col->name . '\'=>"' . $this->getColType($col) . '",' . "\n";
                        if ($col->isPrimaryKey) {
                            $hasPrimaryKey = true;
                            // Add column to composite primary key array
                            $compositePrimaryKeyCols[] = $col->name;
                        }
                    }
                    if ($hasPrimaryKey):
                        $result.= "\t\t\t\t" . '\'PRIMARY KEY (' . implode(',', $compositePrimaryKeyCols) . ')\' ' . "\n\t\t" . '    ), \'\');' . "\n";
                    else:
                        $result.= "\t\t\t\t" . '), \'\');' . "\n";
                    endif;
                    
                    $ukeys = \Yii::$app->db->schema->findUniqueIndexes($table);
                    if (!empty($ukeys)) {
                        foreach ($ukeys as $key => $value) {
                            foreach ($value as $id => $field) {
                                $result.= "\t\t\t" . '$this->createIndex(\'idx_' . $key . "', '$table->name', '$field', TRUE);\n";
                            }
                        }
                    }
                    
                    // Add foreign key(s) and create indexes
                    if (!empty($table->foreignKeys)):

                        foreach ($table->foreignKeys as $fkName => $fk) {
                            $addForeignKeys.= "\t\t\t" . '$this->addForeignKey("'.$fkName.'", "'.$table->name.'", "'.$fk['column'].'","'. $fk['table'].'","'. $fk['ref_column'].'", "'.$fk['delete'].'", "'.$fk['update'].'");'."\n";
                            $dropForeignKeys.= '$this->dropForeignKey(' . "'$fkName', '$table->name');";
                        }
                    endif;
                    
                    $result.= $addForeignKeys;
                    
                    $result.= "\t\t\t" . '$this->execute("SET foreign_key_checks = 1;");' . "\n";
                    $result.= "\t\t}\n\n";
                    $result.= "\tpublic function safeDown()\n\t\t{\n";
                    $result.= "\t\t\t" . $dropForeignKeys . "\n";
                    $result.= "\t\t\t" . '$this->dropTable(\'' . $table->name . '\');' . "\n";
                    
                    $result.= "\t\t}\n}";

                    $this->prepareFile($result). "\n\n";
            }

        }
    }
    
    /**
     * Returns the name of the table migration file created
     * @param string $args the table name
     * @return string
     */
    public function actionData(array $tables) {
        

        foreach ($tables as $key => $args) {
       
        $table = $args;
        $this->class = 'insert_data_into_' . $table;
        $this->table = $table;
        
        $columns = \Yii::$app->db->getTableSchema($table);
        $prefix = \Yii::$app->db->tablePrefix;
        $table = str_replace($prefix, '', $table);
        
        $prepared_columns = '';
        $prepared_data = [];
        
        $name = $this->getFileName();
        
        if (!empty($table)) {
            $data = Yii::$app->db->createCommand('SELECT * FROM `' . $table . '`')->queryAll();
            
            $pcolumns = '';
            foreach ($columns->columns as $column) {
                $pcolumns.= "'" . $column->name . "',";
            }
            foreach ($data as $row) {
                array_push($prepared_data, $row);
            }
            
            $pcolumns = $this->prepareColumns($pcolumns);
            $prows = $this->prepareData($prepared_data);
            
            $insertData = $this->prepareInsert($pcolumns, $prows);
            
            $result = "<?php \n\n";
            $result.= "use yii\db\Schema;\n";
            $result.= "use yii\db\Migration;\n\n";
            $result.= "class {$name} extends Migration\n\n{\n\n";
            
            $result.= "\tpublic function safeUp()\n\t\t{\n";
            $result.= "\t\t\t" . '$this->execute("SET foreign_key_checks = 0;");' . "\n\n";
            $result.= "\t\t\t" . '$this->truncateTable(\'' . $table . '\');' . "\n\n";
            $result.= "\t\t\t" . $insertData . "\n\n";
            $result.= "\t\t\t" . '$this->execute("SET foreign_key_checks = 1;");' . "\n\n";
            $result.= "\t\t}\n\n\n";
            $result.= "\tpublic function safeDown()\n\t\t{\n";
            $result.= "\t\t\t" . '$this->truncateTable(\'' . $table . '\');' . "\n\n";
            
            $result.= "\t\t}\n\n\n}";
            $this->prepareFile($result). "\n\n";
        }
    }
    }
    
    /**
     * Returns the name of the database migration file created
     * @param string $args the schema name
     * @return string
     */
    public function actionMigrate($args) {
        
        $schema = $args;
        $this->class = 'dump_database_' . $schema;
        
        // $tables = Yii::$app->db->schema->getTableNames($schema);
        
        $tables = $this->db->schema->getTableSchemas($schema);
        $addForeignKeys = '';
        $dropForeignKeys = '';
        $hasPrimaryKey = false;
        $name = $this->getFileName();

        $generateTables = [];


        /*foreach ($tables as $table) {
                    if ($table->name === $this->migrationTable) {
                        continue;
                    }
                    $generateTables[]= $table->name;
        }
         $this->actionTable($generateTables);*/


        $result = "<?php \n\n";
        $result.= "use yii\db\Schema;\n\n";
        $result.= "use yii\db\Migration;\n\n";
        $result.= "class {$name} extends Migration\n\n{\n\n";
        
        $result.= "\tpublic function safeUp()\n\t\t{\n";
        foreach ($tables as $table) {
            if ($table->name === $this->migrationTable) {
                continue;
            }


            
            $hasPrimaryKey = false;
            $compositePrimaryKeyCols = array();
            
            // Create table
            $result.= "\t\t\t" . '$this->createTable(\'' . $table->name . '\', array(' . "\n";
            foreach ($table->columns as $col) {
                $result.= "\t\t\t\t" . '\'' . $col->name . '\'=>"' . $this->getColType($col) . '",' . "\n";
                
                // if ($col->isPrimaryKey && !$col->autoIncrement) {
                
                //     // Add column to composite primary key array
                //     $compositePrimaryKeyCols[] = $col->name;
                // }
                if ($col->isPrimaryKey) {
                    
                    $hasPrimaryKey = true;
                    
                    // Add column to composite primary key array
                    $compositePrimaryKeyCols[] = $col->name;
                }
            }
            if ($hasPrimaryKey):
                $result.= "\t\t\t\t" . '\'PRIMARY KEY (' . implode(',', $compositePrimaryKeyCols) . ')\' ' . "\n\t\t" . '    ), \'\');' . "\n";
            else:
                $result.= "\t\t\t\t" . '), \'\');' . "\n\n";
            endif;
            
            // Add foreign key(s) and create indexes
            if (!empty($table->foreignKeys)):
                
                foreach ($table->foreignKeys as $col => $fk) {
                    
                    $fk_attr = array_values($fk);
                    $fk_keys = array_keys($fk);
                    
                    // Foreign key naming convention: fk_table_foreignTable_col (max 64 characters)
                    if ($col == 0):
                        $fkName = substr('fk_' . $table->name . '_' . $fk_keys[1] . '_' . $fk_attr[1], 0, 64);
                    else:
                        $fkName = substr('fk_' . $table->name . '_' . $fk_keys[1] . '_' . $fk_attr[1], 0, 64);
                        
                        // $fkName = substr('fk_' . $table->name . '_' . $fk[0] . '_' . $col, 0, 64);
                        
                        
                    endif;
                    
                    if ($col == 0):
                        $addForeignKeys.= '    $this->addForeignKey(' . "'$fkName', '$table->name', '$fk_keys[1]', '$fk[0]', '$fk_attr[1]', 'RESTRICT', 'CASCADE');\n\n";
                    else:
                        $addForeignKeys.= '    $this->addForeignKey(' . "'$fkName', '$table->name', '$fk_keys[1]', '$fk[0]', '$fk_attr[1]', 'RESTRICT', 'CASCADE');\n\n";
                    endif;
                    
                    $dropForeignKeys.= "\t" . '$this->dropForeignKey(' . "'$fkName', '$table->name');\n\n";
                    
                    // Index naming convention: idx_col
                    if ($col == 0):
                        $result.= '$this->createIndex(\'idx_' . $fk[0] . "', '$table->name', '$fk_keys[1]', FALSE);\n\n";
                    else:
                        $result.= '    $this->createIndex(\'idx_' . $fk[0] . "', '$table->name', '$fk_keys[1]', FALSE);\n\n";
                    endif;
                }
            endif;
            
            // Add composite primary key for join tables
            if ($compositePrimaryKeyCols) {
                
                //$result.= '    $this->addPrimaryKey(\'pk_' . $table->name . "', '$table->name', '" . implode(',', $compositePrimaryKeyCols) . "');\n\n";
                
                
            }
            
        }
       
       $result.= $addForeignKeys;
        
        // This needs to come after all of the tables have been created.
        $result.= "\t\t}\n\n\n";
        $result.= "\tpublic function safeDown()\n\t\t{\n";
        $result.= $dropForeignKeys;
        
        // This needs to come before the tables are dropped.
        foreach ($tables as $table) {
            if ($table->name === $this->migrationTable) {
                continue;
            }
            $result.= "\t\t\t" . '$this->dropTable(\'' . $table->name . '\');' . "\n";
        }
        $result.= "\t\t}\n\n}\n";
        
        // $path = '/var/www/public_html/';
        // $fileName = $this->class;
        // $filePath = $path . $fileName;
        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $name . '.php';
        $this->prepareFile($result) . "\n\n";

       
    }
    
    /**
     * Returns the prepared column string
     * @param string $data the column string|$trim the literal to trim
     * @return string
     */
    public function prepareColumns($data, $trim = ',') {
        return $this->columnFormat($data, $trim);
    }
    
    /**
     * Returns the prepared data string
     * @param array $data the data array
     * @return string
     */
    public function prepareData($data = []) {
        
        $this->_rows = '';
        foreach ($data as $key => $row) {
            $rows = '';
            foreach ($row as $column => $value) {
                $rows.= "'" . $value . "',";
            }
            $this->_rows.= "\n".$this->dataFormat($rows) . ",";
        }
        
        if (!empty($this->_rows)) {
            return $this->dataFormat($this->_rows);
        }
        return [];
    }
    
    /**
     * Returns the formatted column string
     * @param string $data the column string|$trim the literal to trim
     * @return string
     */
    public function columnFormat($data, $trim = ',') {
        if (null !== $trim) $data = rtrim($data, $trim);
        return "{$this->column_encloser[0]}" . rtrim($data, $trim) . "{$this->column_encloser[1]}";
    }
    
    /**
     * Returns the formatted data string
     * @param string $data the column string|$trim the literal to trim
     * @return string
     */
    public function dataFormat($data, $trim = ',') {
        if (null !== $trim) $data = rtrim($data, $trim);
        return "{$this->data_encloser[0]}" . $data . "{$this->data_encloser[1]}";
    }
    
    public function getColType($col) {
        
        if ($col->isPrimaryKey && $col->autoIncrement) {
            $result = $col->dbType;
            $result.= ' NOT NULL AUTO_INCREMENT';
            
            // return "pk";
            return $result;
        }
       $result = $col->dbType;
       // die;
        if (!$col->allowNull) {
            $result.= ' NOT NULL';
        }
        if ($col->defaultValue != null && 'timestamp' != $col->dbType) {
            $result.= " DEFAULT '{$col->defaultValue}'";
        }elseif ($col->defaultValue == 'CURRENT_TIMESTAMP' && 'timestamp' == $col->dbType) {
            $result.= " DEFAULT {$col->defaultValue}";
        }
        elseif ($col->defaultValue != null && 'timestamp' == $col->dbType) {
            $result.= " DEFAULT '{$col->defaultValue}'";
        } 
         
        elseif ($col->allowNull) {
            $result.= ' DEFAULT NULL';
        }
        return $result;
    }
    
    public function getFileName() {
        return 'm' . gmdate('ymd_His') . '_' . $this->class;
    }
    
    public function setFileName() {
        $this->fileName = $this->getFileName();
    }
    
    public function prepareFile($data) {
        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $this->getFileName() . '.php';
        try {
            file_put_contents($file, $data);
             $this->stdout("New migration {$this->getFileName()} successfully created.\nRun yii migrate to apply migrations.\n\n", Console::FG_GREEN);
            return $file;
        }
        catch(Exception $e) {
            throw new Exception("There has been an error processing the file. Please try after some time.");
        }
    }
    
    public function prepareInsert($rows, $columns) {
        return '$this->batchInsert("{{%' . $this->table . '%}}", ' . $rows . ', ' . $columns . ');';
    }
}
