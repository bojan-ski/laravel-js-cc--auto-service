<?php

return [

    'title' => 'Brendovi',
    'new' => 'Novi brend',
    'count' => ':count brend|:count brenda|:count brendova|{0}Nema brendova',
    'search_label' => 'Pretraga brendova',
    'search_placeholder' => 'Pretraži po nazivu brenda',

    'tiers' => [
        'aftermarket' => 'Zamenski',
        'original' => 'Originalni',
        'first_fit' => 'Prva ugradnja',
        'genuine' => 'Fabrički original',
    ],

    'headers' => [
        'name' => 'Naziv',
        'tier' => 'Kvalitet',
        'parts' => 'Delovi',
        'actions' => 'Radnje',
    ],

    'picker' => [
        'placeholder' => 'Bez brenda',
        'option' => ':name · :tier',
    ],

    'trashed' => [
        'badge' => 'Obrisan',
    ],

    'create' => [
        'title' => 'Novi brend',
        'submit' => 'Dodaj brend',
    ],

    'edit' => [
        'title' => 'Izmeni :name',
        'action' => 'Izmeni :name',
        'submit' => 'Sačuvaj izmene',
    ],

    'form' => [
        'name' => 'Naziv',
        'quality_tier' => 'Klasa kvaliteta',
        'tier_note' => 'Klasa opisuje šta brend isporučuje, a ne pojedinačni deo. Prikazuje se uz svaki deo ovog brenda.',
    ],

    'delete' => [
        'action' => 'Obriši :name',
        'confirm' => 'Obrisati :name? Delovi koji ga već nose zadržavaju brend, a naziv ostaje rezervisan dok se brend ne vrati.',
    ],

    'restore' => [
        'action' => 'Vrati :name',
        'label' => 'Vrati',
        'confirm' => 'Vratiti :name? Ponovo će se moći izabrati u obrascu za deo.',
    ],

    'empty' => [
        'none' => [
            'title' => 'Još nema brendova',
            'body' => 'Dodajte prvi brend da biste beležili ko proizvodi delove na polici.',
        ],
        'no_matches' => [
            'title' => 'Nijedan brend ne odgovara pretrazi',
            'body' => 'Pokušajte sa delom naziva.',
        ],
    ],

    'parts_count' => ':count deo|:count dela|:count delova|{0}Nema',

    'validation' => [
        'name_deleted' => 'Taj naziv pripada obrisanom brendu. Vratite ga umesto da pravite drugi.',
    ],

    'flash' => [
        'created' => 'Brend :name je dodat.',
        'updated' => 'Brend :name je izmenjen.',
        'deleted' => 'Brend :name je obrisan. Njegov naziv ostaje rezervisan.',
        'restored' => 'Brend :name je vraćen.',
    ],

];
