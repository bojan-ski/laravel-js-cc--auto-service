<?php

return [
    'statuses' => [
        'pending' => 'În așteptare',
        'approved' => 'Aprobat',
        'declined' => 'Refuzat',
        'superseded' => 'Înlocuit',
    ],

    'decision_methods' => [
        'in_person' => 'În persoană',
        'phone' => 'Telefonic',
        'email' => 'Prin e-mail',
    ],

    'show' => [
        'title' => 'Deviz :number',
        'details' => 'Detaliile devizului',
        'actions' => 'Acțiuni',
        'job' => 'Lucrare',
        'customer' => 'Client',
        'vehicle' => 'Vehicul',
        'created' => 'Creat',
        'created_by' => 'Creat de',
        'decided' => 'Decis',
        'decided_by' => 'Înregistrat de',
        'decision_method' => 'Mod',
        'decision_note' => 'Notă',
        'superseded' => 'Înlocuit',
        'emailed' => 'Ultimul e-mail',
        'print' => 'Tipărește',
        'back' => 'Înapoi la lucrare',
        'superseded_notice' => 'Acest deviz a fost înlocuit la :date. Este păstrat ca evidență.',
        'history' => 'Devizele acestei lucrări',
    ],

    'history_headers' => [
        'number' => 'Număr',
        'status' => 'Stare',
        'created' => 'Creat',
        'total' => 'Total',
    ],

    'identity' => [
        'heading' => 'Deviz :number',
        'created' => 'Data :date',
        'prepared_for' => 'Întocmit pentru',
    ],

    'decision' => [
        'title' => 'Înregistrați decizia clientului',
        'decision' => 'Decizie',
        'approve' => 'Aprobat',
        'decline' => 'Refuzat',
        'method' => 'Cum a răspuns clientul',
        'note' => 'Notă',
        'submit' => 'Înregistrează decizia',
        'submit_for' => 'Înregistrează decizia pentru devizul :number',
    ],

    'email' => [
        'action' => 'Trimite clientului pe e-mail',
        'action_for' => 'Trimite clientului pe e-mail: devizul :number',
    ],

    'create' => [
        'action' => 'Creează deviz',
        'action_for' => 'Creează deviz pentru :reference',
        'revise' => 'Revizuiește devizul',
        'revise_for' => 'Revizuiește devizul pentru :reference',
        'revise_confirm' => 'Se creează un deviz nou din liniile actuale. Devizul :number va fi marcat ca înlocuit.',
    ],

    'summary' => [
        'label' => 'Deviz',
        'none' => 'Fără deviz',
        'method' => '(:method)',
        'view' => 'Vezi',
        'view_for' => 'Vezi devizul :number',
    ],

    'warning' => [
        'over' => 'Totalul actual depășește cu :amount devizul aprobat :number.',
        'not_approved' => 'Devizul :number nu a fost aprobat.',
        'declined' => 'Devizul :number a fost refuzat.',
    ],

    'flash' => [
        'created' => 'Devizul :number a fost creat.',
        'approved' => 'Aprobarea a fost înregistrată pe devizul :number.',
        'declined' => 'Refuzul a fost înregistrat pe devizul :number.',
        'emailed' => 'Devizul :number se trimite la :email.',
        'no_email' => 'Clientul nu are o adresă de e-mail, deci devizul nu a fost trimis.',
        'not_creatable' => 'Un deviz necesită o lucrare în curs cu cel puțin o linie.',
        'not_decidable' => 'Pentru acest deviz decizia a fost deja înregistrată sau a fost înlocuit.',
        'not_emailable' => 'Se poate trimite pe e-mail doar un deviz în așteptare sau aprobat.',
    ],
];
