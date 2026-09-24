<?php

return [

    'vehicle' => 'Vehicul',
    'pair' => ':label: :value',

    'notice' => [
        'queued' => 'Clientul este notificat prin e-mail la :email.',
        'skipped' => 'Clientul nu are o adresă de e-mail înregistrată, așa că nu s-a trimis niciun e-mail.',
    ],

    'job_completed' => [
        'subject' => 'Vehiculul dumneavoastră este gata — :plate',
        'heading' => 'Vehiculul dumneavoastră este gata',
        'greeting' => 'Bună ziua, :name,',
        'body' => 'Vehiculul dumneavoastră :make :model (:plate) este gata de ridicare.',
        'reference' => 'Referință lucrare',
    ],

    'invoice_issued' => [
        'subject' => 'Factura :number — :plate',
        'heading' => 'Factura :number',
        'greeting' => 'Bună ziua, :name,',
        'body' => 'Vă transmitem factura pentru lucrările efectuate la vehiculul dumneavoastră :make :model (:plate).',
        'number' => 'Factură',
        'issued' => 'Emisă',
        'due' => 'Scadență',
        'subtotal' => 'Subtotal',
        'vat' => 'TVA :rate%',
        'total' => 'Total',
    ],

];
