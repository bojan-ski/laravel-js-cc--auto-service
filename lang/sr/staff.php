<?php

return [
    'title' => 'Zaposleni',
    'new' => 'Novi zaposleni',
    'count' => ':count zaposleni|:count zaposlena|:count zaposlenih|{0}Nema zaposlenih',
    'search_label' => 'Pretraga zaposlenih',
    'search_placeholder' => 'Pretraži po imenu ili email adresi',

    'tabs' => [
        'label' => 'Filtriraj zaposlene po ulozi',
        'all' => 'Svi',
    ],

    'headers' => [
        'name' => 'Ime',
        'email' => 'Email',
        'role' => 'Uloga',
        'actions' => 'Radnje',
    ],

    'offboarded' => [
        'on' => 'Odjavljen :date',
    ],

    'you' => 'Vi',

    'actions' => [
        'edit' => 'Izmeni :name',
        'offboard' => 'Odjavi',
        'offboard_named' => 'Odjavi :name',
        'restore' => 'Vrati',
        'restore_named' => 'Vrati :name',
    ],

    'create' => [
        'title' => 'Novi zaposleni',
        'submit' => 'Kreiraj nalog',
    ],

    'edit' => [
        'title' => 'Izmeni :name',
        'submit' => 'Sačuvaj izmene',
    ],

    'form' => [
        'name' => 'Ime',
        'email' => 'Email',
        'role' => 'Uloga',
        'role_placeholder' => 'Izaberite ulogu',
        'password' => 'Lozinka',
        'password_confirmation' => 'Potvrdite lozinku',
        'password_optional' => 'Nova lozinka',
        'password_note' => 'Ostavite prazno da biste zadržali trenutnu lozinku.',
        'own_role_note' => 'Ne možete promeniti sopstvenu ulogu niti odjaviti sebe. Obratite se drugom administratoru.',
    ],

    'offboard' => [
        'title' => 'Odjaviti :name?',
        'consequences' => [
            'Više ne može da se prijavi niti da resetuje lozinku',
            'Ime ostaje na svakom radnom nalogu, delu i računu na kome je radio/la',
            'Email adresa ostaje rezervisana',
            'Nalog možete kasnije vratiti',
        ],
        'submit' => 'Odjavi :name',
    ],

    'restore' => [
        'confirm' => 'Vratiti nalog za :name? Ponovo će moći da se prijavi.',
    ],

    'empty' => [
        'title' => 'Nijedan zaposleni ne odgovara filterima',
        'body' => 'Proverite pravopis ili uklonite filtere da biste videli sve.',
        'clear' => 'Ukloni filtere',
    ],

    'validation' => [
        'email_offboarded' => 'Ta adresa pripada korisniku :name, čiji je nalog odjavljen. Vratite taj nalog umesto da kreirate novi.',
    ],

    'flash' => [
        'created' => ':name sada može da se prijavi.',
        'updated' => 'Sačuvano: :name.',
        'offboarded' => 'Nalog korisnika :name je odjavljen.',
        'restored' => 'Nalog korisnika :name je vraćen i ponovo može da se prijavi.',
        'last_admin' => 'Radionica bi tako ostala bez administratora. Prvo dodelite ulogu administratora nekom drugom.',
    ],
];
