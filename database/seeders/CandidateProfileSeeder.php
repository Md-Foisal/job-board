<?php

namespace Database\Seeders;

use Database\Seeders\Demo\Catalogue;
use Database\Seeders\Demo\People;
use Illuminate\Database\Seeder;

class CandidateProfileSeeder extends Seeder
{
    /**
     * People with complete profiles who have not applied for anything
     * yet, from every line of work, including ones no seeded company is
     * hiring for right now.
     */
    public function run(): void
    {
        $families = array_keys(Catalogue::people()['families']);

        foreach (range(1, 10) as $ignored) {
            People::unrenderedCv(People::candidate($families[array_rand($families)]));
        }
    }
}
