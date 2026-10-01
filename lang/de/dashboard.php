<?php

return [
    'title' => 'Übersicht',

    'today' => [
        'title' => 'Heute',
        'empty' => 'Heute ist nichts gebucht.',
        'arrived' => 'Eingetroffen',
        'view_all' => 'Alle heutigen Termine',
    ],

    'active_jobs' => [
        'title' => '{0} Aktive Aufträge|{1} Aktive Aufträge (1)|[2,*] Aktive Aufträge (:count)',
        'empty' => 'Keine Aufträge in der Werkstatt.',
        'view_all' => 'Alle aktiven Aufträge anzeigen',
        'view_job' => 'Auftrag :reference',
    ],

    'low_stock' => [
        'title' => '{0} Niedriger Bestand|{1} Niedriger Bestand (1)|[2,*] Niedriger Bestand (:count)',
        'empty' => 'Nichts nachzubestellen.',
        'view_all' => 'Alle nachzubestellenden Teile',
        'out_of_stock' => 'nicht vorrätig',
    ],

    'due_soon' => [
        'title' => '{0} Bald fällig|{1} Bald fällig (1)|[2,*] Bald fällig (:count)',
        'overdue' => 'Überfällig',
        'emailed' => 'E-Mail am :date gesendet',
        'pending' => 'E-Mail ausstehend',
        'more' => 'und :count weitere|und :count weitere',
        'empty' => 'In den nächsten 30 Tagen ist nichts fällig.',
    ],
];
