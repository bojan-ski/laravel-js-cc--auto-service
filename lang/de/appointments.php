<?php

return [
    'statuses' => [
        'booked' => 'Gebucht',
        'arrived' => 'Eingetroffen',
        'no_show' => 'Nicht erschienen',
        'cancelled' => 'Abgesagt',
    ],

    'title' => 'Termine',

    'index' => [
        'new_appointment' => 'Neuer Termin',
        'set_hours' => 'Zeiten festlegen',
        'view_label' => 'Ansicht',
        'day' => 'Tag',
        'week' => 'Woche',
        'previous' => 'Zurück',
        'next' => 'Weiter',
        'today' => 'Heute',
        'status_label' => 'Status',
        'status_all' => 'Alle',
        'count' => '{0}Keine Termine|{1}1 Termin|[2,*]:count Termine',
        'nothing_booked' => 'Nichts gebucht',
        'week_range' => ':start — :end',
    ],

    'columns' => [
        'time' => 'Zeit',
        'plate' => 'Kennzeichen',
        'customer' => 'Kunde',
        'status' => 'Status',
    ],

    'empty' => [
        'none_title' => 'Noch keine Termine',
        'none_body' => 'Buchen Sie den ersten Termin, damit er hier erscheint.',
        'window_title' => 'In diesem Zeitraum ist nichts gebucht',
        'window_body' => 'Springen Sie zurück zu heute oder wechseln Sie zu einem anderen Tag oder einer anderen Woche.',
        'filtered_title' => 'Keine Termine mit diesem Status',
        'filtered_body' => 'Setzen Sie den Statusfilter zurück, um alle Termine dieses Zeitraums zu sehen.',
        'clear_filter' => 'Statusfilter zurücksetzen',
    ],

    'show' => [
        'title' => ':plate, gebucht für :time',
        'details' => 'Termindetails',
        'customer' => 'Kunde',
        'duration' => 'Dauer',
        'duration_value' => ':minutes Minuten',
        'notes' => 'Notizen',
        'booked_by' => 'Gebucht von',
        'arrived' => 'Eingetroffen',
        'no_show' => 'Als nicht erschienen markiert',
        'cancelled' => 'Abgesagt',
        'job' => 'Auftrag',
    ],

    'actions' => [
        'arrived' => 'Eingetroffen',
        'no_show' => 'Nicht erschienen',
        'cancel' => 'Termin absagen',
        'edit' => 'Verschieben',
        'mark_arrived_for' => 'Eingetroffen: Auftrag für :plate öffnen, gebucht für :time',
        'mark_no_show_for' => ':plate um :time als nicht erschienen markieren',
        'cancel_for' => 'Termin für :plate um :time absagen',
        'edit_for' => ':plate um :time verschieben',
        'confirm_cancel' => 'Termin für :plate um :time absagen? Dies kann nicht rückgängig gemacht werden.',
    ],

    'form' => [
        'create_title' => 'Neuer Termin',
        'edit_title' => 'Termin verschieben',
        'vehicle_fixed' => 'Fahrzeug',
        'vehicle_fixed_hint' => 'Das Fahrzeug kann nicht geändert werden. Sagen Sie diesen Termin ab und legen Sie stattdessen einen neuen an.',
        'scheduled_at' => 'Datum und Uhrzeit',
        'scheduled_at_hint' => 'Ab morgen, Beginn und Ende zwischen :open und :close.',
        'duration_minutes' => 'Dauer (Minuten)',
        'notes' => 'Notizen',
        'submit_create' => 'Buchen',
        'submit_update' => 'Änderungen speichern',
        'back' => 'Abbrechen',
    ],

    'overlap' => [
        'warning' => '{1}1 weiteres Fahrzeug ist in diesem Zeitraum bereits gebucht.|[2,*]:count weitere Fahrzeuge sind in diesem Zeitraum bereits gebucht.',
    ],

    'validation' => [
        'not_before_tomorrow' => 'Termine können erst ab morgen gebucht werden.',
        'outside_hours' => 'Der Termin muss zwischen :open und :close beginnen und enden.',
    ],

    'hours' => [
        'title' => 'Terminzeiten',
        'intro' => 'Neue und verschobene Termine müssen innerhalb dieser Zeiten beginnen und enden. Bestehende Termine bleiben unverändert.',
        'open_at' => 'Beginn',
        'close_at' => 'Ende',
        'close_after_open' => 'Das Ende muss nach dem Beginn liegen.',
        'submit' => 'Zeiten speichern',
        'cancel' => 'Abbrechen',
    ],

    'flash' => [
        'created' => 'Termin für :plate angelegt.',
        'updated' => 'Termin für :plate aktualisiert.',
        'no_show' => ':plate als nicht erschienen markiert.',
        'cancelled' => 'Termin für :plate abgesagt.',
        'hours_updated' => 'Terminzeiten auf :open – :close festgelegt.',
    ],
];
