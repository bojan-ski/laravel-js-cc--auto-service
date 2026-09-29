<?php

return [
    'title' => 'Kunden',

    'index' => [
        'search_label' => 'Kunden suchen',
        'search_placeholder' => 'Name, Telefon, Kennzeichen oder Fahrzeug',
        'new' => 'Neuer Kunde',
        'count' => ':count Kunde|:count Kunden',

        'headers' => [
            'name' => 'Name',
            'phone' => 'Telefon',
            'vehicles' => 'Fahrzeuge',
            'email' => 'E-Mail',
        ],
    ],

    'empty' => [
        'none' => [
            'title' => 'Noch keine Kunden.',
            'body' => 'Legen Sie den ersten Kunden an, um Aufträge anzunehmen.',
        ],

        'no_matches' => [
            'title' => 'Kein Kunde passt zu dieser Suche.',
            'body' => 'Prüfen Sie die Schreibweise oder versuchen Sie einen Teil der Telefonnummer.',
        ],
    ],

    'show' => [
        'contact' => 'Kontaktdaten',
        'phone' => 'Telefon',
        'email' => 'E-Mail',
        'address' => 'Adresse',
        'tax_id' => 'Steuernr. (PIB)',
        'registration_number' => 'Registernr. (MB)',
        'notes' => 'Notizen',
        'jobs' => 'Auftragshistorie',
        'jobs_empty' => 'Noch keine Aufträge erfasst.',
        'jobs_view_all_for' => 'Alle :count Aufträge für :name anzeigen',
    ],

    'create' => [
        'title' => 'Neuer Kunde',
        'submit' => 'Kunde anlegen',
    ],

    'edit' => [
        'title' => ':name bearbeiten',
        'submit' => 'Änderungen speichern',
    ],

    'form' => [
        'name' => 'Name',
        'phone' => 'Telefon',
        'email' => 'E-Mail (optional)',
        'address' => 'Adresse (optional)',
        'company' => 'Firma',
        'company_hint' => 'Bei einer Firma PIB und MB angeben. Bei einer Privatperson beide Felder leer lassen.',
        'tax_id' => 'Steuernr. (PIB)',
        'registration_number' => 'Registernr. (MB)',
        'notes' => 'Notizen (optional)',
    ],

    'validation' => [
        'phone_taken' => 'Diese Telefonnummer gehört bereits zu :name.',
        'tax_id_digits' => 'Eine PIB hat genau 9 Ziffern.',
        'tax_id_checksum' => 'Die Prüfziffer dieser PIB ist falsch. Bitte die Nummer auf Tippfehler prüfen.',
        'registration_number_digits' => 'Eine MB hat genau 8 Ziffern.',
        'registration_number_required' => 'Eine Firma braucht neben der PIB auch die MB.',
        'registration_number_without_tax_id' => 'Eine MB wird nur für eine Firma erfasst. Bitte auch die PIB angeben oder die MB leer lassen.',
    ],

    'intake' => [
        'legend' => 'Kunde und Fahrzeug',
        'customer' => 'Kunde',
        'vehicle' => 'Fahrzeug',
        'attributes' => [
            'new_customer_name' => 'Name',
            'new_customer_phone' => 'Telefon',
            'new_customer_email' => 'E-Mail',
            'new_vehicle_plate' => 'Kennzeichen',
            'new_vehicle_make' => 'Hersteller',
            'new_vehicle_model' => 'Modell',
            'new_vehicle_year' => 'Baujahr',
        ],
        'search_label' => 'Kunden suchen',
        'search_hint' => 'Name, Telefon oder Kennzeichen. Leer lassen, um unten einen neuen Kunden anzulegen.',
        'results' => ':count Kunde gefunden|:count Kunden gefunden',
        'results_none' => 'Kein Kunde gefunden. Erfassen Sie unten den neuen Kunden.',
        'picked' => 'Kunde',
        'change' => 'Kunden wechseln',
        'new_customer' => 'Neuer Kunde',
        'existing_vehicle' => 'Vorhandenes Fahrzeug',
        'existing_vehicle_placeholder' => 'Fahrzeug wählen oder unten einen neuen Kunden anlegen',
        'vehicle_legend' => 'Fahrzeug',
        'new_vehicle_option' => 'Neues Fahrzeug',
        'new_vehicle' => 'Neues Fahrzeug',
        'locked_hint' => 'Geben Sie zuerst Name und Telefon des Kunden ein.',
        'required_customer' => 'Füllen Sie beim neuen Kunden das Feld „:attribute“ aus oder suchen Sie einen bestehenden Kunden.',
        'required_vehicle' => 'Füllen Sie beim Fahrzeug das Feld „:attribute“ aus oder wählen Sie ein vorhandenes Fahrzeug.',
        'vehicle_not_theirs' => 'Dieses Fahrzeug gehört nicht zum gewählten Kunden.',
        'plate_taken' => ':plate gehört bereits zu :owner.',
    ],

    'delete' => [
        'action' => ':name löschen',
        'confirm' => ':name löschen? Der Kunde erscheint dann nicht mehr in der Kundenliste. Es wird nichts endgültig entfernt.',
    ],

    'flash' => [
        'created' => ':name wurde angelegt.',
        'updated' => ':name wurde aktualisiert.',
        'deleted' => ':name wurde gelöscht.',
    ],
];
