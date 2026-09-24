<?php

return [
    'section' => [
        'title' => 'Vozila',
        'empty' => 'Još nema evidentiranih vozila.',
        'add' => 'Dodaj vozilo',
        'add_for' => 'Dodaj vozilo za :name',

        'headers' => [
            'plate' => 'Tablice',
            'vehicle' => 'Vozilo',
            'year' => 'Godište',
            'actions' => 'Radnje',
        ],
    ],

    'deleted' => [
        'title' => 'Obrisana vozila',
        'intro' => 'Ova vozila su skrivena svuda drugde. Njihove registarske oznake ostaju rezervisane dok se vozila ne vrate.',
        'restore' => 'Vrati',
        'restore_vehicle' => 'Vrati :plate',
        'confirm' => 'Vratiti :plate? Vozilo se vraća ovom klijentu sa netaknutim tablicama i istorijom.',
    ],

    'show' => [
        'details' => 'Detalji vozila',
        'owner' => 'Vlasnik',
        'year' => 'Godište',
        'engine' => 'Motor',
        'vin' => 'Broj šasije (VIN)',
        'mileage' => 'Kilometraža',
        'notes' => 'Napomene',
        'mileage_value' => ':mileage km',
        'history' => 'Servisna istorija',
        'history_empty' => 'Još nema radnih naloga za ovo vozilo.',
        'history_view_all_for' => 'Prikaži sve radne naloge za :plate (:count)',
    ],

    'create' => [
        'title' => 'Novo vozilo',
        'for' => 'Za :name',
        'submit' => 'Dodaj vozilo',
    ],

    'edit' => [
        'title' => 'Izmeni :plate',
        'submit' => 'Sačuvaj izmene',
    ],

    'picker' => [
        'option' => ':plate · :vehicle · :owner',
        'owner_deleted' => ':name (obrisan)',
    ],

    'form' => [
        'owner' => 'Vlasnik',
        'registration_plate' => 'Registarska oznaka',
        'make' => 'Marka',
        'model' => 'Model',
        'year' => 'Godište (opciono)',
        'vin' => 'Broj šasije (VIN, opciono)',
        'engine' => 'Motor (opciono)',
        'mileage' => 'Kilometraža (opciono)',
        'notes' => 'Napomene (opciono)',
    ],

    'delete' => [
        'action' => 'Obriši :plate',
        'confirm' => 'Obrisati :plate? Registarska oznaka ostaje rezervisana i ne može se koristiti za drugo vozilo dok se ovo ne vrati.',
    ],

    'reassign' => [
        'action' => 'Prenesi',
        'action_vehicle' => 'Prenesi :plate na drugog klijenta',
        'title' => 'Prenos vozila :plate',
        'current_owner' => 'Trenutni vlasnik',
        'new_owner' => 'Novi vlasnik',
        'placeholder' => 'Izaberite klijenta',
        'warning_title' => 'Istorija se čuva',
        'warning' => 'Menja se samo vlasnik vozila. Postojeći radni nalozi i računi zadržavaju klijenta :name, tako da se prethodni rad nikada ne fakturiše ponovo.',
        'submit' => 'Prenesi vozilo',
        'confirm' => 'Preneti vozilo :plate sa vlasnika :name na klijenta :selected? Postojeći radni nalozi i računi zadržavaju klijenta :name.',
    ],

    'validation' => [
        'plate_deleted' => 'Ta registarska oznaka pripada obrisanom vozilu čiji je vlasnik :owner. Administrator ga može vratiti sa stranice tog klijenta.',
        'vin_deleted' => 'Taj broj šasije pripada obrisanom vozilu čiji je vlasnik :owner. Administrator ga može vratiti sa stranice tog klijenta.',
        'already_owner' => 'Taj klijent je već vlasnik ovog vozila.',
    ],

    'flash' => [
        'created' => 'Vozilo :plate je dodato.',
        'updated' => 'Vozilo :plate je izmenjeno.',
        'deleted' => 'Vozilo :plate je obrisano. Njegova registarska oznaka ostaje rezervisana.',
        'restored' => 'Vozilo :plate je vraćeno.',
        'reassigned' => 'Vozilo :plate sada pripada klijentu :name.',
    ],
];
