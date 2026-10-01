<?php

return [
    'statuses' => [
        'received' => 'Primljen',
        'in_progress' => 'U radu',
        'awaiting_parts' => 'Čeka delove',
        'completed' => 'Završen',
        'cancelled' => 'Otkazan',
    ],

    'board' => [
        'title' => 'Radni nalozi',
        'new' => 'Novi radni nalog',
        'count' => ':count radni nalog|:count radna naloga|:count radnih naloga',
        'tabs_label' => 'Filtriraj po statusu',
        'tab_all' => 'Svi',
        'search_label' => 'Pretraga radnih naloga',
        'search_placeholder' => 'Tablice ili klijent',
        'mine' => 'Moji',
        'mine_hint' => '(samo radni nalozi sa mojim stavkama rada)',
        'sort_by' => 'Sortiraj po koloni :column, :direction',
        'ascending' => 'rastuće',
        'descending' => 'opadajuće',

        'headers' => [
            'plate' => 'Tablice',
            'vehicle' => 'Vozilo',
            'customer' => 'Klijent',
            'status' => 'Status',
            'received' => 'Primljen',
            'assigned' => 'Zaduženi',
        ],

        'assigned_more' => 'još :count osoba|još :count osobe|još :count osoba',

        'empty' => [
            'none' => [
                'title' => 'Još nema radnih naloga.',
                'body' => 'Otvorite prvi radni nalog iz termina u kome je vozilo stiglo ili bez zakazivanja.',
            ],

            'none_in_status' => [
                'title' => 'Nema radnih naloga sa ovim statusom.',
                'body' => 'Prikažite sve statuse da biste videli sve na tabli.',
                'clear' => 'Prikaži sve statuse',
            ],

            'no_matches' => [
                'title' => 'Nijedan radni nalog ne odgovara pretrazi.',
                'body' => 'Proverite tablice ili pokušajte sa delom imena klijenta.',
            ],

            'none_mine' => [
                'title' => 'Ovde nema vaših radnih naloga.',
                'body' => 'Radni nalog je vaš kada ima stavku rada na vaše ime.',
                'clear' => 'Prikaži radne naloge svih',
            ],
        ],
    ],

    'form' => [
        'create_title' => 'Novi radni nalog',
        'appointment' => 'Termin',
        'appointment_summary' => ':time · zakazao/la :name',
        'vehicle' => 'Vozilo',
        'complaint' => 'Opis kvara',
        'complaint_placeholder' => 'Šta je klijent rekao da nije u redu? Zapišite njegovim rečima.',
        'mileage_in' => 'Kilometraža pri prijemu (km, opciono)',
        'last_known_mileage' => 'Poslednja poznata: :mileage km',
        'submit_walk_in' => 'Otvori radni nalog',
        'submit_from_appointment' => 'Stigao — otvori radni nalog',
        'back' => 'Otkaži',
    ],

    'validation' => [
        'appointment_not_booked' => 'Taj termin više nije zakazan, pa se ne može označiti da je vozilo stiglo.',
    ],

    'show' => [
        'complaint' => 'Opis kvara',
        'details' => 'Detalji radnog naloga',
        'mileage_in' => 'Kilometraža pri prijemu',
        'mileage_value' => ':mileage km',
        'origin' => 'Poreklo',
        'origin_booking' => 'Zakazano :time',
        'walk_in' => 'Bez zakazivanja',
        'opened_by' => 'Otvorio/la',
        'received' => 'Primljen',
        'started' => 'Započet',
        'awaiting_parts' => 'Čeka delove',
        'completed' => 'Završen',
        'cancelled' => 'Otkazan',
        'line_items' => 'Stavke',
        'line_items_empty' => 'Još nema evidentiranih stavki.',
        'attachments' => 'Fotografije i dokumenti',
        'attachments_empty' => 'Još ništa nije otpremljeno.',

        'lock' => [
            'title' => 'Zaključan — fakturisan :date',
            'body' => 'Stavke su samo za čitanje. Administrator mora stornirati račun da bi ponovo otvorio ovaj radni nalog.',
            'invoice' => 'Račun',
        ],
    ],

    'items' => [
        'types' => [
            'labor' => 'Rad',
            'part' => 'Deo',
        ],

        'headers' => [
            'type' => 'Tip',
            'description' => 'Opis',
            'quantity' => 'Kol.',
            'price' => 'Cena',
            'discount' => 'Pop.',
            'line_total' => 'Ukupno',
            'who' => 'Ko',
            'actions' => 'Radnje',
        ],

        'subtotal' => 'Osnovica',
        'complete_hint' => 'Dodajte bar jednu stavku pre nego što se radni nalog može završiti.',
        'discount_value' => ':percent %',
        'discount_total' => 'Popust',

        'add' => [
            'title' => 'Dodaj rad',
            'description' => 'Opis',
            'description_placeholder' => 'Šta je urađeno?',
            'description_placeholder_part' => 'Opciona napomena o ovom delu',
            'quantity' => 'Sati',
            'who' => 'Izvršio/la',
            'who_placeholder' => 'Izaberite osobu',
            'who_unassigned' => 'Nije dodeljeno',
            'rate' => 'Cena po satu',
            'submit' => 'Dodaj stavku',
            'group_label' => 'Dodaj stavku',
            'labor_link' => '+ Rad',
            'labor_link_label' => 'Dodaj stavku rada',
            'part_link' => '+ Deo',
            'part_link_label' => 'Dodaj deo kao stavku',
            'no_parts' => 'Katalog delova je prazan, pa još nema delova za dodavanje.',
            'part_title' => 'Dodaj deo',
            'part' => 'Deo',
            'quantity_units' => 'Količina',
            'price' => 'Jedinična cena',
            'price_placeholder' => 'Kataloška cena',
            'submit_part' => 'Dodaj deo',
        ],

        'edit' => [
            'title_for' => 'Izmena stavke: :description',
            'price' => 'Cena',
            'row_marker' => 'Ova stavka se menja ispod.',
            'open' => 'Izmeni',
            'open_line' => 'Izmeni :description',
            'save' => 'Sačuvaj',
            'cancel' => 'Otkaži',
        ],

        'discount' => [
            'open' => 'Popust',
            'open_line' => 'Popust :description',
            'title_for' => 'Popust: :description',
            'percent' => 'Popust %',
            'hint' => 'Jedinična cena ostaje ista. Unesite 0 da uklonite popust.',
            'save' => 'Sačuvaj popust',
            'cancel' => 'Otkaži',
            'row_marker' => 'Popust za ovu stavku se podešava ispod.',
        ],

        'delete' => [
            'label' => 'Obriši',
            'label_line' => 'Obriši :description',
            'confirm' => 'Obrisati „:description“? Stavke se brišu trajno.',
        ],

        'flash' => [
            'added' => 'Stavka je dodata.',
            'part_added' => 'Stavka dela je dodata i deo je skinut sa police.',
            'updated' => 'Stavka je izmenjena.',
            'deleted' => 'Stavka je obrisana.',
            'discounted' => 'Popust je sačuvan.',
            'not_accepting' => 'Ovaj radni nalog više ne prima izmene stavki. Možda je u međuvremenu fakturisan ili zatvoren.',
            'stock_negative' => 'Stanje za :name je sada :balance. Zaliha je otišla u minus.',
        ],
    ],

    'attachments' => [
        'types' => [
            'photo' => 'Fotografija',
            'document' => 'Dokument',
        ],

        'photos_label' => 'Fotografije',
        'documents_label' => 'Dokumenti',
        'uploaded_by' => ':name · :date',
        'photo_alt' => 'Fotografija radnog naloga :reference, otpremio/la :name',
        'open_photo' => 'Otvori fotografiju u punoj veličini, radni nalog :reference, otpremio/la :name, :date',

        'upload' => [
            'label' => 'Dodaj fotografiju ili dokument',
            'action' => 'Otpremi',
            'action_for' => 'Otpremi datoteku na radni nalog :reference',
            'hint' => 'JPEG, PNG ili WebP do 10 MB, ili PDF do 20 MB.',
        ],

        'download' => [
            'action' => 'Preuzmi',
            'action_for' => 'Preuzmi :filename',
        ],

        'delete' => [
            'action' => 'Obriši',
            'action_for' => 'Obriši :filename',
            'confirm' => 'Obrisati :filename? Datoteka se trajno uklanja i ne može se vratiti.',
        ],

        'lightbox' => [
            'label' => 'Pregled fotografija',
            'close' => 'Zatvori',
        ],

        'flash' => [
            'uploaded' => 'Datoteka je otpremljena.',
            'deleted' => 'Datoteka je obrisana.',
            'unreadable' => 'Ta datoteka nije mogla da se pročita kao slika i nije sačuvana.',
        ],
    ],

    'history' => [
        'view_all' => 'Prikaži sve radne naloge (:count)',

        'headers' => [
            'reference' => 'Radni nalog',
            'plate' => 'Tablice',
            'customer' => 'Klijent',
            'status' => 'Status',
            'received' => 'Primljen',
            'mileage' => 'Kilometraža',
        ],
    ],

    'actions' => [
        'start' => 'Započni',
        'pause' => 'Čeka delove',
        'resume' => 'Nastavi',
        'complete' => 'Završi',
        'cancel' => 'Otkaži nalog',
        'reopen' => 'Ponovo otvori',
        'start_for' => 'Započni radni nalog :reference (:plate)',
        'pause_for' => 'Čeka delove: radni nalog :reference (:plate)',
        'resume_for' => 'Nastavi radni nalog :reference (:plate)',
        'complete_for' => 'Završi radni nalog :reference (:plate)',
        'cancel_for' => 'Otkaži nalog :reference (:plate)',
        'reopen_for' => 'Ponovo otvori radni nalog :reference (:plate)',
        'issue' => 'Izdaj račun',
        'issue_for' => 'Izdaj račun za radni nalog :reference (:plate)',
        'confirm_cancel' => 'Otkazati radni nalog :reference (:plate)? Otkazivanje je trajno — otkazan radni nalog ne može se ponovo otvoriti.',
        'confirm_cancel_with_parts' => 'Otkazati radni nalog :reference (:plate)? :count stavka dela biće vraćena na stanje. Otkazivanje je trajno — otkazan radni nalog ne može se ponovo otvoriti.|Otkazati radni nalog :reference (:plate)? :count stavke dela biće vraćene na stanje. Otkazivanje je trajno — otkazan radni nalog ne može se ponovo otvoriti.|Otkazati radni nalog :reference (:plate)? :count stavki dela biće vraćeno na stanje. Otkazivanje je trajno — otkazan radni nalog ne može se ponovo otvoriti.|{1}Otkazati radni nalog :reference (:plate)? Jedna stavka dela biće vraćena na stanje. Otkazivanje je trajno — otkazan radni nalog ne može se ponovo otvoriti.',
    ],

    'flash' => [
        'created' => 'Radni nalog :reference je otvoren.',
        'mileage_lower' => 'Uneta kilometraža (:entered km) manja je od poslednje poznate (:stored km), pa kilometraža vozila nije ažurirana.',
        'started' => 'Radni nalog :reference je započet.',
        'paused' => 'Radni nalog :reference čeka delove.',
        'resumed' => 'Rad na nalogu :reference je nastavljen.',
        'completed' => 'Radni nalog :reference je završen.',
        'cancelled' => 'Radni nalog :reference je otkazan.',
        'reopened' => 'Radni nalog :reference je ponovo otvoren.',
    ],
];
