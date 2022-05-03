<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;
use SuperPig\EedoCustomerService\enum\client_user\State;

final class ClientUsers extends AbstractMigration
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
        $table = $this->table('client_users', ['collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('uuid', 'string', ['length' => '26'])
            ->addColumn('info', 'string', ['null' => true])
            ->addColumn('state', 'integer', ['limit' => MysqlAdapter::INT_SMALL, 'default' => State::NORMAL])
            ->addColumn('extend', 'string', ['null' => true])
            ->addTimestamps('created_at', 'updated_at')
            ->addIndex('uuid', ['unique' => true])
            ->create();
    }
}
