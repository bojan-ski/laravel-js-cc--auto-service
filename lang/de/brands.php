<?php

return [

    'title' => 'Marken',
    'new' => 'Neue Marke',
    'count' => '{0} Keine Marken|{1} 1 Marke|[2,*] :count Marken',
    'search_label' => 'Marken suchen',
    'search_placeholder' => 'Markenname suchen',

    'tiers' => [
        'aftermarket' => 'Nachbau',
        'original' => 'Original',
        'first_fit' => 'Erstausrüster',
        'genuine' => 'Originalteil',
    ],

    'headers' => [
        'name' => 'Name',
        'tier' => 'Qualität',
        'parts' => 'Teile',
        'actions' => 'Aktionen',
    ],

    'picker' => [
        'placeholder' => 'Keine Marke',
        'option' => ':name · :tier',
    ],

    'trashed' => [
        'badge' => 'Gelöscht',
    ],

    'create' => [
        'title' => 'Neue Marke',
        'submit' => 'Marke anlegen',
    ],

    'edit' => [
        'title' => ':name bearbeiten',
        'action' => ':name bearbeiten',
        'submit' => 'Änderungen speichern',
    ],

    'form' => [
        'name' => 'Name',
        'quality_tier' => 'Qualitätsstufe',
        'tier_note' => 'Die Stufe beschreibt, was die Marke liefert, nicht ein einzelnes Teil. Sie wird neben jedem Teil dieser Marke angezeigt.',
    ],

    'delete' => [
        'action' => ':name löschen',
        'confirm' => ':name löschen? Teile, die sie bereits tragen, behalten die Marke, und der Name bleibt reserviert, bis sie wiederhergestellt wird.',
    ],

    'restore' => [
        'action' => ':name wiederherstellen',
        'label' => 'Wiederherstellen',
        'confirm' => ':name wiederherstellen? Sie ist dann im Teileformular wieder auswählbar.',
    ],

    'empty' => [
        'none' => [
            'title' => 'Noch keine Marken',
            'body' => 'Legen Sie die erste Marke an, um festzuhalten, wer die Teile im Regal herstellt.',
        ],
        'no_matches' => [
            'title' => 'Keine Marke passt zu dieser Suche',
            'body' => 'Versuchen Sie einen Teil des Namens.',
        ],
    ],

    'parts_count' => '{0} Keine|{1} 1 Teil|[2,*] :count Teile',

    'validation' => [
        'name_deleted' => 'Dieser Name gehört zu einer gelöschten Marke. Stellen Sie sie wieder her, statt eine zweite anzulegen.',
    ],

    'flash' => [
        'created' => ':name wurde angelegt.',
        'updated' => ':name wurde aktualisiert.',
        'deleted' => ':name wurde gelöscht. Der Name bleibt reserviert.',
        'restored' => ':name wurde wiederhergestellt.',
    ],

];
