<?php

/**
 * Creates a call for the method `yii\db\Migration::createTable()`
 */
/* @var $table string the name table */
/* @var $indexes array the foreign keys */

//dd( $indexes );

if($indexes){
        foreach ($indexes as $index):
        if(is_string($index['Column_name'])): ?>
        <?php if($index['Non_unique'] == 0): ?>
                $this->createIndex('<?= str_replace('_UNIQUE', '', $index['Key_name'])?>', $this->tableName, '<?= $index['Column_name'] ?>', true);
        <?php else: ?>
                $this->createIndex('<?= $index['Key_name']  ?>', $this->tableName, '<?= $index['Column_name'] ?>');
        <?php endif; ?>
        <?php else: ?>
                $this->createIndex('<?= $index['Key_name']  ?>', $this->tableName, ['<?= implode("','",$index['Column_name']) ?>']);
        <?php endif; ?>
        <?php endforeach;
}
