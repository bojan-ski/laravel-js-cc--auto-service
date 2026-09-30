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
        'before_discount' => 'Înainte de discount',
        'discount' => 'Discount',
        'subtotal' => 'Subtotal',
        'vat' => 'TVA :rate%',
        'total' => 'Total',
        'tax_id' => 'Cod fiscal (PIB)',
        'registration_number' => 'Nr. de înregistrare (MB)',
    ],

    'estimate' => [
        'subject' => 'Deviz :number — :plate',
        'heading' => 'Deviz :number',
        'greeting' => 'Bună ziua, :name,',
        'body' => 'Vă transmitem devizul pentru lucrările la vehiculul dumneavoastră :make :model (:plate).',
        'reply' => 'Vă rugăm să ne contactați pentru a-l aproba sau refuza.',
        'number' => 'Deviz',
        'created' => 'Data',
        'line' => ':description × :quantity',
        'line_discounted' => ':description × :quantity (discount :percent%)',
        'before_discount' => 'Înainte de discount',
        'discount' => 'Discount',
        'subtotal' => 'Subtotal',
        'vat' => 'TVA :rate%',
        'total' => 'Total',
    ],

];
