<?php

/**
 * Creates a call for the method `yii\db\Migration::createTable()`
 */
/* @var $table string the name table */
/* @var $fields array the fields */
/* @var $foreignKeys array the foreign keys */
/* @var $indexes array the foreign keys */

?>
        $this->createTable($this->tableName, [
    <?php foreach ($fields as $field):
    if (!empty($field['decorators'])): ?>
        <?= "'{$field['property']}' => \$this->{$field['decorators']}" ?>,
    <?php elseif(!empty('raw')): ?>
        <?=$field['raw'] ?>,
    <?php else: ?>
        '<?= $field['property'] ?>',
    <?php endif;
endforeach; ?>
    ], $collation);

<?= $this->render('_addForeignKeys', [
    'table'       => $table,
    'foreignKeys' => $foreignKeys,
]);?>

<?= $this->render('_addIndexes', [
    'table'   => $table,
    'indexes' => $indexes,
])
?>
