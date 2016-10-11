<?php foreach ($foreignKeys as $fk => $fkData): ?>
        // drops foreign key for table `<?= $fkData['table'] ?>`
        $this->dropForeignKey(
            '<?= $fk ?>',
            '<?= $table ?>'
        );

        // drops index for column `<?= $fkData['column'] ?>`
        $this->dropIndex(
            '<?= $fk ?>',
            '<?= $table ?>'
        );

<?php endforeach;
