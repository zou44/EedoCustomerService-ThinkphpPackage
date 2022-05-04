<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;
use SuperPig\EedoCustomerService\enum\client_user\State;

final class CustomerServiceAccounts extends AbstractMigration
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
        $table = $this->table('customer_service_accounts', ['collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('account', 'string')
            ->addColumn('password', 'string')
            ->addColumn('info', 'string', ['null' => true])
            ->addColumn('state', 'integer', [
                'limit'   => MysqlAdapter::INT_TINY,
                'default' => State::NORMAL,
            ])
            ->addColumn('extend', 'string', ['null' => true])
            ->addTimestamps('created_at', 'updated_at')
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->create();
    }
}
