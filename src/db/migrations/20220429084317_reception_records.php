<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;
use SuperPig\EedoCustomerService\enum\reception_record\State;

class ReceptionRecords extends AbstractMigration
{
    /**
     * Change Method.
     * Write your reversible migrations using this method.
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('reception_records', ['collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('cs_id', 'biginteger')
            ->addColumn('cu_id', 'biginteger')
            ->addColumn('state', 'integer', [
                'limit'   => MysqlAdapter::INT_SMALL,
                'default' => State::REMOVE,
            ])
            ->addColumn('extend', 'string', ['null' => true])
            ->addTimestamps('created_at', 'updated_at')
            ->addColumn('cu_unread', 'integer', [ 'default' => 0, ])
            ->addColumn('cs_unread', 'integer', [ 'default' => 0, ])
            ->addColumn('cs_last_msg_at', 'timestamp')
            ->addColumn('cu_last_msg_at', 'timestamp')
            ->addIndex(['cs_id', 'cu_id'])
            ->addIndex(['cu_id', 'cs_id'])
            ->create();
    }
}
