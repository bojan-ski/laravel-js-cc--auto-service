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
        'tax_id' => 'Steuernr. (PIB)',
        'registration_number' => 'Registernr. (MB)',
    ],

    'estimate' => [
        'subject' => 'Kostenvoranschlag :number — :plate',
        'heading' => 'Kostenvoranschlag :number',
        'greeting' => 'Guten Tag :name,',
        'body' => 'Anbei erhalten Sie den Kostenvoranschlag für die Arbeiten an Ihrem :make :model (:plate).',
        'reply' => 'Bitte kontaktieren Sie uns, um ihn freizugeben oder abzulehnen.',
        'number' => 'Kostenvoranschlag',
        'created' => 'Datum',
        'line' => ':description × :quantity',
        'line_discounted' => ':description × :quantity (:percent % Rabatt)',
    ],

    'service_reminder' => [
        'subject_service' => 'Serviceerinnerung für :plate',
        'subject_registration' => 'Zulassungserinnerung für :plate',
        'heading_service' => 'Serviceerinnerung',
        'heading_registration' => 'Zulassungserinnerung',
        'greeting' => 'Guten Tag :name,',
        'body_service' => 'Für Ihren :make :model (:plate) ist der nächste Service am :date fällig.',
        'body_registration' => 'Die Zulassung Ihres :make :model (:plate) läuft am :date ab.',
        'due_on' => 'Fällig am',
        'or_at' => 'Oder bei',
        'expires_on' => 'Läuft ab am',
        'book' => 'Rufen Sie uns an, um einen Termin zu vereinbaren.',
        'opt_out' => 'Wenn Sie diese Erinnerungen nicht mehr erhalten möchten, sagen Sie uns Bescheid, und wir stellen sie ein.',
    ],

    'totals' => [
        'before_discount' => 'Vor Rabatt',
        'discount' => 'Rabatt',
        'subtotal' => 'Zwischensumme',
        'vat' => 'MwSt. :rate %',
        'total' => 'Gesamt',
    ],

];
