<?php
namespace tmukherjee13\migration\console\controllers;

use Yii;
use yii\db\Query;
use yii\db\Connection;
use yii\db\TableSchema;
use yii\helpers\Console;
use yii\console\Exception;
use yii\helpers\FileHelper;
use yii\helpers\ArrayHelper;
use yii\console\controllers\MigrateController;


class MigrationController extends MigrateController
{
    use \tmukherjee13\migration\console\components\Formatter;
    
    /**
     * @inheritdoc
     */
    public $defaultAction = 'migrate';
    
    /** @var string a migration table name */
    public $migrationTable = 'migration';
    
    /** @var string a migration path */
    public $migrationPath = "@app/migrations";
    
    /** @var string путь к данным */
    public $dataMigrationFolder = 'data';
    
    /** @var bool использовать путь к данным */
    public $useDataMigrationFolder = TRUE;
    
    /** @var string template file to use for generation */
    public $templateFile = "@tmukherjee13/migration";
    
    /** @var string table columns */
    public $fields = "";
    
    /** @var Connection|string the DB connection object or the application component ID of the DB connection. */
    public $db = 'db';
    
    /** @var string class */
    protected $class = "";
    
    /** @var string table name */
    protected $table = "";
    
    /** @var string file name */
    protected $fileName = '';
    
    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['migrationTable', 'db']);
    }
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        
        if (parent::beforeAction($action)) {
            if (is_string($this->db)) {
                $this->db = Yii::$app->get($this->db);
            }
            if (!$this->db instanceof Connection) {
                throw new Exception("The 'db' option must refer to the application component ID of a DB connection.");
            }
            
            $path = (string) Yii::getAlias($this->migrationPath);
            
            
            if (!is_dir($path)) {
                if ($action->id !== 'table') {
                    
                    throw new Exception("Migration failed. Directory specified in migrationPath doesn't exist: {$this->migrationPath}");
                }
                FileHelper::createDirectory($path);
            }
            $this->migrationPath = $path;
            
            $version = Yii::getVersion();
            $this->stdout("Yii Database Migration Tool (based on Yii v{$version})\n", Console::FG_YELLOW);
            
            return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Up data
     *
     * @param int $limit
     *
     * @return int
     */
    public function actionUpData($limit = 0)
    {
        $this->migrationPath .= DIRECTORY_SEPARATOR . $this->dataMigrationFolder;
        return parent::actionUp($limit);
    }
    
    /**
     * Returns the name of the data migration file created
     *
     * @param array $tables the list of tables
     *
     * @return int
     * @throws \yii\base\Exception
     * @throws \yii\console\Exception
     * @throws \yii\db\Exception
     */
    public function actionData(array $tables)
    {
        if ($this->confirm('Create the migration ' . "?", TRUE)) {
            foreach ($tables as $key => $args) {
                
                $table = $args;
                $this->class = 'insert_data_into_' . $table;
                $this->table = $table;
                $this->templateFile = '@tmukherjee13/migration/views/dataTemplate.php';
                
                $columns = $this->db->getTableSchema($table);
                $prefix = $this->db->tablePrefix;
                $table = str_replace($prefix, '', $table);
                $table = $columns;
                
                $this->table = $this->getTableName($table);
                
                $prepared_columns = '';
                $up = '';
                $down = '';
                $prepared_data = [];
                
                $name = $this->getFileName();
                
                if (!empty($table)) {
                    
                    $query = new Query;
                    $query->select('*')
                        ->from($table->name);
                    $command = $query->createCommand();
                    $data = $command->queryAll();
                    
                    $pcolumns = '';
                    foreach ($columns->columns as $column) {
                        $pcolumns .= "'" . $column->name . "',";
                    }
                    foreach ($data as $row) {
                        array_push($prepared_data, $row);
                    }
                    
                    if (empty($prepared_data)) {
                        $this->stdout("\nTable '{$table->name}' doesn't contain any data.\n\n", Console::FG_RED);
                    } else {
                        $pcolumns = $this->prepareColumns($pcolumns);
                        $prows = $this->prepareData($prepared_data);
                        
                        if ($this->useDataMigrationFolder) {
                            $path = $this->migrationPath . DIRECTORY_SEPARATOR . $this->dataMigrationFolder;
                            FileHelper::createDirectory($path);
                            $this->migrationPath = $path;
                        }
                        $this->prepareFile(['columns' => $pcolumns, 'rows' => $prows]);
                    }
                    // return self::EXIT_CODE_ERROR;
                }
            }
            return self::EXIT_CODE_NORMAL;
        }
    }
    
    /**
     * @return string
     */
    public function getFileName()
    {
        return 'm' . gmdate('ymd_His') . '_' . $this->class;
    }
    
    /**
     *
     */
    public function setFileName()
    {
        $this->fileName = $this->getFileName();
    }
    
    /**
     * @param $data
     *
     * @return string
     * @throws \yii\console\Exception
     */
    public function prepareFile($data)
    {
        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $this->getFileName() . '.php';
        try {
            
            $data['table'] = $this->table;
            $data['className'] = $this->getFileName();
            $content = $this->renderFile(Yii::getAlias($this->templateFile), $data);
            file_put_contents($file, $content);
            $this->stdout("\nNew migration {$this->getFileName()} successfully created.\nRun yii migrate to apply migrations.\n", Console::FG_GREEN);
            return $file;
        } catch (Exception $e) {
            throw new Exception("There has been an error processing the file. Please try after some time.");
        }
    }
    
    /**
     * Returns the name of the database migration file created
     *
     * @param $args
     *
     * @return int
     * @throws \yii\console\Exception
     */
    public function actionSchema($args)
    {
        
        $schema = $args;
        $this->class = 'dump_database_' . $schema;
        $tables = $this->db->schema->getTableSchemas($schema);
        $addForeignKeys = '';
        $dropForeignKeys = '';
        $up = '';
        $down = '';
        $hasPrimaryKey = FALSE;
        $name = $this->getFileName();
        $tablePrefix = $this->db->tablePrefix;
        $generateTables = [];
        
        foreach ($tables as $table) {
            if ($table->name === $this->db->tablePrefix . $this->migrationTable) {
                continue;
            }
            $generateTables[] = $table->name;
        }
        $this->actionTable($generateTables);
        return self::EXIT_CODE_NORMAL;
        
    }
    
    /**
     * Creates migration based on table
     * @method actionTable
     *
     * @param array $tables Name of the table to create migration
     *
     * @return mixed
     * @author Tarun Mukherjee (https://github.com/tmukherjee13)
     */
    public function actionTable(array $tables)
    {
        
        if (!$this->confirm('Create the migration ' . "?")) {
            return;
        }
        
        foreach ($tables as $key => $tableName) {
            
            $this->class = 'create_table_' . $tableName;
            
            try {
                
                $table = $this->db->getTableSchema($tableName);
            } catch (Exception $e) {
                throw new Exception("There has been an error processing the file. Please try after some time.");
            }
            
            if (empty($table)) {
                throw new Exception("Table doesn't exists");
            }
            
            $this->findConstraints($table);
            
            $this->getCreateTableSql($table);
            
            $compositePrimaryKeyCols = [];
            
            $addForeignKeys = "";
            $dropForeignKeys = "";
            $up = "";
            
            $this->table = $this->getTableName($table);
            
            foreach ($table->columns as $col) {
                $up .= "\t\t\t" . '\'' . $col->name . '\'=>"' . $this->getColType($col) . '",' . "\n";
                if ($col->isPrimaryKey) {
                    // Add column to composite primary key array
                    $compositePrimaryKeyCols[] = $col->name;
                }
            }
            
            $ukeys = $this->db->schema->findUniqueIndexes($table);
            if (!empty($ukeys)) {
                $indexArr = [];
                foreach ($ukeys as $key => $value) {
                    $indexKey = $key;
                    foreach ($value as $id => $field) {
                        $indexArr[] = $field;
                    }
                }
                $indexStr = implode(',', $indexArr);
                $up .= "\t\t" . '$this->createIndex(\'idx_' . $indexKey . "', '" . $this->getTableName($table) . "', '$indexStr', TRUE);\n";
                
            }
            
            if (!empty($table->foreignKeys)):
                
                foreach ($table->foreignKeys as $fkName => $fk) {
                    $addForeignKeys .= "\t\t" . '$this->addForeignKey("' . $fkName . '", "' . $table->name . '", "' . $fk['column'] . '","' . $fk['table'] . '","' . $fk['ref_column'] . '", "' . $fk['delete'] . '", "' . $fk['update'] . '");' . "\n";
                    $dropForeignKeys .= '$this->dropForeignKey(' . "'$fkName', '$table->name');";
                }
            
            endif;
            
            $this->fields = $table->columns;
            
            $fields = $this->parseFields();
            
            $this->prepareFile([
                'up'          => '',
                'down'        => '',
                'foreignKeys' => $table->foreignKeys,
                'fields'      => $fields,
                'indexes'     => $this->findIndexes($table),
            ]) . "\n\n";
            
        }
    }
    
    /**
     * @param $table
     *
     * @return array
     * @throws \yii\db\Exception
     */
    protected function findConstraints($table)
    {
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
            
            $rows = $this->db->createCommand($sql, [':tableName' => $table->name])->queryAll();
            
            $constraints = [];
            $table->foreignKeys = [];
            foreach ($rows as $row) {
                $constraints[$row['constraint_name']]['referenced_table_name'] = $row['referenced_table_name'];
                $constraints[$row['constraint_name']]['columns'][$row['column_name']] = $row['referenced_column_name'];
                
                $table->foreignKeys[$row['constraint_name']]['table'] = $row['referenced_table_name'];
                $table->foreignKeys[$row['constraint_name']]['column'] = $row['column_name'];
                $table->foreignKeys[$row['constraint_name']]['ref_column'] = $row['referenced_column_name'];
                $table->foreignKeys[$row['constraint_name']]['delete'] = $row['DELETE_RULE'];
                $table->foreignKeys[$row['constraint_name']]['update'] = $row['UPDATE_RULE'];
            }
            
            return $constraints;
        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            if (!$previous instanceof \PDOException || strpos($previous->getMessage(), 'SQLSTATE[42S02') === FALSE) {
                throw $e;
            }
            
            // table does not exist, try to determine the foreign keys using the table creation sql
            $sql = $this->getCreateTableSql($table);
            $regexp = '/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
            if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fks = array_map('trim', explode(',', str_replace('`', '', $match[1])));
                    $pks = array_map('trim', explode(',', str_replace('`', '', $match[3])));
                    $constraint = [str_replace('`', '', $match[2])];
                    foreach ($fks as $k => $name) {
                        $constraint[$name] = $pks[$k];
                    }
                    $table->foreignKeys[md5(serialize($constraint))] = $constraint;
                }
                $table->foreignKeys = array_values($table->foreignKeys);
            }
        }
    }
    
    /**
     * Gets the CREATE TABLE sql string.
     *
     * @param \yii\db\TableSchema $table
     *
     * @return mixed
     * @throws \yii\db\Exception
     */
    protected function getCreateTableSql(TableSchema $table)
    {
        $row = $this->db->createCommand('SHOW CREATE TABLE ' . $this->quoteTableName($table->fullName))->queryOne();
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }
        return $sql;
    }
    
    /**
     * @param $name
     *
     * @return string
     */
    public function quoteTableName($name)
    {
        return strpos($name, '`') !== FALSE ? $name : "`$name`";
    }
    
    /**
     * @inheritdoc
     */
    protected function parseFields()
    {
        $fields = [];
        
        foreach ($this->fields as $column => $schema) {
            $chunks = [];
            
            $columns = $this->formatCol($schema);
            
            if (is_string($columns)) {
                
                $fields[] = [
                    'raw' => "'{$column}' => \"{$this->getColType($schema)}\" ",
                ];
                
                continue;
            }
            
            foreach ($columns as $key => $chunk) {
                if (!preg_match('/^(.+?)\(([^)]+)\)$/', $chunk)) {
                    $chunk .= '()';
                }
                $chunks[] = $chunk;
            }
            
            $fields[] = [
                'property'   => $column,
                'decorators' => implode('->', $chunks),
            ];
        }
        return $fields;
    }
    
    /**
     * Gets all indexes for defined table
     *
     * @param \yii\db\TableSchema $table
     *
     * @return array|void
     */
    protected function findIndexes(TableSchema $table)
    {
        $sql = "SHOW INDEX FROM `{$table->name}`";
        
        try {
            
            $query = $this->db->createCommand($sql, [':tableName' => $table->name]);
            
            $rows = $query->queryAll();
            
            if (empty($rows)) {
                return [];
            }
            
            return $this->filterKeyIndex($rows, $table);
        } catch (\Exception $e) {
            return;
        }
    }
    
    /**
     * @param array $rows
     * @param       $table
     *
     * @return array
     */
    public function filterKeyIndex(array $rows = [], $table)
    {
        $return = [];
        foreach ($rows as $row) {
            
            //  is already set the key just add the columns.
            if (isset($return[$row['Key_name']])) {
                
                if (is_string($return[$row['Key_name']]['Column_name'])) {
                    $return[$row['Key_name']]['Column_name'] = [$return[$row['Key_name']]['Column_name']];
                }
                
                $return[$row['Key_name']]['Column_name'][] = $row['Column_name'];
                continue;
            }
            
            //  is primary
            if ($row['Key_name'] === 'PRIMARY') {
                continue;
            }
            
            // is found in foreign keys.
            if (array_key_exists($row['Key_name'], $table->foreignKeys)) {
                continue;
            }
            
            
            $return[$row['Key_name']] = $row;
        }
        
        return $return;
    }
    
    /**
     * @inheritdoc
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, TRUE) === NULL) {
            $this->createMigrationHistoryTable();
        }
        $query = new Query;
        $rows = $query->select(['version', 'apply_time'])
            ->from($this->migrationTable)
            ->orderBy('apply_time DESC, version DESC')
            ->limit($limit)
            ->createCommand($this->db)
            ->queryAll();
        $history = ArrayHelper::map($rows, 'version', 'apply_time');
        unset($history[self::BASE_MIGRATION]);
        
        return $history;
    }
    
    /**
     * @inheritdoc
     */
    protected function addMigrationHistory($version)
    {
        $command = $this->db->createCommand();
        $command->insert($this->migrationTable, [
            'version'    => $version,
            'apply_time' => time(),
        ])->execute();
    }
    
    /**
     * @inheritdoc
     */
    protected function removeMigrationHistory($version)
    {
        $command = $this->db->createCommand();
        $command->delete($this->migrationTable, [
            'version' => $version,
        ])->execute();
    }
}