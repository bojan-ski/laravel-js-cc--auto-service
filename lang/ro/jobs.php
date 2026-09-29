<?php

return [
    'statuses' => [
        'received' => 'Recepționată',
        'in_progress' => 'În lucru',
        'awaiting_parts' => 'Așteaptă piese',
        'completed' => 'Finalizată',
        'cancelled' => 'Anulată',
    ],

    'board' => [
        'title' => 'Lucrări',
        'new' => 'Lucrare nouă',
        'count' => ':count lucrare|:count lucrări|:count de lucrări',
        'tabs_label' => 'Filtrează după stare',
        'tab_all' => 'Toate',
        'search_label' => 'Caută lucrări',
        'search_placeholder' => 'Nr. înmatriculare sau client',
        'mine' => 'Ale mele',
        'mine_hint' => '(doar lucrările cu pozițiile mele de manoperă)',
        'sort_by' => 'Sortează după :column, :direction',
        'ascending' => 'crescător',
        'descending' => 'descrescător',

        'headers' => [
            'plate' => 'Nr. înmatr.',
            'vehicle' => 'Vehicul',
            'customer' => 'Client',
            'status' => 'Stare',
            'received' => 'Recepție',
            'assigned' => 'Alocat',
        ],

        'assigned_more' => 'încă :count persoană|încă :count persoane|încă :count de persoane',

        'empty' => [
            'none' => [
                'title' => 'Nicio lucrare încă.',
                'body' => 'Deschideți prima lucrare dintr-o programare sosită sau fără programare.',
            ],

            'none_in_status' => [
                'title' => 'Nicio lucrare cu această stare.',
                'body' => 'Afișați toate stările pentru a vedea toate lucrările.',
                'clear' => 'Afișează toate stările',
            ],

            'no_matches' => [
                'title' => 'Nicio lucrare nu corespunde căutării.',
                'body' => 'Verificați numărul de înmatriculare sau încercați o parte din numele clientului.',
            ],

            'none_mine' => [
                'title' => 'Nicio lucrare a dumneavoastră aici.',
                'body' => 'O lucrare devine a dumneavoastră când are o poziție de manoperă pe numele dumneavoastră.',
                'clear' => 'Afișează lucrările tuturor',
            ],
        ],
    ],

    'form' => [
        'create_title' => 'Lucrare nouă',
        'appointment' => 'Programare',
        'appointment_summary' => ':time · programată de :name',
        'vehicle' => 'Vehicul',
        'complaint' => 'Defect reclamat',
        'complaint_placeholder' => 'Ce spune clientul că nu funcționează? Folosiți cuvintele lui.',
        'mileage_in' => 'Kilometraj la intrare (km, opțional)',
        'last_known_mileage' => 'Ultimul cunoscut: :mileage km',
        'submit_walk_in' => 'Deschide lucrarea',
        'submit_from_appointment' => 'Confirmă sosirea și deschide lucrarea',
        'back' => 'Anulează',
    ],

    'validation' => [
        'appointment_not_booked' => 'Această programare nu mai este activă, deci nu poate fi marcată ca sosită.',
    ],

    'show' => [
        'complaint' => 'Defect reclamat',
        'details' => 'Detalii lucrare',
        'mileage_in' => 'Kilometraj la intrare',
        'mileage_value' => ':mileage km',
        'origin' => 'Origine',
        'origin_booking' => 'Programare din :time',
        'walk_in' => 'Fără programare',
        'opened_by' => 'Deschisă de',
        'received' => 'Recepționată',
        'started' => 'Începută',
        'awaiting_parts' => 'Așteaptă piese',
        'completed' => 'Finalizată',
        'cancelled' => 'Anulată',
        'line_items' => 'Poziții',
        'line_items_empty' => 'Nicio poziție înregistrată încă.',
        'attachments' => 'Fotografii și documente',
        'attachments_empty' => 'Nimic încărcat încă.',

        'lock' => [
            'title' => 'Blocată — facturată pe :date',
            'body' => 'Pozițiile nu mai pot fi modificate. Un administrator trebuie să storneze factura pentru a redeschide această lucrare.',
            'invoice' => 'Factură',
        ],
    ],

    'items' => [
        'types' => [
            'labor' => 'Manoperă',
            'part' => 'Piesă',
        ],

        'headers' => [
            'type' => 'Tip',
            'description' => 'Descriere',
            'quantity' => 'Cant.',
            'price' => 'Preț',
            'who' => 'Cine',
            'actions' => 'Acțiuni',
        ],

        'subtotal' => 'Subtotal',
        'complete_hint' => 'Adăugați cel puțin o poziție înainte ca lucrarea să poată fi finalizată.',

        'add' => [
            'title' => 'Adaugă manoperă',
            'description' => 'Descriere',
            'description_placeholder' => 'Ce s-a lucrat?',
            'description_placeholder_part' => 'Notă opțională despre această piesă',
            'quantity' => 'Ore',
            'who' => 'Efectuată de',
            'who_placeholder' => 'Selectați o persoană',
            'who_unassigned' => 'Nealocată',
            'rate' => 'Tarif orar',
            'submit' => 'Adaugă poziția',
            'group_label' => 'Adaugă o poziție',
            'labor_link' => '+ Manoperă',
            'labor_link_label' => 'Adaugă o poziție de manoperă',
            'part_link' => '+ Piesă',
            'part_link_label' => 'Adaugă o poziție de piesă',
            'no_parts' => 'Catalogul de piese este gol, deci nu există încă nicio piesă de adăugat.',
            'part_title' => 'Adaugă piesă',
            'part' => 'Piesă',
            'quantity_units' => 'Cantitate',
            'price' => 'Preț unitar',
            'price_placeholder' => 'Preț din catalog',
            'submit_part' => 'Adaugă piesa',
        ],

        'edit' => [
            'title_for' => 'Editează poziția: :description',
            'price' => 'Preț',
            'row_marker' => 'Această poziție este editată mai jos.',
            'open' => 'Editează',
            'open_line' => 'Editează :description',
            'save' => 'Salvează',
            'cancel' => 'Anulează',
        ],

        'delete' => [
            'label' => 'Șterge',
            'label_line' => 'Șterge :description',
            'confirm' => 'Ștergeți „:description”? Pozițiile se șterg definitiv.',
        ],

        'flash' => [
            'added' => 'Poziție adăugată.',
            'part_added' => 'Poziția de piesă a fost adăugată, iar piesa a fost scoasă din stoc.',
            'updated' => 'Poziție actualizată.',
            'deleted' => 'Poziție ștearsă.',
            'stock_negative' => 'Stocul pentru :name este acum :balance. Stocul a devenit negativ.',
        ],
    ],

    'attachments' => [
        'types' => [
            'photo' => 'Fotografie',
            'document' => 'Document',
        ],

        'photos_label' => 'Fotografii',
        'documents_label' => 'Documente',
        'uploaded_by' => ':name · :date',
        'photo_alt' => 'Fotografie la lucrarea :reference, încărcată de :name',
        'open_photo' => 'Deschide la dimensiune completă fotografia lucrării :reference, încărcată de :name pe :date',

        'upload' => [
            'label' => 'Adaugă o fotografie sau un document',
            'action' => 'Încarcă',
            'action_for' => 'Încarcă un fișier la lucrarea :reference',
            'hint' => 'JPEG, PNG sau WebP de până la 10 MB, sau un PDF de până la 20 MB.',
        ],

        'download' => [
            'action' => 'Descarcă',
            'action_for' => 'Descarcă :filename',
        ],

        'delete' => [
            'action' => 'Șterge',
            'action_for' => 'Șterge :filename',
            'confirm' => 'Ștergeți :filename? Fișierul este eliminat definitiv și nu poate fi recuperat.',
        ],

        'lightbox' => [
            'label' => 'Vizualizator foto',
            'close' => 'Închide',
        ],

        'flash' => [
            'uploaded' => 'Fișier încărcat.',
            'deleted' => 'Fișier șters.',
            'unreadable' => 'Fișierul nu a putut fi citit ca imagine și nu a fost salvat.',
        ],
    ],

    'history' => [
        'view_all' => 'Vezi toate lucrările (:count)',

        'headers' => [
            'reference' => 'Lucrare',
            'plate' => 'Nr. înmatr.',
            'customer' => 'Client',
            'status' => 'Stare',
            'received' => 'Recepție',
            'mileage' => 'Kilometraj',
        ],
    ],

    'actions' => [
        'start' => 'Începe',
        'pause' => 'Așteaptă piese',
        'resume' => 'Reia',
        'complete' => 'Finalizează',
        'cancel' => 'Anulează lucrarea',
        'reopen' => 'Redeschide',
        'start_for' => 'Începe lucrarea :reference pentru :plate',
        'pause_for' => 'Așteaptă piese pentru lucrarea :reference (:plate)',
        'resume_for' => 'Reia lucrarea :reference pentru :plate',
        'complete_for' => 'Finalizează lucrarea :reference pentru :plate',
        'cancel_for' => 'Anulează lucrarea :reference pentru :plate',
        'reopen_for' => 'Redeschide lucrarea :reference pentru :plate',
        'issue' => 'Emite factura',
        'issue_for' => 'Emite factura pentru lucrarea :reference (:plate)',
        'confirm_cancel' => 'Anulați lucrarea :reference pentru :plate? Anularea este definitivă — o lucrare anulată nu poate fi redeschisă.',
        'confirm_cancel_with_parts' => 'Anulați lucrarea :reference pentru :plate? O poziție de piesă va fi returnată în stoc. Anularea este definitivă — o lucrare anulată nu poate fi redeschisă.|Anulați lucrarea :reference pentru :plate? :count poziții de piese vor fi returnate în stoc. Anularea este definitivă — o lucrare anulată nu poate fi redeschisă.|Anulați lucrarea :reference pentru :plate? :count de poziții de piese vor fi returnate în stoc. Anularea este definitivă — o lucrare anulată nu poate fi redeschisă.',
    ],

    'flash' => [
        'created' => 'Lucrarea :reference a fost deschisă.',
        'mileage_lower' => 'Kilometrajul introdus (:entered km) este mai mic decât ultimul cunoscut, :stored km, așa că kilometrajul vehiculului nu a fost actualizat.',
        'started' => 'Lucrarea :reference a început.',
        'paused' => 'Lucrarea :reference așteaptă piese.',
        'resumed' => 'Lucrarea :reference a fost reluată.',
        'completed' => 'Lucrarea :reference a fost finalizată.',
        'cancelled' => 'Lucrarea :reference a fost anulată.',
        'reopened' => 'Lucrarea :reference a fost redeschisă.',
    ],
];
