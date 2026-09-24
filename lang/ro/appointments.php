<?php

return [
    'statuses' => [
        'booked' => 'Programată',
        'arrived' => 'Sosită',
        'no_show' => 'Neprezentată',
        'cancelled' => 'Anulată',
    ],

    'title' => 'Programări',

    'index' => [
        'new_appointment' => 'Programare nouă',
        'set_hours' => 'Setează programul',
        'view_label' => 'Mod de afișare',
        'day' => 'Zi',
        'week' => 'Săptămână',
        'previous' => 'Anterior',
        'next' => 'Următor',
        'today' => 'Astăzi',
        'status_label' => 'Stare',
        'status_all' => 'Toate',
        'count' => ':count programare|:count programări|:count de programări|{0}Nicio programare',
        'nothing_booked' => 'Nicio programare',
        'week_range' => ':start — :end',
    ],

    'columns' => [
        'time' => 'Ora',
        'plate' => 'Nr. înmatr.',
        'customer' => 'Client',
        'status' => 'Stare',
    ],

    'empty' => [
        'none_title' => 'Nicio programare încă',
        'none_body' => 'Creați prima programare pentru a o vedea aici.',
        'window_title' => 'Nicio programare în această perioadă',
        'window_body' => 'Reveniți la ziua de azi sau treceți la altă zi ori săptămână.',
        'filtered_title' => 'Nicio programare cu această stare',
        'filtered_body' => 'Ștergeți filtrul de stare pentru a vedea toate programările din această perioadă.',
        'clear_filter' => 'Șterge filtrul de stare',
    ],

    'show' => [
        'title' => ':plate, programat pe :time',
        'details' => 'Detalii programare',
        'customer' => 'Client',
        'duration' => 'Durată',
        'duration_value' => ':minutes min',
        'notes' => 'Observații',
        'booked_by' => 'Programată de',
        'arrived' => 'Sosită',
        'no_show' => 'Marcată ca neprezentată',
        'cancelled' => 'Anulată',
        'job' => 'Lucrare',
    ],

    'actions' => [
        'arrived' => 'A sosit',
        'no_show' => 'Neprezentare',
        'cancel' => 'Anulează programarea',
        'edit' => 'Reprogramează',
        'mark_arrived_for' => 'A sosit: deschide o lucrare pentru :plate, programat pe :time',
        'mark_no_show_for' => 'Neprezentare: marchează programarea pentru :plate din :time',
        'cancel_for' => 'Anulează programarea pentru :plate din :time',
        'edit_for' => 'Reprogramează :plate, programat pe :time',
        'confirm_cancel' => 'Anulați programarea pentru :plate din :time? Această acțiune este ireversibilă.',
    ],

    'form' => [
        'create_title' => 'Programare nouă',
        'edit_title' => 'Reprogramare',
        'vehicle_fixed' => 'Vehicul',
        'vehicle_fixed_hint' => 'Vehiculul nu poate fi schimbat. Anulați această programare și creați una nouă.',
        'scheduled_at' => 'Data și ora',
        'scheduled_at_hint' => 'Începând de mâine, cu început și sfârșit între :open și :close.',
        'duration_minutes' => 'Durată (minute)',
        'notes' => 'Observații',
        'submit_create' => 'Programează',
        'submit_update' => 'Salvează modificările',
        'back' => 'Anulează',
    ],

    'overlap' => [
        'warning' => ':count altă mașină este deja programată în acest interval.|:count alte mașini sunt deja programate în acest interval.|:count de alte mașini sunt deja programate în acest interval.',
    ],

    'validation' => [
        'not_before_tomorrow' => 'Programările se pot face doar începând de mâine.',
        'outside_hours' => 'Programarea trebuie să înceapă și să se termine între :open și :close.',
    ],

    'hours' => [
        'title' => 'Program pentru programări',
        'intro' => 'Programările noi și cele reprogramate trebuie să înceapă și să se termine în acest interval. Programările existente nu se modifică.',
        'open_at' => 'Deschidere',
        'close_at' => 'Închidere',
        'close_after_open' => 'Ora de închidere trebuie să fie după ora de deschidere.',
        'submit' => 'Salvează programul',
        'cancel' => 'Anulează',
    ],

    'flash' => [
        'created' => 'Programare creată pentru :plate.',
        'updated' => 'Programare actualizată pentru :plate.',
        'no_show' => 'Programarea pentru :plate a fost marcată ca neprezentată.',
        'cancelled' => 'Programare anulată pentru :plate.',
        'hours_updated' => 'Programul pentru programări a fost setat la :open – :close.',
    ],
];
