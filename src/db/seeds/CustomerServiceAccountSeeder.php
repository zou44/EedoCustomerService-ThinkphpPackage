<?php


use Phinx\Seed\AbstractSeed;
use SuperPig\EedoCustomerService\enum\customer_service_account\State;

class CustomerServiceAccountSeeder extends AbstractSeed
{
    /**
     * Run Method.
     * Write your database seeder using this method.
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run()
    {
        $customerServiceAccount = $this->table('customer_service_accounts');
        $customerServiceAccount->insert(
            array(
                array(
                    'id'         => 1,
                    'account'    => 'user1',
                    'password'   => password_hash('12345678', PASSWORD_DEFAULT),
                    'state'      => State::NORMAL,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                )
            )
        )->save();

        $customerServiceInfo = $this->table('customer_service_info');
        $customerServiceInfo->insert(
            array(
                array(
                    'cs_id'         => 1,
                    'nickname'    => '测试用户',
                    'intro'       => 'eedo客服系统',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                )
            )
        )->save();
    }
}
