<?php


use Phinx\Seed\AbstractSeed;
use SuperPig\EedoCustomerService\enum\admin\State;

class AdminSeeder extends AbstractSeed
{
    /**
     * Run Method.
     * Write your database seeder using this method.
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run()
    {
        $data = [
            [
                'account'  => 'admin',
                'password' => password_hash('admin888', PASSWORD_DEFAULT),
                'state'    => State::NORMAL,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
        ];

        $admin = $this->table('admins');
        $admin->insert($data)->save();
    }
}
