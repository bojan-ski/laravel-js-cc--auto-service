<?php

return [

    'vehicle' => 'Vozilo',
    'pair' => ':label: :value',

    'notice' => [
        'queued' => 'Klijentu se šalje email na :email.',
        'skipped' => 'Klijent nema email adresu u evidenciji, pa email nije poslat.',
    ],

    'job_completed' => [
        'subject' => 'Vaše vozilo je spremno — :plate',
        'heading' => 'Vaše vozilo je spremno',
        'greeting' => 'Poštovani :name,',
        'body' => 'Vaše vozilo :make :model (:plate) je spremno za preuzimanje.',
        'reference' => 'Broj radnog naloga',
    ],

    'invoice_issued' => [
        'subject' => 'Račun :number — :plate',
        'heading' => 'Račun :number',
        'greeting' => 'Poštovani :name,',
        'body' => 'U nastavku je račun za radove na Vašem vozilu :make :model (:plate).',
        'number' => 'Račun',
        'issued' => 'Izdat',
        'due' => 'Rok plaćanja',
        'subtotal' => 'Osnovica',
        'vat' => 'PDV :rate%',
        'total' => 'Ukupno',
    ],

];
