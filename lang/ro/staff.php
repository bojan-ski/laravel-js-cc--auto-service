<?php

return [
    'title' => 'Personal',
    'new' => 'Angajat nou',
    'count' => ':count angajat|:count angajați|:count de angajați|{0}Niciun angajat',
    'search_label' => 'Caută în personal',
    'search_placeholder' => 'Caută după nume sau e-mail',

    'tabs' => [
        'label' => 'Filtrează personalul după rol',
        'all' => 'Toți',
    ],

    'headers' => [
        'name' => 'Nume',
        'email' => 'E-mail',
        'role' => 'Rol',
        'actions' => 'Acțiuni',
    ],

    'offboarded' => [
        'on' => 'Cont dezactivat la :date',
    ],

    'you' => 'Dvs.',

    'actions' => [
        'edit' => 'Editează :name',
        'offboard' => 'Dezactivează',
        'offboard_named' => 'Dezactivează :name',
        'restore' => 'Restaurează',
        'restore_named' => 'Restaurează :name',
    ],

    'create' => [
        'title' => 'Angajat nou',
        'submit' => 'Creează contul',
    ],

    'edit' => [
        'title' => 'Editează :name',
        'submit' => 'Salvează modificările',
    ],

    'form' => [
        'name' => 'Nume',
        'email' => 'E-mail',
        'role' => 'Rol',
        'role_placeholder' => 'Alegeți un rol',
        'password' => 'Parolă',
        'password_confirmation' => 'Confirmați parola',
        'password_optional' => 'Parolă nouă',
        'password_note' => 'Lăsați gol pentru a păstra parola actuală.',
        'own_role_note' => 'Nu vă puteți schimba propriul rol și nici nu vă puteți dezactiva propriul cont. Cereți altui administrator.',
    ],

    'offboard' => [
        'title' => 'Dezactivați contul pentru :name?',
        'consequences' => [
            'Nu se mai poate autentifica și nu își mai poate reseta parola',
            'Numele rămâne pe fiecare lucrare, piesă și factură la care a lucrat',
            'Adresa de e-mail rămâne rezervată',
            'Puteți restaura contul mai târziu',
        ],
        'submit' => 'Dezactivează :name',
    ],

    'restore' => [
        'confirm' => 'Restaurați contul pentru :name? Se va putea autentifica din nou.',
    ],

    'empty' => [
        'title' => 'Niciun angajat nu corespunde filtrelor',
        'body' => 'Verificați ortografia sau ștergeți filtrele pentru a vedea pe toată lumea.',
        'clear' => 'Șterge filtrele',
    ],

    'validation' => [
        'email_offboarded' => 'Această adresă aparține unui cont dezactivat (:name). Restaurați contul în loc să creați unul nou.',
    ],

    'flash' => [
        'created' => ':name se poate autentifica acum.',
        'updated' => 'Modificările pentru :name au fost salvate.',
        'offboarded' => 'Contul pentru :name a fost dezactivat.',
        'restored' => 'Contul pentru :name a fost restaurat și se poate folosi din nou pentru autentificare.',
        'last_admin' => 'Atelierul ar rămâne fără administrator. Acordați mai întâi rolul de administrator altcuiva.',
    ],
];
