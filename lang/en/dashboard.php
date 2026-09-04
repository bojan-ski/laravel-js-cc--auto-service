<?php

return [
    'title' => 'Dashboard',

    'today' => [
        'title' => 'Today',
        'empty' => 'Nothing booked today.',
        'arrived' => 'Arrived',
        'view_all' => "All of today's appointments",
    ],

    'active_jobs' => [
        'title' => '{0} Active jobs|{1} Active jobs (1)|[2,*] Active jobs (:count)',
        'empty' => 'No jobs in the shop.',
        'view_all' => 'All active jobs on the board',
        'view_job' => 'Job :reference',
    ],

    'low_stock' => [
        'title' => '{0} Low stock|{1} Low stock (1)|[2,*] Low stock (:count)',
        'empty' => 'Nothing to reorder.',
        'view_all' => 'All parts to reorder',
        'out_of_stock' => 'out of stock',
    ],
];
