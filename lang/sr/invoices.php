<?php

return [
    'statuses' => [
        'unpaid' => 'Neplaćen',
        'paid' => 'Plaćen',
        'void' => 'Storniran',
    ],

    'title' => 'Računi',
    'count' => ':count račun|:count računa|:count računa|{0}Nema računa|{1}Jedan račun',

    'tabs' => [
        'label' => 'Filtriraj račune po statusu',
        'all' => 'Svi',
    ],

    'search_label' => 'Pretraga računa',
    'search_placeholder' => 'Broj računa ili ime klijenta',

    'headers' => [
        'number' => 'Broj',
        'customer' => 'Klijent',
        'issued' => 'Izdat',
        'total' => 'Ukupno',
        'status' => 'Status',
    ],

    'empty' => [
        'none' => [
            'title' => 'Još nema računa.',
            'body' => 'Račun se izdaje iz završenog radnog naloga, ne kreira se ovde.',
        ],

        'none_in_status' => [
            'title' => 'Nijedan račun nema taj status.',
            'body' => 'Pokušajte sa drugim statusom ili prikažite sve.',
            'clear' => 'Prikaži sve račune',
        ],

        'no_matches' => [
            'title' => 'Nijedan račun ne odgovara pretrazi.',
            'body' => 'Pretražujte po broju računa ili imenu klijenta.',
        ],
    ],

    'issue' => [
        'title' => 'Izdavanje računa · :reference',
        'heading' => 'Izdaj račun',
        'due_at' => 'Rok plaćanja (opciono)',
        'bill_to' => 'Kupac',
        'bill_to_hint' => 'Ovi podaci se prepisuju na račun u trenutku izdavanja. Kasnije izmene klijenta ga neće promeniti, zato prvo ispravite klijenta ako nešto nije u redu.',
        'warning_title' => 'Izdavanjem računa ovaj radni nalog se zaključava.',
        'warning_body' => 'Stavke postaju samo za čitanje i samo administrator može stornirati račun da bi ponovo otvorio nalog.',
        'submit' => 'Izdaj račun',
        'submit_for' => 'Izdaj račun za radni nalog :reference',
        'cancel' => 'Otkaži',
    ],

    'totals' => [
        'subtotal' => 'Osnovica',
        'vat' => 'PDV :rate%',
        'total' => 'Ukupno',
    ],

    'show' => [
        'title' => 'Račun :number',
        'details' => 'Detalji računa',
        'job' => 'Radni nalog',
        'customer' => 'Klijent',
        'vehicle' => 'Vozilo',
        'issued' => 'Izdat',
        'issued_by' => 'Izdao/la',
        'due' => 'Rok plaćanja',
        'line_items' => 'Stavke',
        'actions' => 'Radnje sa računom',
        'print' => 'Štampaj',
        'mark_paid' => 'Označi kao plaćen',
        'mark_paid_for' => 'Označi kao plaćen račun :number',
        'confirm_paid' => 'Označiti račun :number kao plaćen? Ovo se ne može poništiti — pogrešno evidentirano plaćanje ispravlja se storniranjem računa i izdavanjem novog.',
    ],

    'items' => [
        'description' => 'Opis',
        'quantity' => 'Kol.',
        'price' => 'Jedinična cena',
        'line_total' => 'Iznos',
    ],

    'void' => [
        'title' => 'Ovaj račun je storniran.',
        'body' => 'Njegov radni nalog je ponovo otvoren i stavke su se od tada možda promenile. Iznosi ispod su ono što je fakturisano.',
        'view_job' => 'Prikaži radni nalog',
        'view_job_for' => 'Prikaži radni nalog :reference',

        'action' => 'Storniraj račun',
        'action_for' => 'Storniraj račun :number',
        'page_title' => 'Storniranje računa · :number',
        'heading' => 'Storniraj račun',
        'warning_title' => 'Ovim se račun poništava i otključava radni nalog :reference.',
        'warning_body' => 'Radni nalog ostaje završen. Ponovo ga otvorite da biste izmenili stavke, a zatim ga ponovo završite da biste izdali novi račun.',
        'consequences' => [
            'kept' => 'Račun se čuva i ostaje vidljiv, označen kao storniran.',
            'number' => 'Njegov broj :number se nikada ponovo ne koristi.',
            'stock' => 'Već ugrađeni delovi se ne vraćaju na stanje.',
            'reissue' => 'Nakon toga se može izdati novi račun, sa novim iznosima.',
        ],
        'paid_notice' => 'Ovaj račun je označen kao plaćen :date. Storniranjem se poništava već izmiren račun.',
        'cancel' => 'Otkaži',
        'voided' => 'Storniran',
        'voided_by' => 'Stornirao/la',
        'print_marker' => 'STORNIRAN',
    ],

    'identity' => [
        'heading' => 'Račun :number',
        'issued' => 'Izdat :date',
        'bill_to' => 'Kupac',
    ],

    'buyer' => [
        'tax_id' => 'PIB',
        'registration_number' => 'Matični broj (MB)',
    ],

    'flash' => [
        'issued' => 'Račun :number je izdat. Ovaj radni nalog je sada zaključan.',
        'not_issuable' => 'Za ovaj radni nalog nije moguće izdati račun. Možda ga je neko drugi već fakturisao.',
        'paid' => 'Račun :number je označen kao plaćen.',
        'not_payable' => 'Ovaj račun nije moguće označiti kao plaćen. Njegov status se možda promenio od učitavanja stranice.',
        'voided' => 'Račun :number je storniran. Radni nalog :reference je otključan.',
        'not_voidable' => 'Ovaj račun nije moguće stornirati. Njegov status se možda promenio od učitavanja stranice.',
    ],
];
