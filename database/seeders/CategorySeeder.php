<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'id' => 1,
            'name' => '13.00 - ΛΕΩΦΟΡΕΙΑ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 2,
            'name' => '13.01 - ΛΟΙΠΑ ΕΠΙΒΑΤΙΚΑ ΑΥΤΟΚΙΝΗΤΑ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 3,
            'name' => '14.00 - ΕΠΙΠΛΑ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 4,
            'name' => '14.02 - ΣΚΕΥΗ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 5,
            'name' => '14.03 - Η/Υ ΚΑΙ ΗΛΕΚΤΡΟΝΙΚΑ ΣΥΓΚΡΟΤΗΜΑΤΑ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 6,
            'name' => '14.04 - ΜΕΣΑ ΑΠΟΘΗΚΕΥΣΕΩΣ ΚΑΙ ΜΕΤΑΦΟΡΑΣ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 7,
            'name' => '14.05 - ΕΠΙΣΤΗΜΟΝΙΚΑ ΟΡΓΑΝΑ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 8,
            'name' => '14.08 - ΕΞΟΠΛΙΣΜΟΣ ΤΗΛΕΠΙΚΟΙΝΩΝΙΩΝ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 9,
            'name' => '14.09 - ΛΟΙΠΟΣ ΕΞΟΠΛΙΣΜΟΣ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 10,
            'name' => '14.30 - ΕΡΓΑ ΤΕΧΝΗΣ - ΚΕΙΜΗΛΙΑ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 11,
            'name' => '16.17 ΛΟΓΙΣΜΙΚΑ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Category::create([
            'id' => 12,
            'name' => '99.99 ΔΕΝ ΠΕΡΙΓΡΑΦΕΤΑΙ ΠΑΡΑΠΑΝΩ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
