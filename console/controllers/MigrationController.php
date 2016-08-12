<?php
namespace tmukherjee13\migration\console\controllers;

use tmukherjee13\migration\console\components\Formatter;
use Yii;
use yii\console\controllers\BaseMigrateController;
use yii\console\Exception;
use yii\db\Connection;

// use yii\console\controllers\BaseMigrateController as Migrate;
use yii\helpers\Console;
use yii\helpers\FileHelper;

class MigrationController extends BaseMigrateController
{

    /**
     * @inheritdoc
     */
    public $defaultAction = 'migrate';

    /**
     * @var string a migration table name
     */
    public $migrationTable = 'migration';

    /**
     * @var string a migration path
     */
    public $migrationPath = "@app/migrations";

    public $templateFile = "@tmukherjee13/migration";

    /**
     * @var string class name
     */
    protected $class = "";

    /**
     * @var string table name
     */
    protected $table = "";

    /**
     * @var string table columns
     */
    protected $fields = "";

    /**
     * @var string file name
     */
    protected $fileName = '';

    /**
     * @var Connection|string the DB connection object or the application component ID of the DB connection.
     */
    public $db = 'db';

    public $test = "";

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

            $path = Yii::getAlias($this->migrationPath);
            if (!is_dir($path)) {
                if ($action->id !== 'table') {

                    throw new Exception("Migration failed. Directory specified in migrationPath doesn't exist: {$this->migrationPath}");
                }
                FileHelper::createDirectory($path);
            }
            $this->migrationPath = $path;

            $version = Yii::getVersion();
            $this->stdout("Yii Database Migration Tool (based on Yii v{$version})\n", Console::FG_YELLOW);
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
    private function type($type)
    {
        $class = new \ReflectionClass('yii\db\Schema');
        return $class->getShortName() . '::' . implode(array_keys($class->getConstants(), $type));
    }

    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
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

            // $rows = $this->db->createCommand($sql, [':tableName' => $table->name])->queryAll();
            $rows = $this->db->createCommand($sql, [':tableName' => $table->name])->queryAll();

            $constraints        = [];
            $table->foreignKeys = [];
            foreach ($rows as $row) {
                $constraints[$row['constraint_name']]['referenced_table_name']        = $row['referenced_table_name'];
                $constraints[$row['constraint_name']]['columns'][$row['column_name']] = $row['referenced_column_name'];

                // $table->foreignKeys[$row['constraint_name']]['name'] = $row['constraint_name'];
                $table->foreignKeys[$row['constraint_name']]['table']      = $row['referenced_table_name'];
                $table->foreignKeys[$row['constraint_name']]['column']     = $row['column_name'];
                $table->foreignKeys[$row['constraint_name']]['ref_column'] = $row['referenced_column_name'];
                $table->foreignKeys[$row['constraint_name']]['delete']     = $row['DELETE_RULE'];
                $table->foreignKeys[$row['constraint_name']]['update']     = $row['UPDATE_RULE'];
            }
            // $table->foreignKeys = [];
            // foreach ($constraints as $constraint) {
            //     $table->foreignKeys[] = array_merge([$constraint['referenced_table_name']], $constraint['columns']);
            // }
            return $constraints;
        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            if (!$previous instanceof \PDOException || strpos($previous->getMessage(), 'SQLSTATE[42S02') === false) {
                throw $e;
            }

            // table does not exist, try to determine the foreign keys using the table creation sql
            $sql    = $this->getCreateTableSql($table);
            $regexp = '/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
            if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fks        = array_map('trim', explode(',', str_replace('`', '', $match[1])));
                    $pks        = array_map('trim', explode(',', str_replace('`', '', $match[3])));
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
     * @param TableSchema $table the table metadata
     * @return string $sql the result of 'SHOW CREATE TABLE'
     */
    protected function getCreateTableSql($table)
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

    public function quoteTableName($name)
    {
        return strpos($name, '`') !== false ? $name : "`$name`";
    }

    /**
     * Creates migration based on table
     * @method actionTable
     * @param  array       $tables Name of the table to create migration
     * @return mixed
     * @author Tarun Mukherjee (https://github.com/tmukherjee13)
     */

    public function actionTable(array $tables)
    {

        if ($this->confirm('Create the migration ' . "?")) {

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
                $tsch = $this->findConstraints($table);

                $sql = $this->getCreateTableSql($table);
                // $constraint = [];

                $hasPrimaryKey           = false;
                $compositePrimaryKeyCols = array();

                $addForeignKeys  = "";
                $dropForeignKeys = "";
                $up              = "";
                $down            = "";
                $name            = $this->getFileName();

                $this->table = $this->getTableName($table);

                foreach ($table->columns as $col) {
                    $up .= "\t\t\t" . '\'' . $col->name . '\'=>"' . Formatter::getColType($col) . '",' . "\n";
                    if ($col->isPrimaryKey) {
                        $hasPrimaryKey = true;
                        // Add column to composite primary key array
                        $compositePrimaryKeyCols[] = $col->name;
                    }
                }

                $ukeys = $this->db->schema->findUniqueIndexes($table);
                if (!empty($ukeys)) {
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

                $this->prepareFile(['up' => '', 'down' => '', 'foreignKeys' => $table->foreignKeys, 'fields' => $fields]) . "\n\n";

            }

        }
        return;
    }

    /**
     * Returns the name of the data migration file created
     * @param string $args the table name
     * @return string
     */
    public function actionData(array $tables)
    {

        if ($this->confirm('Create the migration ' . "?", true)) {
            foreach ($tables as $key => $args) {

                $table       = $args;
                $this->class = 'insert_data_into_' . $table;
                $this->table = $table;
                $this->templateFile = '@tmukherjee13/migration/views/dataTemplate.php';

                $columns = $this->db->getTableSchema($table);
                $prefix  = $this->db->tablePrefix;
                $table   = str_replace($prefix, '', $table);
                $table   = $columns;

                $this->table = $this->getTableName($table);

                $prepared_columns = '';
                $up               = '';
                $down             = '';
                $prepared_data    = [];

                $name = $this->getFileName();

                if (!empty($table)) {
                    $data = $this->db->createCommand('SELECT * FROM `' . $table->name . '`')->queryAll();

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
                        $pcolumns   = Formatter::prepareColumns($pcolumns);
                        $prows      = Formatter::prepareData($prepared_data);

                        $this->prepareFile(['columns' => $pcolumns, 'rows' => $prows]);

                        return self::EXIT_CODE_NORMAL;
                       
                    }
                    // return self::EXIT_CODE_ERROR;
                }
            }
        }
    }

    /**
     * Returns the name of the database migration file created
     * @param string $args the schema name
     * @return string
     */
    public function actionSchema($args)
    {

        $schema      = $args;
        $this->class = 'dump_database_' . $schema;

        // $tables = $this->db->schema->getTableNames($schema);

        $tables          = $this->db->schema->getTableSchemas($schema);
        $addForeignKeys  = '';
        $dropForeignKeys = '';
        $up              = '';
        $down            = '';
        $hasPrimaryKey   = false;
        $name            = $this->getFileName();
        $tablePrefix     = $this->db->tablePrefix;
        $generateTables  = [];

        foreach ($tables as $table) {
            if ($table->name === $this->db->tablePrefix . $this->migrationTable) {
                continue;
            }
            $generateTables[] = $table->name;
        }
        $this->actionTable($generateTables);
        // $this->actionData($generateTables);
        return self::EXIT_CODE_NORMAL;

        // $result = "<?php \n\n";
        // $result.= "use yii\db\Schema;\n\n";
        // $result.= "use yii\db\Migration;\n\n";
        // $result.= "class {$name} extends Migration\n\n{\n\n";

        // $result.= "\tpublic function safeUp()\n\t\t{\n";
        //  foreach ($tables as $table) {
        //      if ($table->name === $this->migrationTable) {
        //          continue;
        //      }

        //      $hasPrimaryKey = false;
        //      $compositePrimaryKeyCols = array();

        //      // Create table
        //      $up.= "\t\t\t" . '$this->createTable(\'' . $table->name . '\', array(' . "\n";
        //      foreach ($table->columns as $col) {
        //          $up.= "\t\t\t\t" . '\'' . $col->name . '\'=>"' . $this->getColType($col) . '",' . "\n";

        //          // if ($col->isPrimaryKey && !$col->autoIncrement) {

        //          //     // Add column to composite primary key array
        //          //     $compositePrimaryKeyCols[] = $col->name;
        //          // }
        //          if ($col->isPrimaryKey) {

        //              $hasPrimaryKey = true;

        //              // Add column to composite primary key array
        //              $compositePrimaryKeyCols[] = $col->name;
        //          }
        //      }
        //      if ($hasPrimaryKey):
        //          $up.= "\t\t\t\t" . '\'PRIMARY KEY (' . implode(',', $compositePrimaryKeyCols) . ')\' ' . "\n\t\t" . '    ), \'\');' . "\n";
        //      else:
        //          $up.= "\t\t\t\t" . '), \'\');' . "\n\n";
        //      endif;

        //      // Add foreign key(s) and create indexes
        //      if (!empty($table->foreignKeys)):

        //          foreach ($table->foreignKeys as $col => $fk) {

        //              $fk_attr = array_values($fk);
        //              $fk_keys = array_keys($fk);

        //              // Foreign key naming convention: fk_table_foreignTable_col (max 64 characters)
        //              if ($col == 0):
        //                  $fkName = substr('fk_' . $table->name . '_' . $fk_keys[1] . '_' . $fk_attr[1], 0, 64);
        //              else:
        //                  $fkName = substr('fk_' . $table->name . '_' . $fk_keys[1] . '_' . $fk_attr[1], 0, 64);

        //                  // $fkName = substr('fk_' . $table->name . '_' . $fk[0] . '_' . $col, 0, 64);

        //              endif;

        //              if ($col == 0):
        //                  $addForeignKeys.= '    $this->addForeignKey(' . "'$fkName', '$table->name', '$fk_keys[1]', '$fk[0]', '$fk_attr[1]', 'RESTRICT', 'CASCADE');\n\n";
        //              else:
        //                  $addForeignKeys.= '    $this->addForeignKey(' . "'$fkName', '$table->name', '$fk_keys[1]', '$fk[0]', '$fk_attr[1]', 'RESTRICT', 'CASCADE');\n\n";
        //              endif;

        //              $dropForeignKeys.= "\t" . '$this->dropForeignKey(' . "'$fkName', '$table->name');\n\n";

        //              // Index naming convention: idx_col
        //              if ($col == 0):
        //                  $up.= '$this->createIndex(\'idx_' . $fk[0] . "', '$table->name', '$fk_keys[1]', FALSE);\n\n";
        //              else:
        //                  $up.= '    $this->createIndex(\'idx_' . $fk[0] . "', '$table->name', '$fk_keys[1]', FALSE);\n\n";
        //              endif;
        //          }
        //      endif;

        //      // Add composite primary key for join tables
        //      if ($compositePrimaryKeyCols) {

        //          //$result.= '    $this->addPrimaryKey(\'pk_' . $table->name . "', '$table->name', '" . implode(',', $compositePrimaryKeyCols) . "');\n\n";

        //      }

        //  }

        // $up.= $addForeignKeys;

        //  // This needs to come after all of the tables have been created.
        //  // $result.= "\t\t}\n\n\n";
        //  // $result.= "\tpublic function safeDown()\n\t\t{\n";
        //  $down.= $dropForeignKeys;

        //  // This needs to come before the tables are dropped.
        //  foreach ($tables as $table) {
        //      if ($table->name === $this->migrationTable) {
        //          continue;
        //      }
        //      $down.= "\t\t\t" . '$this->dropTable(\'' . $table->name . '\');' . "\n";
        //  }
        //  // $result.= "\t\t}\n\n}\n";

        //  // $path = '/var/www/public_html/';
        //  // $fileName = $this->class;
        //  // $filePath = $path . $fileName;
        //  // $file = $this->migrationPath . DIRECTORY_SEPARATOR . $name . '.php';
        //  // $this->prepareFile($result) . "\n\n";
        //  $this->prepareFile(['up'=>$up,'down'=>$down]);
    }

    public function getFileName()
    {
        return 'm' . gmdate('ymd_His') . '_' . $this->class;
    }

    public function setFileName()
    {
        $this->fileName = $this->getFileName();
    }

    public function prepareFile($data)
    {
        $file = $this->migrationPath . DIRECTORY_SEPARATOR . $this->getFileName() . '.php';
        try {

            $data['table']     = $this->table;
            $data['className'] = $this->getFileName();
            $content           = $this->renderFile(Yii::getAlias($this->templateFile), $data);
            file_put_contents($file, $content);
            $this->stdout("\nNew migration {$this->getFileName()} successfully created.\nRun yii migrate to apply migrations.\n", Console::FG_GREEN);
            return $file;
        } catch (Exception $e) {
            throw new Exception("There has been an error processing the file. Please try after some time.");
        }
    }

   

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

    /**
     * @inheritdoc
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $query = new Query;
        $rows  = $query->select(['version', 'apply_time'])
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

    /**
     * @inheritdoc
     */
    protected function parseFields()
    {
        $fields = [];

        foreach ($this->fields as $column => $schema) {
            $chunks = [];

            $columns = Formatter::formatCol($schema);

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
}
