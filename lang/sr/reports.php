<?php

return [
    'title' => 'Izveštaji',

    'period' => [
        'legend' => 'Period',
        'from' => 'Od',
        'to' => 'Do',
        'apply' => 'Primeni',
        'summary' => 'Računi izdati :from – :to',
    ],

    'export' => [
        'label' => 'Izvezi CSV',
        'aria' => 'Izvezi CSV računa izdatih :from – :to',
    ],

    'empty' => [
        'title' => 'Nema podataka za ovaj period',
        'body' => 'Nijedan račun izdat u ovom periodu ne ulazi u izveštaj. Izaberite drugi period.',
    ],

    'voided' => ':count stornirani račun se ne računa.|:count stornirana računa se ne računaju.|:count storniranih računa se ne računa.',

    'revenue' => [
        'title' => 'Prihod',
        'invoices' => 'Računi',
        'before_discount' => 'Pre popusta',
        'discount' => 'Popusti',
        'subtotal' => 'Osnovica',
        'vat' => 'PDV',
        'total' => 'Ukupno',
    ],

    'vat' => [
        'title' => 'PDV po stopi',
        'rate' => 'Stopa',
        'rate_value' => ':rate%',
        'base' => 'Osnovica',
        'vat' => 'PDV',
    ],

    'payments' => [
        'title' => 'Plaćanja',
        'method' => 'Način',
        'invoices' => 'Računi',
        'amount' => 'Iznos',
        'unrecorded' => 'Nije zabeleženo',
        'unpaid' => 'Neplaćeno',
    ],

    'labor' => [
        'title' => 'Rad po mehaničaru',
        'mechanic' => 'Mehaničar',
        'hours' => 'Sati',
        'amount' => 'Iznos',
        'empty' => 'U ovom periodu nije fakturisan rad.',
    ],

    'parts' => [
        'title' => 'Prodati delovi',
        'sku' => 'Šifra',
        'name' => 'Deo',
        'quantity' => 'Kol.',
        'revenue' => 'Prihod',
        'cost' => 'Trošak',
        'margin' => 'Marža',
        'total' => 'Ukupno',
        'empty' => 'U ovom periodu nisu fakturisani delovi.',
        'without_cost' => ':count deo bez nabavne cene nije uračunat u maržu.|:count dela bez nabavne cene nisu uračunata u maržu.|:count delova bez nabavne cene nije uračunato u maržu.',
    ],

    'csv' => [
        'number' => 'Broj',
        'issued_on' => 'Datum izdavanja',
        'status' => 'Status',
        'job' => 'Radni nalog',
        'buyer_name' => 'Kupac',
        'buyer_address' => 'Adresa kupca',
        'buyer_tax_id' => 'PIB kupca',
        'buyer_registration_number' => 'MB kupca',
        'before_discount' => 'Pre popusta',
        'discount' => 'Popust',
        'subtotal' => 'Osnovica',
        'vat_rate' => 'Stopa PDV-a',
        'vat' => 'PDV',
        'total' => 'Ukupno',
        'payment_method' => 'Način plaćanja',
        'paid_on' => 'Datum plaćanja',
        'voided_on' => 'Datum storniranja',
    ],
];
