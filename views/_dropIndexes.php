<?php
/* @var $table string the name table */
/* @var $indexes array the foreign keys */

foreach ($indexes as $key => $index): ?>
        $this->dropIndex('<?= $key ?>', $this->tableName);
<?php endforeach;
