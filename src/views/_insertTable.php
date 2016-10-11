<?php

/**
 * Creates a call for the method `yii\db\Migration::createTable()`
 */
/* @var $table string the name table */
/* @var $fields array the fields */
/* @var $foreignKeys array the foreign keys */

?>

$this->batchInsert('<?= $table ?>', <?= $columns ?>, <?= $rows ?>);
