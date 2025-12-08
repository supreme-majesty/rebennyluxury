<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminTable extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('admins')->insert([
            'id' => 1,
            'name' => 'Super Admin',
            'phone' => '0542661103',
            'email' => 'admin@gmail.com',
            'admin_role_id' => 1,
            'image' => 'def.png',
            'password' => bcrypt('S1sc077a'),
            'remember_token' =>Str::random(10),
        ]);
    }
}
