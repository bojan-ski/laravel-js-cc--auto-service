<?php

return [
    'statuses' => [
        'unpaid' => 'Neplătită',
        'paid' => 'Plătită',
        'void' => 'Stornată',
    ],

    'payment_methods' => [
        'cash' => 'Numerar',
        'card' => 'Card',
        'bank_transfer' => 'Transfer bancar',
    ],

    'title' => 'Facturi',
    'count' => 'O factură|:count facturi|:count de facturi|{0}Nicio factură',

    'tabs' => [
        'label' => 'Filtrează facturile după stare',
        'all' => 'Toate',
    ],

    'search_label' => 'Caută facturi',
    'search_placeholder' => 'Număr factură sau nume client',

    'headers' => [
        'number' => 'Număr',
        'customer' => 'Client',
        'issued' => 'Emisă',
        'total' => 'Total',
        'status' => 'Stare',
    ],

    'empty' => [
        'none' => [
            'title' => 'Nicio factură încă.',
            'body' => 'O factură se emite dintr-o lucrare finalizată, nu se creează aici.',
        ],

        'none_in_status' => [
            'title' => 'Nicio factură cu această stare.',
            'body' => 'Încercați altă stare sau afișați-le pe toate.',
            'clear' => 'Afișează toate facturile',
        ],

        'no_matches' => [
            'title' => 'Nicio factură nu corespunde căutării.',
            'body' => 'Căutați după numărul facturii sau numele clientului.',
        ],
    ],

    'issue' => [
        'title' => 'Emite factura · :reference',
        'heading' => 'Emite factura',
        'due_at' => 'Scadență (opțional)',
        'bill_to' => 'Facturat către',
        'bill_to_hint' => 'Aceste date sunt copiate pe factură în momentul emiterii. Modificările ulterioare ale clientului nu o vor schimba, așa că mai întâi corectați clientul dacă ceva este greșit.',
        'warning_title' => 'Emiterea blochează această lucrare.',
        'warning_body' => 'Pozițiile nu mai pot fi modificate, iar doar un administrator poate storna factura pentru a redeschide lucrarea.',
        'submit' => 'Emite factura',
        'submit_for' => 'Emite factura pentru lucrarea :reference',
        'cancel' => 'Anulează',
    ],

    'totals' => [
        'before_discount' => 'Înainte de discount',
        'discount' => 'Discount',
        'subtotal' => 'Subtotal',
        'vat' => 'TVA :rate%',
        'total' => 'Total',
    ],

    'show' => [
        'title' => 'Factura :number',
        'details' => 'Detalii factură',
        'job' => 'Lucrare',
        'customer' => 'Client',
        'vehicle' => 'Vehicul',
        'issued' => 'Emisă',
        'issued_by' => 'Emisă de',
        'due' => 'Scadență',
        'paid_on' => 'Data plății',
        'payment_method' => 'Metodă de plată',
        'payment_method_placeholder' => 'Alegeți…',
        'line_items' => 'Poziții',
        'actions' => 'Acțiuni factură',
        'print' => 'Tipărește',
        'mark_paid' => 'Marchează ca plătită',
        'mark_paid_for' => 'Marchează ca plătită factura :number',
        'confirm_paid' => 'Marcați factura :number ca plătită? Acțiunea este ireversibilă — o plată înregistrată din greșeală se corectează prin stornarea facturii și emiterea uneia noi.',
    ],

    'items' => [
        'description' => 'Descriere',
        'quantity' => 'Cant.',
        'price' => 'Preț unitar',
        'line_total' => 'Total poziție',
        'discount' => 'Disc.',
        'discount_value' => ':percent %',
    ],

    'void' => [
        'title' => 'Această factură a fost stornată.',
        'body' => 'Lucrarea aferentă a fost redeschisă și este posibil ca pozițiile să se fi modificat între timp. Totalurile de mai jos sunt cele facturate.',
        'view_job' => 'Vezi lucrarea',
        'view_job_for' => 'Vezi lucrarea :reference',

        'action' => 'Stornează factura',
        'action_for' => 'Stornează factura :number',
        'page_title' => 'Stornare factură · :number',
        'heading' => 'Stornare factură',
        'warning_title' => 'Aceasta anulează factura și deblochează lucrarea :reference.',
        'warning_body' => 'Lucrarea rămâne finalizată. Redeschideți-o pentru a-i edita pozițiile, apoi finalizați-o din nou pentru a emite o factură nouă.',
        'consequences' => [
            'kept' => 'Factura se păstrează și rămâne vizibilă, marcată ca stornată.',
            'number' => 'Numărul ei, :number, nu va fi refolosit niciodată.',
            'stock' => 'Piesele deja montate nu se returnează în stoc.',
            'reissue' => 'Apoi se poate emite o factură nouă, cu totaluri noi.',
        ],
        'paid_notice' => 'Această factură a fost marcată ca plătită la :date. Stornarea ei anulează o factură deja achitată.',
        'cancel' => 'Anulează',
        'voided' => 'Stornată',
        'voided_by' => 'Stornată de',
        'print_marker' => 'STORNATĂ',
    ],

    'identity' => [
        'heading' => 'Factura :number',
        'issued' => 'Emisă la :date',
        'bill_to' => 'Facturat către',
    ],

    'buyer' => [
        'tax_id' => 'Cod fiscal (PIB)',
        'registration_number' => 'Nr. de înregistrare (MB)',
    ],

    'validation' => [
        'payment_method_required' => 'Alegeți cum a fost plătită factura.',
    ],

    'flash' => [
        'issued' => 'Factura :number a fost emisă. Această lucrare este acum blocată.',
        'not_issuable' => 'Această lucrare nu a putut fi facturată. Este posibil să fi fost deja facturată de altcineva.',
        'paid' => 'Factura :number a fost marcată ca plătită. Metodă de plată: :method.',
        'not_payable' => 'Această factură nu a putut fi marcată ca plătită. Este posibil ca starea ei să se fi schimbat de la încărcarea paginii.',
        'voided' => 'Factura :number a fost stornată. Lucrarea :reference este deblocată.',
        'not_voidable' => 'Această factură nu a putut fi stornată. Este posibil ca starea ei să se fi schimbat de la încărcarea paginii.',
    ],
];
