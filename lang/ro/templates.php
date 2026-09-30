<?php

return [
    'index' => [
        'title' => 'Șabloane de lucrări',
        'new' => 'Șablon nou',
        'headers' => [
            'actions' => 'Acțiuni',
        ],
    ],

    'headers' => [
        'name' => 'Nume',
        'description' => 'Descriere',
        'lines' => 'Poziții',
    ],

    'lines_count' => ':count poziție|:count poziții|:count de poziții',
    'no_lines' => 'Încă nicio poziție',

    'empty' => [
        'none' => [
            'title' => 'Încă nu există șabloane de lucrări',
            'body' => 'Un șablon adaugă la o lucrare, într-un singur pas, un set de poziții de manoperă și piese.',
        ],
    ],

    'trashed' => [
        'badge' => 'Șters',
    ],

    'restore' => [
        'label' => 'Restaurează',
        'action' => 'Restaurează :name',
        'confirm' => 'Restaurați șablonul :name?',
    ],

    'create' => [
        'title' => 'Șablon de lucrare nou',
        'submit' => 'Creează șablonul',
    ],

    'edit' => [
        'title' => 'Editează :name',
        'submit' => 'Salvează șablonul',
    ],

    'fields' => [
        'name' => 'Nume',
        'description' => 'Descriere',
    ],

    'errors' => [
        'name_trashed' => 'Un șablon șters poartă deja numele :name. Un administrator îl poate restaura.',
    ],

    'delete' => [
        'label' => 'Șterge șablonul',
        'action' => 'Șterge șablonul :name',
        'confirm' => 'Ștergeți șablonul :name? Un administrator îl poate restaura.',
    ],

    'lines' => [
        'heading' => 'Poziții',
        'empty' => 'Acest șablon nu are încă poziții. Adăugați una mai jos.',
        'headers' => [
            'type' => 'Tip',
            'description' => 'Descriere',
            'quantity' => 'Cant.',
            'actions' => 'Acțiuni',
        ],
        'part_label' => ':name · :sku',
        'part_deleted' => '(ștearsă)',
        'deleted_part_notice' => 'O piesă din acest șablon a fost ștearsă. Înlocuiți sau eliminați acea poziție înainte de a aplica șablonul.',
        'add' => [
            'labor_title' => 'Adaugă o poziție de manoperă',
            'part_title' => 'Adaugă o poziție de piesă',
            'group_label' => 'Tipul poziției',
            'labor_link' => '+ Manoperă',
            'part_link' => '+ Piesă',
            'description' => 'Descriere',
            'part' => 'Piesă',
            'hours' => 'Ore',
            'units' => 'Cantitate',
            'no_parts' => 'Încă nu există piese în catalog.',
            'submit' => 'Adaugă poziția',
        ],
        'edit' => [
            'open' => 'Editează',
            'open_line' => 'Editează :description',
            'title_for' => 'Editare: :description',
            'save' => 'Salvează poziția',
            'cancel' => 'Anulează',
            'row_marker' => 'Această poziție este editată mai jos.',
        ],
        'remove' => [
            'label' => 'Elimină',
            'action' => 'Elimină :description',
            'confirm' => 'Eliminați :description din acest șablon?',
        ],
    ],

    'flash' => [
        'created' => 'Șablonul :name a fost creat. Adăugați-i pozițiile mai jos.',
        'updated' => 'Șablonul :name a fost salvat.',
        'deleted' => 'Șablonul :name a fost șters.',
        'restored' => 'Șablonul :name a fost restaurat.',
        'line_added' => 'Poziția a fost adăugată.',
        'line_updated' => 'Poziția a fost salvată.',
        'line_removed' => 'Poziția a fost eliminată.',
    ],

    'apply' => [
        'title' => 'Aplică un șablon',
        'link' => '+ Șablon',
        'link_label' => 'Aplică un șablon',
        'template' => 'Șablon',
        'template_placeholder' => 'Alegeți șablonul',
        'option' => ':name — :lines',
        'mechanic' => 'Mecanic',
        'mechanic_placeholder' => 'Alegeți mecanicul',
        'mechanic_hint' => 'Obligatoriu când șablonul are poziții de manoperă. Toate pozițiile sunt atribuite acestui mecanic.',
        'self_hint' => 'Toate pozițiile vă sunt atribuite.',
        'submit' => 'Aplică șablonul',
        'none' => 'Încă nu există șabloane de lucrări.',
        'manage' => 'Gestionează șabloanele',
        'flash' => [
            'applied' => 'Șablonul :name a fost aplicat: :count poziție adăugată.|Șablonul :name a fost aplicat: :count poziții adăugate.|Șablonul :name a fost aplicat: :count de poziții adăugate.',
            'stock_negative' => 'Stocul este acum negativ pentru: :parts.',
            'stock_negative_part' => ':name (:balance)',
        ],
        'errors' => [
            'deleted_part' => 'Acest șablon folosește piesa :part, care a fost ștearsă. Editați șablonul înainte de a-l aplica.',
            'mechanic_required' => 'Alegeți mecanicul care va efectua manopera din acest șablon.',
            'no_lines' => 'Acest șablon nu are poziții.',
        ],
    ],
];
