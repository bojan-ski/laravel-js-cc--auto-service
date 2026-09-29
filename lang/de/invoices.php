<?php

return [
    'statuses' => [
        'unpaid' => 'Offen',
        'paid' => 'Bezahlt',
        'void' => 'Storniert',
    ],

    'title' => 'Rechnungen',
    'count' => '{0}Keine Rechnungen|{1}Eine Rechnung|[2,*]:count Rechnungen',

    'tabs' => [
        'label' => 'Rechnungen nach Status filtern',
        'all' => 'Alle',
    ],

    'search_label' => 'Rechnungen suchen',
    'search_placeholder' => 'Rechnungsnummer oder Kundenname',

    'headers' => [
        'number' => 'Nummer',
        'customer' => 'Kunde',
        'issued' => 'Ausgestellt',
        'total' => 'Gesamt',
        'status' => 'Status',
    ],

    'empty' => [
        'none' => [
            'title' => 'Noch keine Rechnungen.',
            'body' => 'Rechnungen werden aus einem abgeschlossenen Auftrag ausgestellt, nicht hier angelegt.',
        ],

        'none_in_status' => [
            'title' => 'Keine Rechnung hat diesen Status.',
            'body' => 'Wählen Sie einen anderen Status oder zeigen Sie alle an.',
            'clear' => 'Alle Rechnungen anzeigen',
        ],

        'no_matches' => [
            'title' => 'Keine Rechnung passt zu dieser Suche.',
            'body' => 'Suchen Sie nach Rechnungsnummer oder Kundenname.',
        ],
    ],

    'issue' => [
        'title' => 'Rechnung ausstellen · :reference',
        'heading' => 'Rechnung ausstellen',
        'due_at' => 'Fälligkeitsdatum (optional)',
        'bill_to' => 'Rechnungsempfänger',
        'bill_to_hint' => 'Diese Angaben werden beim Ausstellen auf die Rechnung übernommen. Spätere Änderungen am Kunden ändern sie nicht, also zuerst den Kunden korrigieren, falls etwas nicht stimmt.',
        'warning_title' => 'Das Ausstellen sperrt diesen Auftrag.',
        'warning_body' => 'Die Positionen sind dann schreibgeschützt, und nur ein Administrator kann die Rechnung stornieren, um den Auftrag wieder zu öffnen.',
        'submit' => 'Rechnung ausstellen',
        'submit_for' => 'Rechnung für Auftrag :reference ausstellen',
        'cancel' => 'Abbrechen',
    ],

    'totals' => [
        'subtotal' => 'Zwischensumme',
        'vat' => 'MwSt. :rate %',
        'total' => 'Gesamt',
    ],

    'show' => [
        'title' => 'Rechnung :number',
        'details' => 'Rechnungsdetails',
        'job' => 'Auftrag',
        'customer' => 'Kunde',
        'vehicle' => 'Fahrzeug',
        'issued' => 'Ausgestellt',
        'issued_by' => 'Ausgestellt von',
        'due' => 'Fällig',
        'line_items' => 'Positionen',
        'actions' => 'Rechnungsaktionen',
        'print' => 'Drucken',
        'mark_paid' => 'Als bezahlt markieren',
        'mark_paid_for' => 'Rechnung :number als bezahlt markieren',
        'confirm_paid' => 'Rechnung :number als bezahlt markieren? Dies kann nicht rückgängig gemacht werden — eine irrtümlich erfasste Zahlung wird korrigiert, indem die Rechnung storniert und eine neue ausgestellt wird.',
    ],

    'items' => [
        'description' => 'Beschreibung',
        'quantity' => 'Menge',
        'price' => 'Einzelpreis',
        'line_total' => 'Betrag',
    ],

    'void' => [
        'title' => 'Diese Rechnung wurde storniert.',
        'body' => 'Der Auftrag wurde wieder geöffnet, und seine Positionen können sich seitdem geändert haben. Die folgenden Summen wurden in Rechnung gestellt.',
        'view_job' => 'Auftrag anzeigen',
        'view_job_for' => 'Auftrag :reference anzeigen',

        'action' => 'Rechnung stornieren',
        'action_for' => 'Rechnung :number stornieren',
        'page_title' => 'Rechnung stornieren · :number',
        'heading' => 'Rechnung stornieren',
        'warning_title' => 'Damit wird die Rechnung storniert und Auftrag :reference entsperrt.',
        'warning_body' => 'Der Auftrag bleibt abgeschlossen. Öffnen Sie ihn wieder, um seine Positionen zu bearbeiten, und schließen Sie ihn dann erneut ab, um eine neue Rechnung auszustellen.',
        'consequences' => [
            'kept' => 'Die Rechnung bleibt erhalten und sichtbar, als storniert gekennzeichnet.',
            'number' => 'Ihre Nummer :number wird nie wiederverwendet.',
            'stock' => 'Bereits verbaute Teile werden nicht in den Bestand zurückgebucht.',
            'reissue' => 'Danach kann eine neue Rechnung mit neuen Summen ausgestellt werden.',
        ],
        'paid_notice' => 'Diese Rechnung wurde am :date als bezahlt markiert. Die Stornierung hebt eine bereits beglichene Rechnung auf.',
        'cancel' => 'Abbrechen',
        'voided' => 'Storniert',
        'voided_by' => 'Storniert von',
        'print_marker' => 'STORNIERT',
    ],

    'identity' => [
        'heading' => 'Rechnung :number',
        'issued' => 'Ausgestellt am :date',
        'bill_to' => 'Rechnungsempfänger',
    ],

    'buyer' => [
        'tax_id' => 'Steuernr. (PIB)',
        'registration_number' => 'Registernr. (MB)',
    ],

    'flash' => [
        'issued' => 'Rechnung :number ausgestellt. Dieser Auftrag ist jetzt gesperrt.',
        'not_issuable' => 'Für diesen Auftrag konnte keine Rechnung ausgestellt werden. Möglicherweise hat jemand anderes bereits eine ausgestellt.',
        'paid' => 'Rechnung :number ist als bezahlt markiert.',
        'not_payable' => 'Diese Rechnung konnte nicht als bezahlt markiert werden. Ihr Status hat sich möglicherweise seit dem Laden der Seite geändert.',
        'voided' => 'Rechnung :number ist storniert. Auftrag :reference ist entsperrt.',
        'not_voidable' => 'Diese Rechnung konnte nicht storniert werden. Ihr Status hat sich möglicherweise seit dem Laden der Seite geändert.',
    ],
];
