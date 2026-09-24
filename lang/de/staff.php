<?php

return [
    'title' => 'Mitarbeiter',
    'new' => 'Neuer Mitarbeiter',
    'count' => '{0} Keine Mitarbeiter|{1} 1 Mitarbeiter|[2,*] :count Mitarbeiter',
    'search_label' => 'Mitarbeiter suchen',
    'search_placeholder' => 'Name oder E-Mail suchen',

    'tabs' => [
        'label' => 'Mitarbeiter nach Rolle filtern',
        'all' => 'Alle',
    ],

    'headers' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'role' => 'Rolle',
        'actions' => 'Aktionen',
    ],

    'offboarded' => [
        'on' => 'Ausgeschieden am :date',
    ],

    'you' => 'Sie',

    'actions' => [
        'edit' => ':name bearbeiten',
        'offboard' => 'Ausscheiden lassen',
        'offboard_named' => ':name ausscheiden lassen',
        'restore' => 'Wiederherstellen',
        'restore_named' => ':name wiederherstellen',
    ],

    'create' => [
        'title' => 'Neuer Mitarbeiter',
        'submit' => 'Konto anlegen',
    ],

    'edit' => [
        'title' => ':name bearbeiten',
        'submit' => 'Änderungen speichern',
    ],

    'form' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'role' => 'Rolle',
        'role_placeholder' => 'Rolle wählen',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwort bestätigen',
        'password_optional' => 'Neues Passwort',
        'password_note' => 'Leer lassen, um das aktuelle Passwort zu behalten.',
        'own_role_note' => 'Sie können Ihre eigene Rolle nicht ändern und sich nicht selbst ausscheiden lassen. Wenden Sie sich an einen anderen Administrator.',
    ],

    'offboard' => [
        'title' => ':name ausscheiden lassen?',
        'consequences' => [
            'Die Person kann sich nicht mehr anmelden und ihr Passwort nicht zurücksetzen',
            'Ihr Name bleibt auf jedem Auftrag, jedem Teil und jeder Rechnung erhalten, an denen sie gearbeitet hat',
            'Ihre E-Mail-Adresse bleibt reserviert',
            'Sie können das Konto später wiederherstellen',
        ],
        'submit' => ':name ausscheiden lassen',
    ],

    'restore' => [
        'confirm' => ':name wiederherstellen? Die Person kann sich dann wieder anmelden.',
    ],

    'empty' => [
        'title' => 'Keine Mitarbeiter passen zu diesen Filtern',
        'body' => 'Prüfen Sie die Schreibweise oder setzen Sie die Filter zurück, um alle zu sehen.',
        'clear' => 'Filter zurücksetzen',
    ],

    'validation' => [
        'email_offboarded' => 'Diese Adresse gehört zu :name (ausgeschieden). Stellen Sie das Konto wieder her, statt ein neues anzulegen.',
    ],

    'flash' => [
        'created' => ':name kann sich jetzt anmelden.',
        'updated' => ':name gespeichert.',
        'offboarded' => ':name ist ausgeschieden.',
        'restored' => ':name wurde wiederhergestellt und kann sich wieder anmelden.',
        'last_admin' => 'Dann hätte die Werkstatt keinen Administrator mehr. Vergeben Sie die Administratorrolle zuerst an jemand anderen.',
    ],
];
