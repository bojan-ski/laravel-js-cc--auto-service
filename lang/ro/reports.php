<?php

return [
    'title' => 'Rapoarte',

    'period' => [
        'legend' => 'Perioadă',
        'from' => 'De la',
        'to' => 'Până la',
        'apply' => 'Aplică',
        'summary' => 'Facturi emise :from – :to',
    ],

    'export' => [
        'label' => 'Exportă CSV',
        'aria' => 'Exportă CSV cu facturile emise :from – :to',
    ],

    'empty' => [
        'title' => 'Nimic de raportat pentru această perioadă',
        'body' => 'Nicio factură emisă în această perioadă nu intră în raport. Alegeți altă perioadă.',
    ],

    'voided' => ':count factură stornată nu este luată în calcul.|:count facturi stornate nu sunt luate în calcul.|:count de facturi stornate nu sunt luate în calcul.',

    'revenue' => [
        'title' => 'Venituri',
        'invoices' => 'Facturi',
        'before_discount' => 'Înainte de discount',
        'discount' => 'Discounturi',
        'subtotal' => 'Subtotal',
        'vat' => 'TVA',
        'total' => 'Total',
    ],

    'vat' => [
        'title' => 'TVA pe cote',
        'rate' => 'Cotă',
        'rate_value' => ':rate%',
        'base' => 'Bază',
        'vat' => 'TVA',
    ],

    'payments' => [
        'title' => 'Plăți',
        'method' => 'Metodă',
        'invoices' => 'Facturi',
        'amount' => 'Sumă',
        'unrecorded' => 'Neînregistrată',
        'unpaid' => 'Neplătită',
    ],

    'labor' => [
        'title' => 'Manoperă pe mecanic',
        'mechanic' => 'Mecanic',
        'hours' => 'Ore',
        'amount' => 'Sumă',
        'empty' => 'Nu s-a facturat manoperă în această perioadă.',
    ],

    'parts' => [
        'title' => 'Piese vândute',
        'sku' => 'Cod intern',
        'name' => 'Piesă',
        'quantity' => 'Cant.',
        'revenue' => 'Venit',
        'cost' => 'Cost',
        'margin' => 'Marjă',
        'total' => 'Total',
        'empty' => 'Nu s-au facturat piese în această perioadă.',
        'without_cost' => ':count piesă fără preț de achiziție nu este inclusă în marjă.|:count piese fără preț de achiziție nu sunt incluse în marjă.|:count de piese fără preț de achiziție nu sunt incluse în marjă.',
    ],

    'csv' => [
        'number' => 'Număr',
        'issued_on' => 'Data emiterii',
        'status' => 'Stare',
        'job' => 'Lucrare',
        'buyer_name' => 'Cumpărător',
        'buyer_address' => 'Adresa cumpărătorului',
        'buyer_tax_id' => 'PIB cumpărător',
        'buyer_registration_number' => 'MB cumpărător',
        'before_discount' => 'Înainte de discount',
        'discount' => 'Discount',
        'subtotal' => 'Subtotal',
        'vat_rate' => 'Cota TVA',
        'vat' => 'TVA',
        'total' => 'Total',
        'payment_method' => 'Metodă de plată',
        'paid_on' => 'Data plății',
        'voided_on' => 'Data stornării',
    ],
];
