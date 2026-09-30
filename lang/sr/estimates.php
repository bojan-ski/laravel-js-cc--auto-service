<?php

return [
    'statuses' => [
        'pending' => 'Na čekanju',
        'approved' => 'Odobren',
        'declined' => 'Odbijen',
        'superseded' => 'Zamenjen',
    ],

    'decision_methods' => [
        'in_person' => 'Lično',
        'phone' => 'Telefonom',
        'email' => 'Imejlom',
    ],

    'show' => [
        'title' => 'Predračun :number',
        'details' => 'Detalji predračuna',
        'actions' => 'Radnje',
        'job' => 'Nalog',
        'customer' => 'Klijent',
        'vehicle' => 'Vozilo',
        'created' => 'Kreiran',
        'created_by' => 'Kreirao',
        'decided' => 'Odluka',
        'decided_by' => 'Evidentirao',
        'decision_method' => 'Način',
        'decision_note' => 'Napomena',
        'superseded' => 'Zamenjen',
        'emailed' => 'Poslednji imejl',
        'print' => 'Štampaj',
        'back' => 'Nazad na nalog',
        'superseded_notice' => 'Ovaj predračun je zamenjen :date. Čuva se kao zapis.',
        'history' => 'Predračuni za ovaj nalog',
    ],

    'history_headers' => [
        'number' => 'Broj',
        'status' => 'Status',
        'created' => 'Kreiran',
        'total' => 'Ukupno',
    ],

    'identity' => [
        'heading' => 'Predračun :number',
        'created' => 'Datum :date',
        'prepared_for' => 'Pripremljeno za',
    ],

    'decision' => [
        'title' => 'Evidentirajte odluku klijenta',
        'decision' => 'Odluka',
        'approve' => 'Odobren',
        'decline' => 'Odbijen',
        'method' => 'Kako je klijent odgovorio',
        'note' => 'Napomena',
        'submit' => 'Evidentiraj odluku',
        'submit_for' => 'Evidentiraj odluku za predračun :number',
    ],

    'email' => [
        'action' => 'Pošalji klijentu imejlom',
        'action_for' => 'Pošalji klijentu imejlom: predračun :number',
    ],

    'create' => [
        'action' => 'Napravi predračun',
        'action_for' => 'Napravi predračun za :reference',
        'revise' => 'Izmeni predračun',
        'revise_for' => 'Izmeni predračun za :reference',
        'revise_confirm' => 'Ovim se pravi novi predračun od trenutnih stavki. Predračun :number biće označen kao zamenjen.',
    ],

    'summary' => [
        'label' => 'Predračun',
        'none' => 'Nema predračuna',
        'method' => '(:method)',
        'view' => 'Prikaži',
        'view_for' => 'Prikaži predračun :number',
    ],

    'warning' => [
        'over' => 'Trenutni iznos je :amount veći od odobrenog predračuna :number.',
        'not_approved' => 'Predračun :number nije odobren.',
        'declined' => 'Predračun :number je odbijen.',
    ],

    'flash' => [
        'created' => 'Predračun :number je napravljen.',
        'approved' => 'Odobrenje je evidentirano na predračunu :number.',
        'declined' => 'Odbijanje je evidentirano na predračunu :number.',
        'emailed' => 'Predračun :number se šalje na :email.',
        'no_email' => 'Klijent nema imejl adresu, pa predračun nije poslat.',
        'not_creatable' => 'Za predračun je potreban nalog u radu sa bar jednom stavkom.',
        'not_decidable' => 'Za taj predračun je odluka već evidentirana ili je zamenjen.',
        'not_emailable' => 'Imejlom se može poslati samo predračun na čekanju ili odobren.',
    ],
];
