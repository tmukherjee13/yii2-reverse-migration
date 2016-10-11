<?php
/**
 * This view is used by console/controllers/MigrateController.php
 * The following variables are available in this view:
 */
/* @var $className string the new migration class name */
/* @var $table string the name table */
/* @var $fields array the fields */
/* @var $foreignKeys array the foreign keys */

echo "<?php\n";
?>

use yii\db\Migration;

/**
 * Handles the data insertion for table `<?= $table ?>`.
 */
class <?= $className ?> extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        <?= $this->render('_insertTable', [
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
        ])
        ?>
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        <?= $this->render('_truncateTable', [
            'table' => $table,
        ])
        ?>
    }
}
