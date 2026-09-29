<?php

return [

    'vehicle' => 'Fahrzeug',
    'pair' => ':label: :value',

    'notice' => [
        'queued' => 'Der Kunde wird per E-Mail an :email benachrichtigt.',
        'skipped' => 'Für den Kunden ist keine E-Mail-Adresse hinterlegt, daher wurde keine E-Mail gesendet.',
    ],

    'job_completed' => [
        'subject' => 'Ihr Fahrzeug ist fertig — :plate',
        'heading' => 'Ihr Fahrzeug ist fertig',
        'greeting' => 'Guten Tag :name,',
        'body' => 'Ihr :make :model (:plate) ist zur Abholung bereit.',
        'reference' => 'Auftragsnummer',
    ],

    'invoice_issued' => [
        'subject' => 'Rechnung :number — :plate',
        'heading' => 'Rechnung :number',
        'greeting' => 'Guten Tag :name,',
        'body' => 'Anbei erhalten Sie die Rechnung für die Arbeiten an Ihrem :make :model (:plate).',
        'number' => 'Rechnung',
        'issued' => 'Ausgestellt',
        'due' => 'Fällig',
        'before_discount' => 'Vor Rabatt',
        'discount' => 'Rabatt',
        'subtotal' => 'Zwischensumme',
        'vat' => 'MwSt. :rate %',
        'total' => 'Gesamt',
        'tax_id' => 'Steuernr. (PIB)',
        'registration_number' => 'Registernr. (MB)',
    ],

];
