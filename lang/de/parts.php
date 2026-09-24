<?php

return [
    'title' => 'Teile',
    'new' => 'Neues Teil',
    'count' => '{0} Keine Teile|{1} 1 Teil|[2,*] :count Teile',
    'search_label' => 'Teile suchen',
    'search_placeholder' => 'Artikelnummer, Name oder Teilenummer',
    'view_part' => ':sku anzeigen',

    'tabs' => [
        'label' => 'Katalogfilter',
        'all' => 'Alle',
        'low_stock' => 'Niedriger Bestand',
    ],

    'trashed' => [
        'show' => 'Gelöschte anzeigen',
        'hide' => 'Gelöschte ausblenden',
        'badge' => 'Gelöscht',
    ],

    'headers' => [
        'sku' => 'Art.-Nr.',
        'name' => 'Name',
        'brand' => 'Marke',
        'stock' => 'Bestand',
        'price' => 'Preis',
        'cost' => 'Einkauf',
        'shelf' => 'Regal',
    ],

    'stock' => [
        'low' => 'Niedrig',
        'negative' => 'Negativ',
    ],

    'picker' => [
        'placeholder' => 'Teil wählen',
        'option' => ':sku · :name · :stock auf Lager',
        'shelf' => ':label · Regal :location',
        'low_stock' => ':label · NIEDRIG',
        'out_of_stock' => ':label · NICHT VORRÄTIG',
    ],

    'reasons' => [
        'job_use' => 'Entnahme für Auftrag',
        'job_return' => 'Rückgabe aus Auftrag',
        'restock' => 'Wareneingang',
        'manual_adjustment' => 'Manuelle Korrektur',
    ],

    'numbers' => [
        'section' => 'Teilenummern',
        'empty' => 'Noch keine Teilenummern erfasst. Fügen Sie die OE-Nummer hinzu, damit dieses Teil darüber gefunden wird.',
        'add' => 'Teilenummer hinzufügen',
        'submit' => 'Nummer hinzufügen',
        'type_label' => 'Art',
        'number_label' => 'Nummer',
        'delete_action' => ':number löschen',
        'confirm_remove' => ':number von diesem Teil löschen? Suchen nach dieser Nummer finden das Teil dann nicht mehr.',
        'note' => 'Leerzeichen, Bindestriche sowie Groß- und Kleinschreibung werden bei der Suche ignoriert, daher finden 1K0 615 301 AA und 1k0-615-301-aa dasselbe Teil.',

        'types' => [
            'oe' => 'OE',
            'catalog' => 'Katalog',
            'ean' => 'Barcode',
            'supplier' => 'Lieferant',
        ],

        'validation' => [
            'not_a_number' => 'Das ist keine verwendbare Teilenummer — sie muss mindestens einen Buchstaben oder eine Ziffer enthalten.',
            'duplicate' => 'Dieses Teil hat diese Nummer bereits unter derselben Art.',
        ],

        'flash' => [
            'added' => ':number wurde hinzugefügt.',
            'removed' => ':number wurde entfernt.',
            'catalogue_conflict' => ':number ist auch eine Katalognummer von :sku (:name) derselben Marke. Beide wurden behalten — prüfen Sie, ob es sich um ein Duplikat handelt.',
        ],
    ],

    'import' => [
        'title' => 'Teile aus einer CSV-Datei importieren',
        'action' => 'CSV importieren',
        'intro' => 'Eine Zeile mit neuer Artikelnummer legt ein Teil an; eine Zeile mit bestehender Artikelnummer bucht ihre Menge in den Bestand ein. Ein Import ändert nie ein bestehendes Teil — Name, Preis, Marke, Kategorie und Regal bleiben genau so, wie sie sind.',
        'file_label' => 'CSV-Datei',
        'submit' => 'Importieren',
        'template' => 'Vorlage herunterladen',
        'template_note' => 'Die Vorlage enthält genau die Kopfzeile, die diese Seite erwartet.',
        'limits' => 'Bis zu :rows Zeilen pro Datei. Aus Excel als CSV speichern — Komma, Semikolon und Tabulator werden als Trennzeichen akzeptiert.',
        'movement_note' => 'CSV-Import',

        'columns_heading' => 'Spalten',
        'column_header' => 'Spalte',
        'column_required' => 'Pflicht',
        'column_meaning' => 'Bedeutung',
        'required_always' => 'Immer',
        'required_new' => 'Nur neue Teile',
        'required_never' => 'Optional',

        'columns' => [
            'sku' => 'Die werkstatteigene Artikelnummer. Entscheidet, ob die Zeile ein Teil anlegt oder einbucht.',
            'quantity' => 'Wie viele eingetroffen sind. Eine ganze Zahl, mindestens 1.',
            'name' => 'Wie das Teil heißt.',
            'unit_price' => 'Verkaufspreis.',
            'cost_price' => 'Was es gekostet hat. Nur für Administratoren — bei allen anderen wird die Spalte abgelehnt.',
            'brand' => 'Ein vorhandener Markenname, genau wie unter Marken geschrieben. Unbekannte Marken werden abgelehnt.',
            'category' => 'Eine freie Gruppierung, zum Beispiel Bremsen.',
            'location' => 'Regal oder Fach.',
            'reorder_level' => 'Die Schwelle für niedrigen Bestand.',
            'supplier' => 'Von wem diese Lieferung kam. Ein neuer Name wird für das nächste Mal gespeichert.',
            'oe_numbers' => 'OE-Nummern, getrennt durch einen senkrechten Strich: 1K0615301AA|8K0698451D',
        ],

        'errors' => [
            'heading' => 'Es wurde nichts importiert',
            'intro' => 'Die ganze Datei wird abgelehnt, wenn eine Zeile fehlerhaft ist, daher bleibt Ihr Katalog unverändert. Beheben Sie diese Fehler und laden Sie die Datei erneut hoch.',
            'no_rows' => 'Die Datei hat eine Kopfzeile, aber keine Datenzeilen.',
            'too_many_rows' => 'Die Datei hat :count Zeilen; das Limit liegt bei :max. Teilen Sie sie in kleinere Dateien auf.',
            'missing_column' => 'In der Kopfzeile fehlt die Pflichtspalte „:column“.',
            'unknown_column' => 'Die Kopfzeile enthält eine Spalte, die dieser Import nicht kennt: „:column“.',
            'duplicate_column' => 'Die Kopfzeile nennt dieselbe Spalte zweimal.',
            'cost_price_forbidden' => 'Nur ein Administrator darf eine Spalte cost_price importieren. Entfernen Sie sie und laden Sie die Datei erneut hoch.',
            'missing_sku' => 'Zeile :line hat keine Artikelnummer.',
            'duplicate_sku' => 'Artikelnummer :sku steht in Zeile :first und erneut in Zeile :line. Jedes Teil darf nur einmal vorkommen.',
            'deleted_part' => 'Zeile :line: :sku gehört zu einem gelöschten Teil. Stellen Sie es vor dem Import wieder her.',
            'bad_quantity' => 'Zeile :line (:sku): quantity muss eine ganze Zahl von mindestens 1 sein.',
            'missing_for_new_part' => 'Zeile :line (:sku) würde ein neues Teil anlegen, daher ist „:column“ erforderlich.',
            'bad_price' => 'Zeile :line (:sku): unit_price muss eine Zahl sein.',
            'bad_cost' => 'Zeile :line (:sku): cost_price muss eine Zahl sein.',
            'bad_reorder_level' => 'Zeile :line (:sku): reorder_level muss eine ganze Zahl sein.',
            'unknown_brand' => 'Zeile :line: Es gibt keine Marke namens „:brand“. Legen Sie sie zuerst unter Marken an.',
        ],

        'flash' => [
            'imported' => 'Import abgeschlossen: :created Teile angelegt, :restocked eingebucht.',
            'details_ignored' => ':count Zeilen enthielten Angaben zu bereits vorhandenen Teilen. Diese Teile wurden eingebucht und ansonsten nicht verändert.',
            'unreadable' => 'Diese Datei konnte nicht als CSV gelesen werden. Prüfen Sie, ob sie sich in einer Tabellenkalkulation öffnen lässt und eine Kopfzeile hat.',
        ],
    ],

    'show' => [
        'details' => 'Teiledetails',
        'brand' => 'Marke',
        'category' => 'Kategorie',
        'stock' => 'Bestand',
        'reorder_level' => 'Nachbestellen ab',
        'price' => 'Preis',
        'cost' => 'Einkaufspreis',
        'shelf' => 'Regal',
        'description' => 'Notizen',
        'movements' => 'Bestandsbewegungen',
        'movements_empty' => 'Noch keine Bewegungen erfasst.',
    ],

    'movements' => [
        'headers' => [
            'when' => 'Wann',
            'change' => 'Änderung',
            'reason' => 'Grund',
            'who' => 'Erfasst von',
        ],
        'showing' => ':shown von :total Bewegungen angezeigt',
        'from_supplier' => 'von :name',
    ],

    'adjust' => [
        'title' => 'Bestand anpassen · :sku',
        'action' => 'Anpassen',
        'action_for' => 'Bestand für :sku anpassen',
        'current' => 'Aktueller Bestand',
        'reason_label' => 'Um welche Art von Bewegung handelt es sich?',
        'quantity' => 'Menge',
        'note' => 'Notiz',
        'supplier' => 'Lieferant (optional)',
        'supplier_note' => 'Von wem diese Charge kam. Ein neuer Name wird für das nächste Mal gespeichert; Leerzeichen sowie Groß- und Kleinschreibung werden beim Abgleich ignoriert.',
        'submit' => 'Erfassen',
        'help' => [
            'restock' => 'Ware trifft ein. Die Menge muss positiv sein.',
            'manual_adjustment' => 'Eine Korrektur nach dem Zählen des Regals. Sie darf negativ sein, und die Notiz muss den Grund nennen.',
        ],
    ],

    'create' => [
        'title' => 'Neues Teil',
        'submit' => 'Teil anlegen',
    ],

    'edit' => [
        'title' => ':sku bearbeiten',
        'action' => ':sku bearbeiten',
        'submit' => 'Änderungen speichern',
    ],

    'form' => [
        'sku' => 'Artikelnummer',
        'name' => 'Name',
        'brand_id' => 'Marke (optional)',
        'category' => 'Kategorie (optional)',
        'description' => 'Notizen (optional)',
        'unit_price' => 'Verkaufspreis',
        'cost_price' => 'Einkaufspreis (optional)',
        'reorder_level' => 'Meldebestand',
        'location' => 'Regalplatz (optional)',
        'stock_title' => 'Der Bestand wird nicht eingetippt',
        'stock_note' => 'Ein neues Teil beginnt bei null. Der Bestand ändert sich nur durch Wareneingang oder durch Verbau in einem Auftrag, sodass jede Änderung nachvollziehbar bleibt.',
    ],

    'delete' => [
        'action' => ':sku löschen',
        'confirm' => ':sku löschen? Die Artikelnummer bleibt reserviert und kann erst wieder für ein anderes Teil verwendet werden, wenn dieses wiederhergestellt ist.',
    ],

    'restore' => [
        'action' => ':sku wiederherstellen',
        'label' => 'Wiederherstellen',
        'confirm' => ':sku wiederherstellen? Das Teil kehrt mit unverändertem Bestand in den Katalog zurück.',
    ],

    'empty' => [
        'none' => [
            'title' => 'Noch keine Teile',
            'body' => 'Der Katalog ist leer. Legen Sie das erste Teil an, um den Bestand zu verfolgen.',
        ],
        'no_matches' => [
            'title' => 'Keine Teile passen zu dieser Suche',
            'body' => 'Prüfen Sie die Artikelnummer oder versuchen Sie einen Teil des Namens oder eine OE-Nummer.',
        ],
        'none_low' => [
            'title' => 'Kein Teil hat niedrigen Bestand',
            'body' => 'Jedes Teil liegt über seinem Meldebestand.',
            'clear' => 'Alle Teile anzeigen',
        ],
    ],

    'validation' => [
        'sku_deleted' => 'Diese Artikelnummer gehört zu einem gelöschten Teil, :name. Ein Administrator kann es im Katalog wiederherstellen.',
    ],

    'flash' => [
        'created' => ':sku wurde angelegt.',
        'updated' => ':sku wurde aktualisiert.',
        'deleted' => ':sku wurde gelöscht. Die Artikelnummer bleibt reserviert.',
        'restored' => ':sku wurde wiederhergestellt.',
        'stock_recorded' => ':sku: :change erfasst. Der Bestand beträgt jetzt :balance.',
        'stock_negative' => 'Der Bestand von :sku beträgt jetzt :balance, unter null. Die Bewegung wurde trotzdem gespeichert.',
    ],
];
