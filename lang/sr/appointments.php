<?php

return [
    'statuses' => [
        'booked' => 'Zakazan',
        'arrived' => 'Stigao',
        'no_show' => 'Nije došao',
        'cancelled' => 'Otkazan',
    ],

    'title' => 'Termini',

    'index' => [
        'new_appointment' => 'Novi termin',
        'set_hours' => 'Podesi radno vreme',
        'view_label' => 'Prikaz',
        'day' => 'Dan',
        'week' => 'Nedelja',
        'previous' => 'Prethodno',
        'next' => 'Sledeće',
        'today' => 'Danas',
        'status_label' => 'Status',
        'status_all' => 'Svi',
        'count' => ':count termin|:count termina|:count termina|{0}Nema termina',
        'nothing_booked' => 'Nema zakazanih termina',
        'week_range' => ':start — :end',
    ],

    'columns' => [
        'time' => 'Vreme',
        'plate' => 'Tablice',
        'customer' => 'Klijent',
        'status' => 'Status',
    ],

    'empty' => [
        'none_title' => 'Još nema termina',
        'none_body' => 'Zakažite prvi termin i on će se pojaviti ovde.',
        'window_title' => 'Nema zakazanih termina u ovom periodu',
        'window_body' => 'Vratite se na današnji dan ili pređite na drugi dan ili nedelju.',
        'filtered_title' => 'Nijedan termin nema ovaj status',
        'filtered_body' => 'Uklonite filter statusa da biste videli sve u ovom periodu.',
        'clear_filter' => 'Ukloni filter statusa',
    ],

    'show' => [
        'title' => ':plate, zakazano za :time',
        'details' => 'Detalji termina',
        'customer' => 'Klijent',
        'duration' => 'Trajanje',
        'duration_value' => ':minutes min',
        'notes' => 'Napomene',
        'booked_by' => 'Zakazao/la',
        'arrived' => 'Stigao',
        'no_show' => 'Označeno da nije došao',
        'cancelled' => 'Otkazan',
        'job' => 'Radni nalog',
    ],

    'actions' => [
        'arrived' => 'Stigao',
        'no_show' => 'Nije došao',
        'cancel' => 'Otkaži termin',
        'edit' => 'Pomeri termin',
        'mark_arrived_for' => 'Stigao: otvori radni nalog za :plate zakazano za :time',
        'mark_no_show_for' => 'Označi da :plate u :time nije došao',
        'cancel_for' => 'Otkaži termin za :plate u :time',
        'edit_for' => 'Pomeri termin za :plate u :time',
        'confirm_cancel' => 'Otkazati termin za :plate u :time? Ova radnja se ne može poništiti.',
    ],

    'form' => [
        'create_title' => 'Novi termin',
        'edit_title' => 'Pomeranje termina',
        'vehicle_fixed' => 'Vozilo',
        'vehicle_fixed_hint' => 'Vozilo se ne može promeniti. Umesto toga otkažite ovaj termin i zakažite novi.',
        'scheduled_at' => 'Datum i vreme',
        'scheduled_at_hint' => 'Od sutra nadalje, s početkom i završetkom između :open i :close.',
        'duration_minutes' => 'Trajanje (minuti)',
        'notes' => 'Napomene',
        'submit_create' => 'Zakaži',
        'submit_update' => 'Sačuvaj izmene',
        'back' => 'Otkaži',
    ],

    'overlap' => [
        'warning' => ':count drugo vozilo je već zakazano u istom periodu.|:count druga vozila su već zakazana u istom periodu.|:count drugih vozila je već zakazano u istom periodu.',
    ],

    'validation' => [
        'not_before_tomorrow' => 'Termini se mogu zakazati najranije za sutra.',
        'outside_hours' => 'Termin mora početi i završiti se između :open i :close.',
    ],

    'hours' => [
        'title' => 'Radno vreme za termine',
        'intro' => 'Novi i pomereni termini moraju početi i završiti se u okviru ovog radnog vremena. Postojeći termini se ne menjaju.',
        'open_at' => 'Početak',
        'close_at' => 'Kraj',
        'close_after_open' => 'Vreme završetka mora biti posle vremena početka.',
        'submit' => 'Sačuvaj radno vreme',
        'cancel' => 'Otkaži',
    ],

    'flash' => [
        'created' => 'Termin za :plate je zakazan.',
        'updated' => 'Termin za :plate je izmenjen.',
        'no_show' => 'Za :plate je zabeleženo da nije došao.',
        'cancelled' => 'Termin za :plate je otkazan.',
        'hours_updated' => 'Radno vreme za termine podešeno je na :open – :close.',
    ],
];
