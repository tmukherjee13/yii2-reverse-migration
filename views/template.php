<?php
/**
 * This view is used by tmukherjee13/migration/MigrationController.php
 * The following variables are available in this view:
 */
/* @var $className string the new migration class name */
/* @var $up string the statements to be executed on migrate/up */
/* @var $down string the statements to be executed on migrate/down */

echo "<?php\n";
?>

use yii\db\Schema;
use yii\db\Migration;

class <?= $className ?> extends Migration
{

    public function safeUp()
    {
        <?= $up ?>
    }
    
    public function safeDown()
    {
        <?= $down ?>
    }

    /*
    public function up()
    {

    }

    public function down()
    {
        echo "<?= $className ?> cannot be reverted.\n";

        return false;
    }
    */
    
}
