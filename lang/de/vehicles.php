<?php

return [
    'section' => [
        'title' => 'Fahrzeuge',
        'empty' => 'Noch keine Fahrzeuge erfasst.',
        'add' => 'Fahrzeug hinzufügen',
        'add_for' => 'Fahrzeug hinzufügen für :name',

        'headers' => [
            'plate' => 'Kennzeichen',
            'vehicle' => 'Fahrzeug',
            'year' => 'Baujahr',
            'actions' => 'Aktionen',
        ],
    ],

    'deleted' => [
        'title' => 'Gelöschte Fahrzeuge',
        'intro' => 'Diese Fahrzeuge sind überall sonst ausgeblendet. Ihre Kennzeichen bleiben reserviert, bis sie wiederhergestellt werden.',
        'restore' => 'Wiederherstellen',
        'restore_vehicle' => ':plate wiederherstellen',
        'confirm' => ':plate wiederherstellen? Das Fahrzeug kehrt mit Kennzeichen und Historie vollständig zu diesem Kunden zurück.',
    ],

    'show' => [
        'details' => 'Fahrzeugdetails',
        'owner' => 'Halter',
        'year' => 'Baujahr',
        'engine' => 'Motor',
        'vin' => 'FIN',
        'mileage' => 'Kilometerstand',
        'notes' => 'Notizen',
        'mileage_value' => ':mileage km',
        'next_service_on' => 'Nächster Service',
        'next_service_mileage' => 'Nächster Service bei',
        'registration_expires_on' => 'Zulassung läuft ab',
        'history' => 'Servicehistorie',
        'history_empty' => 'Für dieses Fahrzeug sind noch keine Aufträge erfasst.',
        'history_view_all_for' => 'Alle :count Aufträge für :plate anzeigen',
    ],

    'reminder_kinds' => [
        'service' => 'Service',
        'registration' => 'Zulassung',
    ],

    'next_service' => [
        'label' => 'Nächster Service',
        'set' => 'Nächsten Service festlegen',
        'set_for' => 'Nächsten Service für :plate festlegen',
        'title' => 'Nächster Service — :plate',
        'context' => 'Auftrag :reference, abgeschlossen am :date',
        'context_mileage' => 'Auftrag :reference, abgeschlossen am :date bei :mileage km',
        'submit' => 'Speichern',
    ],

    'create' => [
        'title' => 'Neues Fahrzeug',
        'for' => 'Für :name',
        'submit' => 'Fahrzeug hinzufügen',
    ],

    'edit' => [
        'title' => ':plate bearbeiten',
        'submit' => 'Änderungen speichern',
    ],

    'picker' => [
        'option' => ':plate · :vehicle · :owner',
        'owner_deleted' => ':name (gelöscht)',
    ],

    'form' => [
        'owner' => 'Halter',
        'registration_plate' => 'Kennzeichen',
        'make' => 'Hersteller',
        'model' => 'Modell',
        'year' => 'Baujahr (optional)',
        'vin' => 'FIN (optional)',
        'engine' => 'Motor (optional)',
        'mileage' => 'Kilometerstand (optional)',
        'next_service_on' => 'Datum des nächsten Service (optional)',
        'next_service_mileage' => 'Kilometerstand für den nächsten Service (optional)',
        'registration_expires_on' => 'Zulassung läuft ab (optional)',
        'notes' => 'Notizen (optional)',
    ],

    'delete' => [
        'action' => ':plate löschen',
        'confirm' => ':plate löschen? Das Kennzeichen bleibt reserviert und kann erst wieder für ein anderes Fahrzeug verwendet werden, wenn dieses wiederhergestellt ist.',
    ],

    'reassign' => [
        'action' => 'Umschreiben',
        'action_vehicle' => ':plate auf einen anderen Kunden umschreiben',
        'title' => ':plate umschreiben',
        'current_owner' => 'Aktueller Halter',
        'new_owner' => 'Neuer Halter',
        'placeholder' => 'Kunden wählen',
        'warning_title' => 'Die Historie bleibt erhalten',
        'warning' => 'Nur das Fahrzeug wechselt. Bestehende Aufträge und Rechnungen behalten :name als Kunden, sodass vergangene Arbeiten nie neu abgerechnet werden.',
        'submit' => 'Fahrzeug umschreiben',
        'confirm' => ':plate von :name auf :selected umschreiben? Bestehende Aufträge und Rechnungen behalten :name als Kunden.',
    ],

    'validation' => [
        'plate_deleted' => 'Dieses Kennzeichen gehört zu einem gelöschten Fahrzeug von :owner. Ein Administrator kann es auf der Seite dieses Kunden wiederherstellen.',
        'vin_deleted' => 'Diese FIN gehört zu einem gelöschten Fahrzeug von :owner. Ein Administrator kann es auf der Seite dieses Kunden wiederherstellen.',
        'already_owner' => 'Dieser Kunde ist bereits Halter dieses Fahrzeugs.',
    ],

    'flash' => [
        'created' => ':plate wurde hinzugefügt.',
        'updated' => ':plate wurde aktualisiert.',
        'deleted' => ':plate wurde gelöscht. Das Kennzeichen bleibt reserviert.',
        'restored' => ':plate wurde wiederhergestellt.',
        'reassigned' => ':plate gehört jetzt :name.',
        'next_service_saved' => 'Nächster Service für :plate gespeichert.',
    ],
];
