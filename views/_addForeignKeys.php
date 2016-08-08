<?php foreach ($foreignKeys as $fk => $fkData): ?>

        // creates index for column `<?= $fkData['column'] ?>`
        $this->createIndex(
            '<?= $fk  ?>',
            '<?= $table ?>',
            '<?= $fkData['column'] ?>'
        );

        // add foreign key for table `<?= $fkData['table'] ?>`
        $this->addForeignKey(
            '<?= $fk ?>',
            '<?= $table ?>',
            '<?= $fkData['column'] ?>',
            '{{%<?= $fkData['table'] ?>}}',
            '<?= $fkData['ref_column'] ?>',
            'CASCADE'
        );
<?php endforeach;
