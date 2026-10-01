<?php

return [
    'title' => 'Početna',

    'today' => [
        'title' => 'Danas',
        'empty' => 'Danas nema zakazanih termina.',
        'arrived' => 'Stigao',
        'view_all' => 'Svi današnji termini',
    ],

    'active_jobs' => [
        'title' => 'Aktivni radni nalozi (:count)|Aktivni radni nalozi (:count)|Aktivni radni nalozi (:count)|{0}Aktivni radni nalozi',
        'empty' => 'U radionici nema radnih naloga.',
        'view_all' => 'Svi aktivni radni nalozi',
        'view_job' => 'Radni nalog :reference',
    ],

    'low_stock' => [
        'title' => 'Niska zaliha (:count)|Niska zaliha (:count)|Niska zaliha (:count)|{0}Niska zaliha',
        'empty' => 'Ništa ne treba naručiti.',
        'view_all' => 'Svi delovi za naručivanje',
        'out_of_stock' => 'nema na stanju',
    ],

    'due_soon' => [
        'title' => 'Uskoro ističe (:count)|Uskoro ističe (:count)|Uskoro ističe (:count)|{0}Uskoro ističe',
        'overdue' => 'Prošao rok',
        'emailed' => 'Poslat email :date',
        'pending' => 'Email na čekanju',
        'more' => 'i još :count|i još :count|i još :count',
        'empty' => 'Ništa ne ističe u narednih 30 dana.',
    ],
];
