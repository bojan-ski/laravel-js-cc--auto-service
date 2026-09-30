<?php

return [
    'statuses' => [
        'pending' => 'Offen',
        'approved' => 'Freigegeben',
        'declined' => 'Abgelehnt',
        'superseded' => 'Ersetzt',
    ],

    'decision_methods' => [
        'in_person' => 'Persönlich',
        'phone' => 'Telefonisch',
        'email' => 'Per E-Mail',
    ],

    'show' => [
        'title' => 'Kostenvoranschlag :number',
        'details' => 'Details zum Kostenvoranschlag',
        'actions' => 'Aktionen',
        'job' => 'Auftrag',
        'customer' => 'Kunde',
        'vehicle' => 'Fahrzeug',
        'created' => 'Erstellt',
        'created_by' => 'Erstellt von',
        'decided' => 'Entschieden',
        'decided_by' => 'Erfasst von',
        'decision_method' => 'Wie',
        'decision_note' => 'Notiz',
        'superseded' => 'Ersetzt',
        'emailed' => 'Zuletzt gemailt',
        'print' => 'Drucken',
        'back' => 'Zurück zum Auftrag',
        'superseded_notice' => 'Dieser Kostenvoranschlag wurde am :date ersetzt. Er bleibt als Nachweis erhalten.',
        'history' => 'Kostenvoranschläge zu diesem Auftrag',
    ],

    'history_headers' => [
        'number' => 'Nummer',
        'status' => 'Status',
        'created' => 'Erstellt',
        'total' => 'Gesamt',
    ],

    'identity' => [
        'heading' => 'Kostenvoranschlag :number',
        'created' => 'Datum :date',
        'prepared_for' => 'Erstellt für',
    ],

    'decision' => [
        'title' => 'Entscheidung des Kunden erfassen',
        'decision' => 'Entscheidung',
        'approve' => 'Freigegeben',
        'decline' => 'Abgelehnt',
        'method' => 'Wie der Kunde geantwortet hat',
        'note' => 'Notiz',
        'submit' => 'Entscheidung erfassen',
        'submit_for' => 'Entscheidung erfassen für Kostenvoranschlag :number',
    ],

    'email' => [
        'action' => 'An Kunden mailen',
        'action_for' => 'An Kunden mailen: Kostenvoranschlag :number',
    ],

    'create' => [
        'action' => 'Kostenvoranschlag erstellen',
        'action_for' => 'Kostenvoranschlag erstellen für :reference',
        'revise' => 'Kostenvoranschlag überarbeiten',
        'revise_for' => 'Kostenvoranschlag überarbeiten für :reference',
        'revise_confirm' => 'Damit wird ein neuer Kostenvoranschlag aus den aktuellen Positionen erstellt. Kostenvoranschlag :number wird als ersetzt markiert.',
    ],

    'summary' => [
        'label' => 'Kostenvoranschlag',
        'none' => 'Kein Kostenvoranschlag',
        'method' => '(:method)',
        'view' => 'Ansehen',
        'view_for' => 'Ansehen: Kostenvoranschlag :number',
    ],

    'warning' => [
        'over' => 'Der aktuelle Betrag liegt :amount über dem freigegebenen Kostenvoranschlag :number.',
        'not_approved' => 'Kostenvoranschlag :number ist nicht freigegeben.',
        'declined' => 'Kostenvoranschlag :number wurde abgelehnt.',
    ],

    'flash' => [
        'created' => 'Kostenvoranschlag :number wurde erstellt.',
        'approved' => 'Freigabe für Kostenvoranschlag :number erfasst.',
        'declined' => 'Ablehnung für Kostenvoranschlag :number erfasst.',
        'emailed' => 'Kostenvoranschlag :number wird an :email gesendet.',
        'no_email' => 'Für den Kunden ist keine E-Mail-Adresse hinterlegt, daher wurde der Kostenvoranschlag nicht gesendet.',
        'not_creatable' => 'Ein Kostenvoranschlag erfordert einen laufenden Auftrag mit mindestens einer Position.',
        'not_decidable' => 'Für diesen Kostenvoranschlag wurde bereits entschieden, oder er wurde ersetzt.',
        'not_emailable' => 'Nur ein offener oder freigegebener Kostenvoranschlag kann gemailt werden.',
    ],
];
