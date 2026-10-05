<?php

return [
    'title' => 'Delovi',
    'new' => 'Novi deo',
    'count' => ':count deo|:count dela|:count delova|{0}Nema delova',
    'search_label' => 'Pretraga delova',
    'search_placeholder' => 'Pretraži po šifri, nazivu ili broju dela',
    'view_part' => 'Prikaži :sku',

    'tabs' => [
        'label' => 'Filteri kataloga',
        'all' => 'Svi',
        'low_stock' => 'Niska zaliha',
    ],

    'trashed' => [
        'show' => 'Prikaži obrisane',
        'hide' => 'Sakrij obrisane',
        'badge' => 'Obrisan',
    ],

    'headers' => [
        'sku' => 'Šifra',
        'name' => 'Naziv',
        'brand' => 'Brend',
        'stock' => 'Stanje',
        'price' => 'Cena',
        'cost' => 'Nabavna cena',
        'shelf' => 'Polica',
    ],

    'stock' => [
        'low' => 'Nisko',
        'negative' => 'Negativno',
    ],

    'picker' => [
        'placeholder' => 'Izaberite deo',
        'option' => ':sku · :name · :stock na stanju',
        'shelf' => ':label · polica :location',
        'low_stock' => ':label · NISKO',
        'out_of_stock' => ':label · NEMA NA STANJU',
    ],

    'reasons' => [
        'job_use' => 'Utrošak na nalogu',
        'job_return' => 'Povraćaj sa naloga',
        'restock' => 'Prijem robe',
        'manual_adjustment' => 'Ručna korekcija',
    ],

    'numbers' => [
        'section' => 'Brojevi dela',
        'empty' => 'Još nema evidentiranih brojeva dela. Dodajte OE broj da bi se ovaj deo mogao pronaći po njemu.',
        'add' => 'Dodaj broj dela',
        'submit' => 'Dodaj broj',
        'type_label' => 'Tip',
        'number_label' => 'Broj',
        'delete_action' => 'Obriši :number',
        'confirm_remove' => 'Obrisati :number sa ovog dela? Pretraga po tom broju ga više neće pronalaziti.',
        'note' => 'Razmaci, crtice i velika ili mala slova se zanemaruju pri pretrazi, pa 1K0 615 301 AA i 1k0-615-301-aa pronalaze isti deo.',

        'types' => [
            'oe' => 'OE',
            'catalog' => 'Kataloški',
            'ean' => 'Bar-kod',
            'supplier' => 'Dobavljač',
        ],

        'validation' => [
            'not_a_number' => 'To nije upotrebljiv broj dela — mora sadržati bar jedno slovo ili cifru.',
            'duplicate' => 'Ovaj deo već ima taj broj pod istim tipom.',
        ],

        'flash' => [
            'added' => 'Broj :number je dodat.',
            'removed' => 'Broj :number je uklonjen.',
            'catalogue_conflict' => ':number je takođe kataloški broj dela :sku (:name) istog brenda. Oba su zadržana — proverite da li je jedan od njih duplikat.',
        ],
    ],

    'import' => [
        'title' => 'Uvoz delova iz CSV datoteke',
        'action' => 'Uvezi CSV',
        'intro' => 'Red sa novom šifrom kreira deo; red sa šifrom koja već postoji dodaje svoju količinu na stanje. Uvoz nikada ne menja postojeći deo — njegov naziv, cena, brend, kategorija i polica ostaju tačno kakvi jesu.',
        'file_label' => 'CSV datoteka',
        'submit' => 'Uvezi',
        'template' => 'Preuzmi šablon',
        'template_note' => 'Šablon sadrži tačan red zaglavlja koji ova stranica očekuje.',
        'limits' => 'Najviše :rows redova po datoteci. Iz Excela sačuvajte kao CSV — kao razdvajači se prihvataju zarez, tačka-zarez i tabulator.',
        'movement_note' => 'Uvoz iz CSV-a',

        'columns_heading' => 'Kolone',
        'column_header' => 'Kolona',
        'column_required' => 'Obavezna',
        'column_meaning' => 'Značenje',
        'required_always' => 'Uvek',
        'required_new' => 'Samo za nove delove',
        'required_never' => 'Opciona',

        'columns' => [
            'sku' => 'Interna šifra dela u radionici. Određuje da li red kreira deo ili dopunjuje stanje.',
            'quantity' => 'Koliko je stiglo. Ceo broj, najmanje 1.',
            'name' => 'Naziv dela.',
            'unit_price' => 'Prodajna cena.',
            'cost_price' => 'Nabavna cena. Samo za administratore — za sve ostale kolona se odbija.',
            'brand' => 'Naziv postojećeg brenda, napisan tačno kao u Brendovima. Nepoznati brendovi se odbijaju.',
            'category' => 'Slobodna grupacija, na primer Kočnice.',
            'location' => 'Polica ili pregrada.',
            'reorder_level' => 'Prag niske zalihe.',
            'supplier' => 'Od koga je stigla ova isporuka. Novi naziv se pamti za sledeći put.',
            'oe_numbers' => 'OE brojevi, razdvojeni uspravnom crtom: 1K0615301AA|8K0698451D',
        ],

        'errors' => [
            'heading' => 'Ništa nije uvezeno',
            'intro' => 'Cela datoteka se odbija ako je bilo koji red neispravan, tako da katalog ostaje netaknut. Ispravite ove greške i ponovo otpremite datoteku.',
            'no_rows' => 'Datoteka ima red zaglavlja, ali nema redova sa podacima.',
            'too_many_rows' => 'Broj redova u datoteci: :count; ograničenje je :max. Podelite je na manje datoteke.',
            'missing_column' => 'U redu zaglavlja nedostaje obavezna kolona „:column“.',
            'unknown_column' => 'Red zaglavlja sadrži kolonu koju ovaj uvoz ne prepoznaje: „:column“.',
            'duplicate_column' => 'Red zaglavlja dvaput navodi istu kolonu.',
            'cost_price_forbidden' => 'Samo administrator može da uveze kolonu cost_price. Uklonite je i ponovo otpremite datoteku.',
            'missing_sku' => 'Red :line nema šifru.',
            'duplicate_sku' => 'Šifra :sku se pojavljuje u redu :first i ponovo u redu :line. Svaki deo sme da se pojavi samo jednom.',
            'deleted_part' => 'Red :line: :sku pripada obrisanom delu. Vratite ga pre uvoza.',
            'bad_quantity' => 'Red :line (:sku): quantity mora biti ceo broj, najmanje 1.',
            'missing_for_new_part' => 'Red :line (:sku) bi kreirao novi deo, pa je kolona „:column“ obavezna.',
            'bad_price' => 'Red :line (:sku): unit_price mora biti broj.',
            'bad_cost' => 'Red :line (:sku): cost_price mora biti broj.',
            'bad_reorder_level' => 'Red :line (:sku): reorder_level mora biti ceo broj.',
            'unknown_brand' => 'Red :line: ne postoji brend „:brand“. Prvo ga dodajte u Brendovima.',
        ],

        'flash' => [
            'imported' => 'Uvoz je završen: kreirano delova: :created, dopunjeno: :restocked.',
            'details_ignored' => 'Redova sa podacima za delove koji već postoje: :count. Ti delovi su dopunjeni, a inače su ostali nepromenjeni.',
            'unreadable' => 'Ta datoteka nije mogla da se pročita kao CSV. Proverite da li se otvara u programu za tabele i da li ima red zaglavlja.',
        ],
    ],

    'show' => [
        'details' => 'Detalji dela',
        'brand' => 'Brend',
        'category' => 'Kategorija',
        'stock' => 'Stanje',
        'reorder_level' => 'Naručiti pri',
        'price' => 'Cena',
        'cost' => 'Nabavna cena',
        'shelf' => 'Polica',
        'description' => 'Napomene',
        'movements' => 'Promene stanja',
        'movements_empty' => 'Još nema evidentiranih promena.',
    ],

    'movements' => [
        'headers' => [
            'when' => 'Kada',
            'change' => 'Promena',
            'reason' => 'Razlog',
            'who' => 'Evidentirao/la',
        ],
        'showing' => 'Prikazano :shown od :total promena',
        'from_supplier' => 'od dobavljača :name',
    ],

    'adjust' => [
        'title' => 'Korekcija stanja · :sku',
        'action' => 'Koriguj',
        'action_for' => 'Koriguj stanje za :sku',
        'current' => 'Trenutno stanje',
        'reason_label' => 'Koja je ovo vrsta promene?',
        'quantity' => 'Količina',
        'note' => 'Napomena',
        'supplier' => 'Dobavljač (opciono)',
        'supplier_note' => 'Od koga je stigla ova isporuka. Novi naziv se pamti za sledeći put; razmaci i velika ili mala slova se zanemaruju pri poređenju.',
        'submit' => 'Evidentiraj',
        'help' => [
            'restock' => 'Roba stiže. Količina mora biti pozitivna.',
            'manual_adjustment' => 'Korekcija nakon brojanja police. Može biti negativna, a napomena je obavezna da objasni razlog.',
        ],
    ],

    'create' => [
        'title' => 'Novi deo',
        'submit' => 'Dodaj deo',
    ],

    'edit' => [
        'title' => 'Izmeni :sku',
        'action' => 'Izmeni :sku',
        'submit' => 'Sačuvaj izmene',
    ],

    'form' => [
        'sku' => 'Šifra',
        'name' => 'Naziv',
        'brand_id' => 'Brend (opciono)',
        'category' => 'Kategorija (opciono)',
        'description' => 'Napomene (opciono)',
        'unit_price' => 'Prodajna cena',
        'cost_price' => 'Nabavna cena (opciono)',
        'reorder_level' => 'Nivo za ponovno naručivanje',
        'location' => 'Lokacija na polici (opciono)',
        'stock_title' => 'Stanje se ne unosi ručno',
        'stock_note' => 'Novi deo počinje od nule. Stanje se menja samo prijemom robe ili ugradnjom na radnom nalogu, tako da svaka promena ostavlja trag.',
    ],

    'delete' => [
        'action' => 'Obriši :sku',
        'confirm' => 'Obrisati :sku? Šifra ostaje rezervisana i ne može se koristiti za drugi deo dok se ovaj ne vrati.',
    ],

    'restore' => [
        'action' => 'Vrati :sku',
        'label' => 'Vrati',
        'confirm' => 'Vratiti :sku? Deo se vraća u katalog sa nepromenjenim stanjem.',
    ],

    'empty' => [
        'none' => [
            'title' => 'Još nema delova',
            'body' => 'Katalog je prazan. Dodajte prvi deo da biste počeli da pratite stanje.',
        ],
        'no_matches' => [
            'title' => 'Nijedan deo ne odgovara pretrazi',
            'body' => 'Proverite šifru ili pokušajte sa delom naziva ili OE brojem.',
        ],
        'none_low' => [
            'title' => 'Nijedan deo nema nisku zalihu',
            'body' => 'Svaki deo je iznad svog nivoa za ponovno naručivanje.',
            'clear' => 'Prikaži sve delove',
        ],
    ],

    'validation' => [
        'sku_deleted' => 'Ta šifra pripada obrisanom delu, :name. Administrator ga može vratiti iz kataloga.',
        'adjustment_note_required' => 'Ručna korekcija mora imati napomenu sa razlogom.',
    ],

    'flash' => [
        'created' => 'Deo :sku je dodat.',
        'updated' => 'Deo :sku je izmenjen.',
        'deleted' => 'Deo :sku je obrisan. Njegova šifra ostaje rezervisana.',
        'restored' => 'Deo :sku je vraćen.',
        'stock_recorded' => ':sku: evidentirano :change. Stanje je sada :balance.',
        'stock_negative' => 'Stanje za :sku je sada :balance, ispod nule. Promena je ipak sačuvana.',
    ],
];
