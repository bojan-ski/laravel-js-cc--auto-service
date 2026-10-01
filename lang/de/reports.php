<?php

return [
    'title' => 'Berichte',

    'period' => [
        'legend' => 'Zeitraum',
        'from' => 'Von',
        'to' => 'Bis',
        'apply' => 'Anwenden',
        'summary' => 'Rechnungen ausgestellt :from – :to',
    ],

    'export' => [
        'label' => 'CSV exportieren',
        'aria' => 'CSV exportieren: Rechnungen ausgestellt :from – :to',
    ],

    'empty' => [
        'title' => 'Für diesen Zeitraum gibt es nichts zu berichten',
        'body' => 'Keine in diesem Zeitraum ausgestellte Rechnung zählt für den Bericht. Wählen Sie einen anderen Zeitraum.',
    ],

    'voided' => ':count stornierte Rechnung wird nicht gezählt.|:count stornierte Rechnungen werden nicht gezählt.',

    'revenue' => [
        'title' => 'Umsatz',
        'invoices' => 'Rechnungen',
        'before_discount' => 'Vor Rabatt',
        'discount' => 'Rabatte',
        'subtotal' => 'Zwischensumme',
        'vat' => 'MwSt.',
        'total' => 'Gesamt',
    ],

    'vat' => [
        'title' => 'MwSt. nach Satz',
        'rate' => 'Satz',
        'rate_value' => ':rate %',
        'base' => 'Nettobetrag',
        'vat' => 'MwSt.',
    ],

    'payments' => [
        'title' => 'Zahlungen',
        'method' => 'Zahlungsart',
        'invoices' => 'Rechnungen',
        'amount' => 'Betrag',
        'unrecorded' => 'Nicht erfasst',
        'unpaid' => 'Unbezahlt',
    ],

    'labor' => [
        'title' => 'Arbeit nach Mechaniker',
        'mechanic' => 'Mechaniker',
        'hours' => 'Stunden',
        'amount' => 'Betrag',
        'empty' => 'In diesem Zeitraum wurde keine Arbeit abgerechnet.',
    ],

    'parts' => [
        'title' => 'Verkaufte Teile',
        'sku' => 'Art.-Nr.',
        'name' => 'Teil',
        'quantity' => 'Menge',
        'revenue' => 'Umsatz',
        'cost' => 'Kosten',
        'margin' => 'Marge',
        'total' => 'Gesamt',
        'empty' => 'In diesem Zeitraum wurden keine Teile abgerechnet.',
        'without_cost' => ':count Teil ohne Einkaufspreis wird bei der Marge nicht berücksichtigt.|:count Teile ohne Einkaufspreis werden bei der Marge nicht berücksichtigt.',
    ],

    'csv' => [
        'number' => 'Nummer',
        'issued_on' => 'Ausgestellt am',
        'status' => 'Status',
        'job' => 'Auftrag',
        'buyer_name' => 'Käufer',
        'buyer_address' => 'Käuferadresse',
        'buyer_tax_id' => 'Käufer-PIB',
        'buyer_registration_number' => 'Käufer-MB',
        'before_discount' => 'Vor Rabatt',
        'discount' => 'Rabatt',
        'subtotal' => 'Zwischensumme',
        'vat_rate' => 'MwSt.-Satz',
        'vat' => 'MwSt.',
        'total' => 'Gesamt',
        'payment_method' => 'Zahlungsart',
        'paid_on' => 'Bezahlt am',
        'voided_on' => 'Storniert am',
    ],
];
