<?php

use Illuminate\Database\Seeder;

class MedioPlataformaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        factory(App\MedioPlataforma::class,15)->create();
    }
}
