<?php

use Phinx\Migration\AbstractMigration;

final class CustomerServiceAccountInfo extends AbstractMigration
{
    /**
     * Change Method.
     * Write your reversible migrations using this method.
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('customer_service_info', ['collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('cs_id', 'biginteger')
            ->addColumn('avatar', 'string', ['null' => true ])
            ->addColumn('nickname', 'string')
            ->addColumn('intro', 'string', ['null' => true ])
            ->addTimestamps('created_at', 'updated_at')
            ->addIndex('cs_id')
            ->create();
    }
}
