<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class ChatRecords extends AbstractMigration
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
        $table = $this->table('chat_records', ['collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('from_user_type', 'integer', ['limit' => MysqlAdapter::INT_TINY])
            ->addColumn('to_user_type', 'integer', ['limit' => MysqlAdapter::INT_TINY])
            ->addColumn('from_id', 'biginteger')
            ->addColumn('to_id', 'biginteger')
            ->addColumn('content', 'text')
            ->addColumn('to_read_at', 'timestamp', ['null' => true])
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->addTimestamps('created_at', null)
            ->addIndex(['from_user_type', 'from_id', 'to_id'], ['name' => 'from_f_t'])
            ->addIndex(['to_user_type', 'from_id', 'to_id'], ['name' => 'to_f_t'])
            ->create();
    }
}
