<?php

return [
    'section' => [
        'title' => 'Vehicule',
        'empty' => 'Niciun vehicul înregistrat încă.',
        'add' => 'Adaugă vehicul',
        'add_for' => 'Adaugă vehicul pentru :name',

        'headers' => [
            'plate' => 'Nr. înmatr.',
            'vehicle' => 'Vehicul',
            'year' => 'An',
            'actions' => 'Acțiuni',
        ],
    ],

    'deleted' => [
        'title' => 'Vehicule șterse',
        'intro' => 'Aceste vehicule sunt ascunse în rest. Numerele lor de înmatriculare rămân rezervate până la restaurare.',
        'restore' => 'Restaurează',
        'restore_vehicle' => 'Restaurează :plate',
        'confirm' => 'Restaurați :plate? Vehiculul revine la acest client cu numărul de înmatriculare și istoricul intacte.',
    ],

    'show' => [
        'details' => 'Detalii vehicul',
        'owner' => 'Proprietar',
        'year' => 'An',
        'engine' => 'Motor',
        'vin' => 'Serie șasiu (VIN)',
        'mileage' => 'Kilometraj',
        'notes' => 'Observații',
        'mileage_value' => ':mileage km',
        'history' => 'Istoric service',
        'history_empty' => 'Nicio lucrare înregistrată încă pentru acest vehicul.',
        'history_view_all_for' => 'Vezi toate lucrările (:count) pentru :plate',
    ],

    'create' => [
        'title' => 'Vehicul nou',
        'for' => 'Pentru :name',
        'submit' => 'Adaugă vehiculul',
    ],

    'edit' => [
        'title' => 'Editează :plate',
        'submit' => 'Salvează modificările',
    ],

    'picker' => [
        'option' => ':plate · :vehicle · :owner',
        'owner_deleted' => ':name (șters)',
    ],

    'form' => [
        'owner' => 'Proprietar',
        'registration_plate' => 'Număr de înmatriculare',
        'make' => 'Marcă',
        'model' => 'Model',
        'year' => 'An (opțional)',
        'vin' => 'Serie șasiu (VIN, opțional)',
        'engine' => 'Motor (opțional)',
        'mileage' => 'Kilometraj (opțional)',
        'notes' => 'Observații (opțional)',
    ],

    'delete' => [
        'action' => 'Șterge :plate',
        'confirm' => 'Ștergeți :plate? Numărul de înmatriculare rămâne rezervat și nu poate fi folosit pentru alt vehicul până la restaurarea acestuia.',
    ],

    'reassign' => [
        'action' => 'Reasignează',
        'action_vehicle' => 'Reasignează :plate altui client',
        'title' => 'Reasignare :plate',
        'current_owner' => 'Proprietar actual',
        'new_owner' => 'Proprietar nou',
        'placeholder' => 'Alegeți un client',
        'warning_title' => 'Istoricul se păstrează',
        'warning' => 'Se mută doar vehiculul. Lucrările și facturile existente păstrează clientul :name, așa că lucrările anterioare nu sunt refacturate niciodată.',
        'submit' => 'Reasignează vehiculul',
        'confirm' => 'Reasignați :plate de la :name la :selected? Lucrările și facturile existente păstrează clientul :name.',
    ],

    'validation' => [
        'plate_deleted' => 'Acest număr de înmatriculare aparține unui vehicul șters al clientului :owner. Un administrator îl poate restaura din pagina acelui client.',
        'vin_deleted' => 'Această serie de șasiu aparține unui vehicul șters al clientului :owner. Un administrator îl poate restaura din pagina acelui client.',
        'already_owner' => 'Acest client deține deja vehiculul.',
    ],

    'flash' => [
        'created' => 'Vehiculul :plate a fost adăugat.',
        'updated' => 'Vehiculul :plate a fost actualizat.',
        'deleted' => 'Vehiculul :plate a fost șters. Numărul de înmatriculare rămâne rezervat.',
        'restored' => 'Vehiculul :plate a fost restaurat.',
        'reassigned' => ':plate aparține acum clientului :name.',
    ],
];
