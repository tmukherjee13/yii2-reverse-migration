<?php
/**
 * This view is used by console/controllers/MigrateController.php
 * The following variables are available in this view:
 */
/* @var $className string the new migration class name */
/* @var $table string the name table */
/* @var $fields array the fields */
/* @var $foreignKeys array the foreign keys */
/* @var $indexes array the foreign keys */

echo "<?php\n";
?>

use yii\db\Migration;

/**
 * Handles the creation for table `<?= $table ?>`.
 */
class <?= $className ?> extends Migration
{

    /** @var string  */
    protected $tableName = '<?=$table?>';

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $collation = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $collation = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

<?= $this->render('_createTable', [
    'table' => $table,
    'fields' => $fields,
    'foreignKeys' => $foreignKeys,
    'indexes' => $indexes,
])
?>

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
<?= $this->render('_dropTable', [
    'table' => $table,
    'foreignKeys' => $foreignKeys,
    'indexes' => $indexes,
])
?>
    }
}
