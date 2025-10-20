<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $departments = [
            ['id' => 98, 'name' => 'ΠΛΗΡΟΦΟΡΙΚΗΣ ΚΑΙ ΤΗΛΕΠΙΚΟΙΝΩΝΙΩΝ', 'city_id' => 1, 'email' => 'dit-secr@uop.gr'],
            ['id' => 104, 'name' => 'ΙΣΤΟΡΙΑΣ ΑΡΧΑΙΟΛΟΓΙΑΣ ΚΑΙ ΔΙΑΧΕΙΡΙΣΗΣ ΠΟΛΙΤΙΣΜΙΚΩΝ ΑΓΑΘΩΝ', 'city_id' => 3, 'email' => 'hamcc-secr@uop.gr'],
            ['id' => 187, 'name' => 'ΚΟΙΝΩΝΙΚΗΣ ΚΑΙ ΕΚΠΑΙΔΕΥΤΙΚΗΣ ΠΟΛΙΤΙΚΗΣ', 'city_id' => 6, 'email' => 'sep-secr@uop.gr'],
            ['id' => 189, 'name' => 'ΦΙΛΟΛΟΓΙΑΣ', 'city_id' => 3, 'email' => 'phil-secr@uop.gr'],
            ['id' => 190, 'name' => 'ΝΟΣΗΛΕΥΤΙΚΗΣ', 'city_id' => 1, 'email' => 'nrsgram@uop.gr'],
            ['id' => 361, 'name' => 'ΟΙΚΟΝΟΜΙΚΩΝ ΕΠΙΣΤΗΜΩΝ', 'city_id' => 1, 'email' => 'econ@uop.gr'],
            ['id' => 362, 'name' => 'ΘΕΑΤΡΙΚΩΝ ΣΠΟΥΔΩΝ', 'city_id' => 5, 'email' => 'ts-secretary@uop.gr'],
            ['id' => 400, 'name' => 'ΟΡΓΑΝΩΣΗΣ ΚΑΙ ΔΙΑΧΕΙΡΙΣΗΣ ΑΘΛΗΤΙΣΜΟΥ', 'city_id' => 2, 'email' => 'toda@go.uop.gr'],
            ['id' => 411, 'name' => 'ΠΟΛΙΤΙΚΗΣ ΕΠΙΣΤΗΜΗΣ ΚΑΙ ΔΙΕΘΝΩΝ ΣΧΕΣΕΩΝ', 'city_id' => 6, 'email' => 'pedis@uop.gr'],
            ['id' => 1511, 'name' => 'ΓΕΩΠΟΝΙΑΣ', 'city_id' => 3, 'email' => 'agro-secr@uop.gr'],
            ['id' => 1512, 'name' => 'ΕΠΙΣΤΗΜΗΣ ΚΑΙ ΤΕΧΝΟΛΟΓΙΑΣ ΤΡΟΦΙΜΩΝ', 'city_id' => 3, 'email' => 'fst-secr@uop.gr'],
            ['id' => 1513, 'name' => 'ΛΟΓΙΣΤΙΚΗΣ ΚΑΙ ΧΡΗΜΑΤΟΟΙΚΟΝΟΜΙΚΗΣ', 'city_id' => 3, 'email' => 'chrime@go.uop.gr'],
            ['id' => 1514, 'name' => 'ΔΙΟΙΚΗΣΗΣ ΕΠΙΧΕΙΡΗΣΕΩΝ ΚΑΙ ΟΡΓΑΝΙΣΜΩΝ', 'city_id' => 3, 'email' => 'boa-secr@uop.gr'],
            ['id' => 1515, 'name' => 'ΛΟΓΟΘΕΡΑΠΕΙΑΣ', 'city_id' => 3, 'email' => 'gramlogo@uop.gr'],
            ['id' => 1516, 'name' => 'ΕΠΙΣΤΗΜΗΣ ΔΙΑΤΡΟΦΗΣ ΚΑΙ ΔΙΑΙΤΟΛΟΓΙΑΣ', 'city_id' => 3, 'email' => 'nds-secr@uop.gr'],
            ['id' => 1517, 'name' => 'ΠΑΡΑΣΤΑΤΙΚΩΝ ΚΑΙ ΨΗΦΙΑΚΩΝ ΤΕΧΝΩΝ', 'city_id' => 5, 'email' => 'pda-secr@uop.gr'],
            ['id' => 1518, 'name' => 'ΔΙΟΙΚΗΤΙΚΗΣ ΕΠΙΣΤΗΜΗΣ ΚΑΙ ΤΕΧΝΟΛΟΓΙΑΣ', 'city_id' => 1, 'email' => 'det@uop.gr'],
            ['id' => 1519, 'name' => 'ΨΗΦΙΑΚΩΝ ΣΥΣΤΗΜΑΤΩΝ', 'city_id' => 2, 'email' => 'ds-secr@uop.gr'],
            ['id' => 1520, 'name' => 'ΦΥΣΙΚΟΘΕΡΑΠΕΙΑΣ', 'city_id' => 2, 'email' => 'pthgram@uop.gr'],
            ['id' => 1522, 'name' => 'ΗΛΕΚΤΡΟΛΟΓΩΝ ΜΗΧΑΝΙΚΩΝ ΚΑΙ ΜΗΧΑΝΙΚΩΝ ΥΠΟΛΟΓΙΣΤΩΝ', 'city_id' => 4, 'email' => 'ece-secr@uop.gr'],
            ['id' => 1523, 'name' => 'ΜΗΧΑΝΟΛΟΓΩΝ ΜΗΧΑΝΙΚΩΝ', 'city_id' => 4, 'email' => 'mech-secr@uop.gr'],
            ['id' => 1524, 'name' => 'ΠΟΛΙΤΙΚΩΝ ΜΗΧΑΝΙΚΩΝ', 'city_id' => 4, 'email' => 'civil-secr@uop.gr'],
            ['id' => 9999, 'name' => 'ΔΟΚΙΜΑΣΤΙΚΟ ΤΜΗΜΑ', 'city_id' => 4, 'email' => 'ktsouvalis@uop.gr'],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}