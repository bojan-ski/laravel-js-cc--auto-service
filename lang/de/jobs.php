<?php

return [
    'statuses' => [
        'received' => 'Angenommen',
        'in_progress' => 'In Arbeit',
        'awaiting_parts' => 'Wartet auf Teile',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Abgebrochen',
    ],

    'board' => [
        'title' => 'Aufträge',
        'new' => 'Neuer Auftrag',
        'count' => ':count Auftrag|:count Aufträge',
        'tabs_label' => 'Nach Status filtern',
        'tab_all' => 'Alle',
        'search_label' => 'Aufträge suchen',
        'search_placeholder' => 'Kennzeichen oder Kunde',
        'mine' => 'Meine',
        'mine_hint' => '(nur Aufträge mit meinen Arbeitspositionen)',
        'sort_by' => 'Sortieren nach :column, :direction',
        'ascending' => 'aufsteigend',
        'descending' => 'absteigend',

        'headers' => [
            'plate' => 'Kennzeichen',
            'vehicle' => 'Fahrzeug',
            'customer' => 'Kunde',
            'status' => 'Status',
            'received' => 'Angenommen',
            'assigned' => 'Zugewiesen',
        ],

        'assigned_more' => ':count weitere Person|:count weitere Personen',

        'empty' => [
            'none' => [
                'title' => 'Noch keine Aufträge.',
                'body' => 'Öffnen Sie den ersten Auftrag aus einem eingetroffenen Termin oder ohne Termin.',
            ],

            'none_in_status' => [
                'title' => 'Keine Aufträge mit diesem Status.',
                'body' => 'Zeigen Sie alle Status an, um alle Aufträge zu sehen.',
                'clear' => 'Alle Status anzeigen',
            ],

            'no_matches' => [
                'title' => 'Kein Auftrag passt zu dieser Suche.',
                'body' => 'Prüfen Sie das Kennzeichen oder versuchen Sie einen Teil des Kundennamens.',
            ],

            'none_mine' => [
                'title' => 'Hier sind keine Ihrer Aufträge.',
                'body' => 'Ein Auftrag gilt als Ihrer, sobald er eine Arbeitsposition auf Ihren Namen hat.',
                'clear' => 'Aufträge aller anzeigen',
            ],
        ],
    ],

    'form' => [
        'create_title' => 'Neuer Auftrag',
        'appointment' => 'Termin',
        'appointment_summary' => ':time · gebucht von :name',
        'vehicle' => 'Fahrzeug',
        'complaint' => 'Beanstandung',
        'complaint_placeholder' => 'Was ist laut Kunde defekt? Mit seinen Worten.',
        'mileage_in' => 'Kilometerstand bei Annahme (km, optional)',
        'last_known_mileage' => 'Zuletzt bekannt: :mileage km',
        'submit_walk_in' => 'Auftrag öffnen',
        'submit_from_appointment' => 'Eingetroffen & Auftrag öffnen',
        'back' => 'Abbrechen',
    ],

    'validation' => [
        'appointment_not_booked' => 'Dieser Termin ist nicht mehr gebucht und kann daher nicht als eingetroffen markiert werden.',
    ],

    'show' => [
        'complaint' => 'Beanstandung',
        'details' => 'Auftragsdetails',
        'mileage_in' => 'Kilometerstand bei Annahme',
        'mileage_value' => ':mileage km',
        'origin' => 'Herkunft',
        'origin_booking' => 'Gebucht für :time',
        'walk_in' => 'Ohne Termin',
        'opened_by' => 'Geöffnet von',
        'received' => 'Angenommen',
        'started' => 'Begonnen',
        'awaiting_parts' => 'Wartet auf Teile',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Abgebrochen',
        'line_items' => 'Positionen',
        'line_items_empty' => 'Noch keine Positionen erfasst.',
        'attachments' => 'Fotos & Dokumente',
        'attachments_empty' => 'Noch nichts hochgeladen.',

        'lock' => [
            'title' => 'Gesperrt — abgerechnet am :date',
            'body' => 'Die Positionen sind schreibgeschützt. Ein Administrator muss die Rechnung stornieren, um diesen Auftrag wieder zu öffnen.',
            'invoice' => 'Rechnung',
        ],
    ],

    'items' => [
        'types' => [
            'labor' => 'Arbeit',
            'part' => 'Teil',
        ],

        'headers' => [
            'type' => 'Art',
            'description' => 'Beschreibung',
            'quantity' => 'Menge',
            'price' => 'Preis',
            'discount' => 'Rabatt',
            'line_total' => 'Gesamt',
            'who' => 'Wer',
            'actions' => 'Aktionen',
        ],

        'subtotal' => 'Zwischensumme',
        'complete_hint' => 'Fügen Sie mindestens eine Position hinzu, bevor dieser Auftrag abgeschlossen werden kann.',
        'discount_value' => ':percent %',
        'discount_total' => 'Rabatt',

        'add' => [
            'title' => 'Arbeit hinzufügen',
            'description' => 'Beschreibung',
            'description_placeholder' => 'Was wurde gemacht?',
            'description_placeholder_part' => 'Optionale Notiz zu diesem Teil',
            'quantity' => 'Stunden',
            'who' => 'Ausgeführt von',
            'who_placeholder' => 'Person wählen',
            'who_unassigned' => 'Nicht zugewiesen',
            'rate' => 'Stundensatz',
            'submit' => 'Position hinzufügen',
            'group_label' => 'Position hinzufügen',
            'labor_link' => '+ Arbeit',
            'labor_link_label' => 'Arbeitsposition hinzufügen',
            'part_link' => '+ Teil',
            'part_link_label' => 'Teileposition hinzufügen',
            'no_parts' => 'Der Teilekatalog ist leer, daher kann noch kein Teil hinzugefügt werden.',
            'part_title' => 'Teil hinzufügen',
            'part' => 'Teil',
            'quantity_units' => 'Menge',
            'price' => 'Einzelpreis',
            'price_placeholder' => 'Katalogpreis',
            'submit_part' => 'Teil hinzufügen',
        ],

        'edit' => [
            'title_for' => 'Position bearbeiten: :description',
            'price' => 'Preis',
            'row_marker' => 'Diese Position wird unten bearbeitet.',
            'open' => 'Bearbeiten',
            'open_line' => ':description bearbeiten',
            'save' => 'Speichern',
            'cancel' => 'Abbrechen',
        ],

        'discount' => [
            'open' => 'Rabatt',
            'open_line' => 'Rabatt für :description',
            'title_for' => 'Rabatt: :description',
            'percent' => 'Rabatt %',
            'hint' => 'Der Einzelpreis bleibt unverändert. Geben Sie 0 ein, um den Rabatt zu entfernen.',
            'save' => 'Rabatt speichern',
            'cancel' => 'Abbrechen',
            'row_marker' => 'Der Rabatt dieser Position wird unten festgelegt.',
        ],

        'delete' => [
            'label' => 'Löschen',
            'label_line' => ':description löschen',
            'confirm' => '„:description“ löschen? Positionen werden endgültig gelöscht.',
        ],

        'flash' => [
            'added' => 'Position hinzugefügt.',
            'part_added' => 'Teileposition hinzugefügt und aus dem Regal entnommen.',
            'updated' => 'Position aktualisiert.',
            'deleted' => 'Position gelöscht.',
            'discounted' => 'Rabatt gespeichert.',
            'not_accepting' => 'Dieser Auftrag nimmt keine Positionsänderungen mehr an. Möglicherweise wurde er inzwischen abgerechnet oder abgeschlossen.',
            'stock_negative' => ':name steht jetzt bei :balance. Der Bestand ist negativ.',
        ],
    ],

    'attachments' => [
        'types' => [
            'photo' => 'Foto',
            'document' => 'Dokument',
        ],

        'photos_label' => 'Fotos',
        'documents_label' => 'Dokumente',
        'uploaded_by' => ':name · :date',
        'photo_alt' => 'Foto zu Auftrag :reference, hochgeladen von :name',
        'open_photo' => 'Foto zu Auftrag :reference in voller Größe öffnen, hochgeladen von :name am :date',

        'upload' => [
            'label' => 'Foto oder Dokument hinzufügen',
            'action' => 'Hochladen',
            'action_for' => 'Datei zu Auftrag :reference hochladen',
            'hint' => 'JPEG, PNG oder WebP bis 10 MB oder ein PDF bis 20 MB.',
        ],

        'download' => [
            'action' => 'Herunterladen',
            'action_for' => ':filename herunterladen',
        ],

        'delete' => [
            'action' => 'Löschen',
            'action_for' => ':filename löschen',
            'confirm' => ':filename löschen? Die Datei wird endgültig entfernt und kann nicht wiederhergestellt werden.',
        ],

        'lightbox' => [
            'label' => 'Fotoanzeige',
            'close' => 'Schließen',
        ],

        'flash' => [
            'uploaded' => 'Datei hochgeladen.',
            'deleted' => 'Datei gelöscht.',
            'unreadable' => 'Diese Datei konnte nicht als Bild gelesen werden und wurde nicht gespeichert.',
        ],
    ],

    'history' => [
        'view_all' => 'Alle :count Aufträge anzeigen',

        'headers' => [
            'reference' => 'Auftrag',
            'plate' => 'Kennzeichen',
            'customer' => 'Kunde',
            'status' => 'Status',
            'received' => 'Angenommen',
            'mileage' => 'Kilometerstand',
        ],
    ],

    'actions' => [
        'start' => 'Beginnen',
        'pause' => 'Auf Teile warten',
        'resume' => 'Fortsetzen',
        'complete' => 'Abschließen',
        'cancel' => 'Auftrag abbrechen',
        'reopen' => 'Wieder öffnen',
        'start_for' => 'Auftrag :reference für :plate beginnen',
        'pause_for' => 'Auftrag :reference für :plate: auf Teile warten',
        'resume_for' => 'Auftrag :reference für :plate fortsetzen',
        'complete_for' => 'Auftrag :reference für :plate abschließen',
        'cancel_for' => 'Auftrag :reference für :plate abbrechen',
        'reopen_for' => 'Auftrag :reference für :plate wieder öffnen',
        'issue' => 'Rechnung ausstellen',
        'issue_for' => 'Rechnung für Auftrag :reference für :plate ausstellen',
        'confirm_cancel' => 'Auftrag :reference für :plate abbrechen? Der Abbruch ist endgültig — ein abgebrochener Auftrag kann nicht wieder geöffnet werden.',
        'confirm_cancel_with_parts' => '{1} Auftrag :reference für :plate abbrechen? Eine Teileposition wird in den Bestand zurückgebucht. Der Abbruch ist endgültig — ein abgebrochener Auftrag kann nicht wieder geöffnet werden.|[2,*] Auftrag :reference für :plate abbrechen? :count Teilepositionen werden in den Bestand zurückgebucht. Der Abbruch ist endgültig — ein abgebrochener Auftrag kann nicht wieder geöffnet werden.',
    ],

    'flash' => [
        'created' => 'Auftrag :reference geöffnet.',
        'mileage_lower' => 'Der eingegebene Kilometerstand (:entered km) liegt unter dem zuletzt bekannten Stand von :stored km, daher wurde der Kilometerstand des Fahrzeugs nicht aktualisiert.',
        'started' => 'Auftrag :reference begonnen.',
        'paused' => 'Auftrag :reference wartet auf Teile.',
        'resumed' => 'Auftrag :reference fortgesetzt.',
        'completed' => 'Auftrag :reference abgeschlossen.',
        'cancelled' => 'Auftrag :reference abgebrochen.',
        'reopened' => 'Auftrag :reference wieder geöffnet.',
    ],
];
