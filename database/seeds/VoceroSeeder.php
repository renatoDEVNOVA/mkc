<?php

use Illuminate\Database\Seeder;

class VoceroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        factory(App\Vocero::class,10)->create();
    }
}
