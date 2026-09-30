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
        'before_discount' => 'Pre popusta',
        'discount' => 'Popust',
        'subtotal' => 'Osnovica',
        'vat' => 'PDV :rate%',
        'total' => 'Ukupno',
        'tax_id' => 'PIB',
        'registration_number' => 'Matični broj (MB)',
    ],

    'estimate' => [
        'subject' => 'Predračun :number — :plate',
        'heading' => 'Predračun :number',
        'greeting' => 'Poštovani :name,',
        'body' => 'U nastavku je predračun za radove na Vašem vozilu :make :model (:plate).',
        'reply' => 'Molimo Vas da nas kontaktirate kako biste ga odobrili ili odbili.',
        'number' => 'Predračun',
        'created' => 'Datum',
        'line' => ':description × :quantity',
        'line_discounted' => ':description × :quantity (popust :percent%)',
        'before_discount' => 'Pre popusta',
        'discount' => 'Popust',
        'subtotal' => 'Osnovica',
        'vat' => 'PDV :rate%',
        'total' => 'Ukupno',
    ],

];
