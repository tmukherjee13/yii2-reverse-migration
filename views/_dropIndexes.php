<?php
/* @var $table string the name table */
/* @var $indexes array the foreign keys */

if($indexes){
        foreach ($indexes as $key => $index): ?>
                $this->dropIndex('<?= $key ?>', $this->tableName);
        <?php endforeach;
}