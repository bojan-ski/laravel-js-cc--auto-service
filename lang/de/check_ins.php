<?php

return [
    'show' => [
        'title' => 'Fahrzeugannahme :reference',
        'back' => 'Zurück zu :reference',
        'print' => 'Protokoll drucken',
        'none' => 'Für diesen Auftrag wurde keine Fahrzeugannahme erfasst.',
        'recorded_by' => 'Erfasst von :name',
    ],

    'fields' => [
        'fuel_level' => 'Tankfüllung',
        'damage_notes' => 'Schäden',
        'valuables' => 'Im Fahrzeug gelassene Wertsachen',
        'mileage_in' => 'Kilometerstand bei Annahme',
        'received' => 'Angenommen',
        'signed_by_name' => 'Name des Kunden',
        'signature' => 'Unterschrift',
        'signature_method' => 'Art der Unterschrift',
        'photo' => 'Foto',
        'vehicle' => 'Fahrzeug',
        'customer' => 'Kunde',
        'vin' => 'FIN',
    ],

    'fuel_levels' => [
        'empty' => 'Leer',
        'quarter' => '1/4',
        'half' => '1/2',
        'three_quarters' => '3/4',
        'full' => 'Voll',
    ],

    'signature_methods' => [
        'drawn' => 'Auf dem Tablet unterschrieben',
        'paper' => 'Auf Papier unterschrieben',
    ],

    'form' => [
        'heading' => 'Zustand bei Annahme',
        'submit' => 'Annahme speichern',
    ],

    'photos' => [
        'heading' => 'Schadensfotos',
        'print_alt' => 'Schadensfoto :position von :count, Auftrag :reference',
        'empty' => 'Keine Schadensfotos.',
        'label' => 'Schadensfoto hinzufügen',
        'upload' => 'Foto hochladen',
        'upload_for' => 'Foto hochladen zur Fahrzeugannahme für :reference',
        'hint' => 'JPEG, PNG oder WebP, bis 10 MB.',
    ],

    'sign' => [
        'heading' => 'Unterschrift',
        'pad_label' => 'Unterschriftenfeld',
        'pad_hint' => 'Der Kunde unterschreibt im Feld mit dem Finger oder einem Stift. Kann er nicht zeichnen, drucken Sie das Protokoll aus und bestätigen Sie unten die Unterschrift auf Papier.',
        'clear' => 'Löschen',
        'submit' => 'Unterschreiben',
        'empty' => 'Der Kunde hat noch nicht unterschrieben.',
        'paper_hint' => 'Drucken Sie das Protokoll aus, lassen Sie es den Kunden unterschreiben und bestätigen Sie es dann hier.',
        'paper_submit' => 'Unterschrift auf Papier bestätigen',
        'signed' => 'Unterschrieben von :name · :date',
        'image_alt' => 'Unterschrift von :name',
        'read_only' => 'Dieses Protokoll ist unterschrieben und kann nicht mehr geändert werden.',
        'errors' => [
            'unreadable' => 'Die Unterschrift konnte nicht gelesen werden. Löschen Sie das Feld und unterschreiben Sie erneut.',
            'empty' => 'Zeichnen Sie die Unterschrift in das Feld und drücken Sie dann Unterschreiben.',
        ],
    ],

    'print' => [
        'title' => 'Fahrzeugannahme',
        'printed' => 'Gedruckt :date',
        'declaration' => 'Ich bestätige, dass der Zustand des Fahrzeugs und die darin gelassenen Wertsachen oben richtig erfasst sind.',
        'signature_line' => 'Unterschrift des Kunden',
        'name_line' => 'Name',
        'date_line' => 'Datum',
        'signed_on_paper' => 'Auf Papier unterschrieben von :name am :date',
        'action' => 'Drucken',
        'back' => 'Zurück zur Fahrzeugannahme',
    ],

    'summary' => [
        'label' => 'Fahrzeugannahme',
        'not_recorded' => 'Nicht erfasst',
        'unsigned' => 'Erfasst, nicht unterschrieben',
        'signed' => 'Unterschrieben :date · :method',
        'photos' => ':count Foto|:count Fotos',
        'record' => 'Annahme erfassen',
        'open' => 'Protokoll öffnen',
        'view' => 'Protokoll ansehen',
        'methods' => [
            'drawn' => 'auf dem Tablet',
            'paper' => 'auf Papier',
        ],
    ],

    'flash' => [
        'saved' => 'Fahrzeugannahme gespeichert.',
        'photo_added' => 'Foto hinzugefügt.',
        'photo_deleted' => 'Foto gelöscht.',
        'signed' => 'Fahrzeugannahme unterschrieben von :name.',
        'not_open' => 'Diese Fahrzeugannahme kann nicht mehr geändert werden.',
    ],
];
