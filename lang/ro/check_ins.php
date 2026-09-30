<?php

return [
    'show' => [
        'title' => 'Recepție :reference',
        'back' => 'Înapoi la :reference',
        'print' => 'Tipărește fișa',
        'none' => 'Nu s-a înregistrat nicio recepție pentru această lucrare.',
        'recorded_by' => 'Înregistrată de :name',
    ],

    'fields' => [
        'fuel_level' => 'Nivel combustibil',
        'damage_notes' => 'Daune',
        'valuables' => 'Obiecte de valoare lăsate în mașină',
        'mileage_in' => 'Kilometraj la intrare',
        'received' => 'Primită',
        'signed_by_name' => 'Numele clientului',
        'signature' => 'Semnătură',
        'signature_method' => 'Modul de semnare',
        'photo' => 'Fotografie',
        'vehicle' => 'Vehicul',
        'customer' => 'Client',
        'vin' => 'Serie șasiu',
    ],

    'fuel_levels' => [
        'empty' => 'Gol',
        'quarter' => '1/4',
        'half' => '1/2',
        'three_quarters' => '3/4',
        'full' => 'Plin',
    ],

    'signature_methods' => [
        'drawn' => 'Semnată pe tabletă',
        'paper' => 'Semnată pe hârtie',
    ],

    'form' => [
        'heading' => 'Starea la sosire',
        'submit' => 'Salvează recepția',
    ],

    'photos' => [
        'heading' => 'Fotografii cu daune',
        'empty' => 'Nicio fotografie cu daune.',
        'label' => 'Adaugă o fotografie cu daune',
        'upload' => 'Încarcă fotografia',
        'upload_for' => 'Încarcă fotografia la recepția pentru :reference',
        'hint' => 'JPEG, PNG sau WebP, până la 10 MB.',
    ],

    'sign' => [
        'heading' => 'Semnătură',
        'pad_label' => 'Zonă de semnătură',
        'pad_hint' => 'Clientul semnează în casetă cu degetul sau cu un stilou. Dacă nu poate desena, tipăriți fișa și confirmați mai jos semnătura pe hârtie.',
        'clear' => 'Șterge',
        'submit' => 'Semnează',
        'empty' => 'Clientul nu a semnat încă.',
        'paper_hint' => 'Tipăriți fișa, cereți clientului să o semneze, apoi confirmați aici.',
        'paper_submit' => 'Confirmă semnătura pe hârtie',
        'signed' => 'Semnată de :name · :date',
        'image_alt' => 'Semnătura lui :name',
        'read_only' => 'Această fișă este semnată și nu mai poate fi modificată.',
        'errors' => [
            'unreadable' => 'Semnătura nu a putut fi citită. Ștergeți caseta și semnați din nou.',
            'empty' => 'Desenați semnătura în casetă, apoi apăsați Semnează.',
        ],
    ],

    'print' => [
        'title' => 'Fișă de recepție a vehiculului',
        'printed' => 'Tipărită :date',
        'declaration' => 'Confirm că starea vehiculului și obiectele de valoare lăsate în el sunt consemnate corect mai sus.',
        'signature_line' => 'Semnătura clientului',
        'name_line' => 'Nume',
        'date_line' => 'Data',
        'signed_on_paper' => 'Semnată pe hârtie de :name la :date',
        'action' => 'Tipărește',
        'back' => 'Înapoi la recepție',
    ],

    'summary' => [
        'label' => 'Recepție',
        'not_recorded' => 'Neînregistrată',
        'unsigned' => 'Înregistrată, nesemnată',
        'signed' => 'Semnată :date · :method',
        'photos' => ':count fotografie|:count fotografii|:count de fotografii',
        'record' => 'Înregistrează recepția',
        'open' => 'Deschide fișa',
        'view' => 'Vezi fișa',
        'methods' => [
            'drawn' => 'pe tabletă',
            'paper' => 'pe hârtie',
        ],
    ],

    'flash' => [
        'saved' => 'Recepția a fost salvată.',
        'photo_added' => 'Fotografia a fost adăugată.',
        'photo_deleted' => 'Fotografia a fost ștearsă.',
        'signed' => 'Recepția a fost semnată de :name.',
        'not_open' => 'Această recepție nu mai poate fi modificată.',
    ],
];
