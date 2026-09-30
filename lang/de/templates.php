<?php

return [
    'index' => [
        'title' => 'Auftragsvorlagen',
        'new' => 'Neue Vorlage',
        'headers' => [
            'actions' => 'Aktionen',
        ],
    ],

    'headers' => [
        'name' => 'Name',
        'description' => 'Beschreibung',
        'lines' => 'Positionen',
    ],

    'lines_count' => ':count Position|:count Positionen',
    'no_lines' => 'Noch keine Positionen',

    'empty' => [
        'none' => [
            'title' => 'Noch keine Auftragsvorlagen',
            'body' => 'Eine Vorlage fügt einem Auftrag in einem Schritt eine Reihe von Arbeits- und Teilepositionen hinzu.',
        ],
    ],

    'trashed' => [
        'badge' => 'Gelöscht',
    ],

    'restore' => [
        'label' => 'Wiederherstellen',
        'action' => ':name wiederherstellen',
        'confirm' => 'Die Vorlage :name wiederherstellen?',
    ],

    'create' => [
        'title' => 'Neue Auftragsvorlage',
        'submit' => 'Vorlage anlegen',
    ],

    'edit' => [
        'title' => ':name bearbeiten',
        'submit' => 'Vorlage speichern',
    ],

    'fields' => [
        'name' => 'Name',
        'description' => 'Beschreibung',
    ],

    'errors' => [
        'name_trashed' => 'Eine gelöschte Vorlage trägt bereits den Namen :name. Ein Administrator kann sie wiederherstellen.',
    ],

    'delete' => [
        'label' => 'Vorlage löschen',
        'action' => 'Vorlage :name löschen',
        'confirm' => 'Die Vorlage :name löschen? Ein Administrator kann sie wiederherstellen.',
    ],

    'lines' => [
        'heading' => 'Positionen',
        'empty' => 'Diese Vorlage hat noch keine Positionen. Fügen Sie unten eine hinzu.',
        'headers' => [
            'type' => 'Art',
            'description' => 'Beschreibung',
            'quantity' => 'Menge',
            'actions' => 'Aktionen',
        ],
        'part_label' => ':name · :sku',
        'part_deleted' => '(gelöscht)',
        'deleted_part_notice' => 'Ein Teil dieser Vorlage wurde gelöscht. Ersetzen oder entfernen Sie diese Position, bevor Sie die Vorlage anwenden.',
        'add' => [
            'labor_title' => 'Arbeitsposition hinzufügen',
            'part_title' => 'Teileposition hinzufügen',
            'group_label' => 'Positionsart',
            'labor_link' => '+ Arbeit',
            'part_link' => '+ Teil',
            'description' => 'Beschreibung',
            'part' => 'Teil',
            'hours' => 'Stunden',
            'units' => 'Menge',
            'no_parts' => 'Im Katalog sind noch keine Teile.',
            'submit' => 'Position hinzufügen',
        ],
        'edit' => [
            'open' => 'Bearbeiten',
            'open_line' => ':description bearbeiten',
            'title_for' => 'Bearbeiten: :description',
            'save' => 'Position speichern',
            'cancel' => 'Abbrechen',
            'row_marker' => 'Diese Position wird unten bearbeitet.',
        ],
        'remove' => [
            'label' => 'Entfernen',
            'action' => ':description entfernen',
            'confirm' => ':description aus dieser Vorlage entfernen?',
        ],
    ],

    'flash' => [
        'created' => 'Vorlage :name angelegt. Fügen Sie unten ihre Positionen hinzu.',
        'updated' => 'Vorlage :name gespeichert.',
        'deleted' => 'Vorlage :name gelöscht.',
        'restored' => 'Vorlage :name wiederhergestellt.',
        'line_added' => 'Position hinzugefügt.',
        'line_updated' => 'Position gespeichert.',
        'line_removed' => 'Position entfernt.',
    ],

    'apply' => [
        'title' => 'Vorlage anwenden',
        'link' => '+ Vorlage',
        'link_label' => 'Vorlage anwenden',
        'template' => 'Vorlage',
        'template_placeholder' => 'Vorlage wählen',
        'option' => ':name — :lines',
        'mechanic' => 'Mechaniker',
        'mechanic_placeholder' => 'Mechaniker wählen',
        'mechanic_hint' => 'Pflicht, wenn die Vorlage Arbeitspositionen enthält. Alle Positionen werden diesem Mechaniker zugewiesen.',
        'self_hint' => 'Alle Positionen werden Ihnen zugewiesen.',
        'submit' => 'Vorlage anwenden',
        'none' => 'Es gibt noch keine Auftragsvorlagen.',
        'manage' => 'Vorlagen verwalten',
        'flash' => [
            'applied' => 'Vorlage :name angewendet: :count Position hinzugefügt.|Vorlage :name angewendet: :count Positionen hinzugefügt.',
            'stock_negative' => 'Der Bestand ist jetzt negativ für: :parts.',
            'stock_negative_part' => ':name (:balance)',
        ],
        'errors' => [
            'deleted_part' => 'Diese Vorlage verwendet :part, das gelöscht wurde. Bearbeiten Sie die Vorlage, bevor Sie sie anwenden.',
            'mechanic_required' => 'Wählen Sie den Mechaniker, der die Arbeit dieser Vorlage ausführt.',
            'no_lines' => 'Diese Vorlage hat keine Positionen.',
        ],
    ],
];
