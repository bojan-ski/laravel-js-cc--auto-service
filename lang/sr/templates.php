<?php

return [
    'index' => [
        'title' => 'Šabloni naloga',
        'new' => 'Novi šablon',
        'headers' => [
            'actions' => 'Radnje',
        ],
    ],

    'headers' => [
        'name' => 'Naziv',
        'description' => 'Opis',
        'lines' => 'Stavke',
    ],

    'lines_count' => ':count stavka|:count stavke|:count stavki',
    'no_lines' => 'Još nema stavki',

    'empty' => [
        'none' => [
            'title' => 'Još nema šablona naloga',
            'body' => 'Šablon u jednom koraku dodaje nalogu skup stavki rada i delova.',
        ],
    ],

    'trashed' => [
        'badge' => 'Obrisan',
    ],

    'restore' => [
        'label' => 'Vrati',
        'action' => 'Vrati :name',
        'confirm' => 'Vratiti šablon :name?',
    ],

    'create' => [
        'title' => 'Novi šablon naloga',
        'submit' => 'Napravi šablon',
    ],

    'edit' => [
        'title' => 'Izmena: :name',
        'submit' => 'Sačuvaj šablon',
    ],

    'fields' => [
        'name' => 'Naziv',
        'description' => 'Opis',
    ],

    'errors' => [
        'name_trashed' => 'Obrisani šablon već nosi naziv :name. Administrator može da ga vrati.',
    ],

    'delete' => [
        'label' => 'Obriši šablon',
        'action' => 'Obriši šablon :name',
        'confirm' => 'Obrisati šablon :name? Administrator može da ga vrati.',
    ],

    'lines' => [
        'heading' => 'Stavke',
        'empty' => 'Ovaj šablon još nema stavki. Dodajte jednu ispod.',
        'headers' => [
            'type' => 'Vrsta',
            'description' => 'Opis',
            'quantity' => 'Kol.',
            'actions' => 'Radnje',
        ],
        'part_label' => ':name · :sku',
        'part_deleted' => '(obrisan)',
        'deleted_part_notice' => 'Jedan deo iz ovog šablona je obrisan. Zamenite ili uklonite tu stavku pre primene šablona.',
        'add' => [
            'labor_title' => 'Dodaj stavku rada',
            'part_title' => 'Dodaj stavku dela',
            'group_label' => 'Vrsta stavke',
            'labor_link' => '+ Rad',
            'part_link' => '+ Deo',
            'description' => 'Opis',
            'part' => 'Deo',
            'hours' => 'Sati',
            'units' => 'Količina',
            'no_parts' => 'U katalogu još nema delova.',
            'submit' => 'Dodaj stavku',
        ],
        'edit' => [
            'open' => 'Izmeni',
            'open_line' => 'Izmeni :description',
            'title_for' => 'Izmena: :description',
            'save' => 'Sačuvaj stavku',
            'cancel' => 'Otkaži',
            'row_marker' => 'Ova stavka se menja ispod.',
        ],
        'remove' => [
            'label' => 'Ukloni',
            'action' => 'Ukloni :description',
            'confirm' => 'Ukloniti :description iz ovog šablona?',
        ],
    ],

    'flash' => [
        'created' => 'Šablon :name je napravljen. Dodajte mu stavke ispod.',
        'updated' => 'Šablon :name je sačuvan.',
        'deleted' => 'Šablon :name je obrisan.',
        'restored' => 'Šablon :name je vraćen.',
        'line_added' => 'Stavka je dodata.',
        'line_updated' => 'Stavka je sačuvana.',
        'line_removed' => 'Stavka je uklonjena.',
    ],

    'apply' => [
        'title' => 'Primeni šablon',
        'link' => '+ Šablon',
        'link_label' => 'Primeni šablon',
        'template' => 'Šablon',
        'template_placeholder' => 'Izaberite šablon',
        'option' => ':name — :lines',
        'mechanic' => 'Mehaničar',
        'mechanic_placeholder' => 'Izaberite mehaničara',
        'mechanic_hint' => 'Obavezno kada šablon ima stavke rada. Sve stavke se dodeljuju ovom mehaničaru.',
        'self_hint' => 'Sve stavke se dodeljuju vama.',
        'submit' => 'Primeni šablon',
        'none' => 'Još nema šablona naloga.',
        'manage' => 'Upravljaj šablonima',
        'flash' => [
            'applied' => 'Šablon :name je primenjen: dodata je :count stavka.|Šablon :name je primenjen: dodate su :count stavke.|Šablon :name je primenjen: dodato je :count stavki.',
            'stock_negative' => 'Zaliha je sada u minusu za: :parts.',
            'stock_negative_part' => ':name (:balance)',
        ],
        'errors' => [
            'deleted_part' => 'Ovaj šablon koristi deo :part, koji je obrisan. Izmenite šablon pre nego što ga primenite.',
            'mechanic_required' => 'Izaberite mehaničara koji će obaviti rad iz ovog šablona.',
            'no_lines' => 'Ovaj šablon nema stavki.',
        ],
    ],
];
