<?php

return [
    'title' => 'Piese',
    'new' => 'Piesă nouă',
    'count' => ':count piesă|:count piese|:count de piese|{0}Nicio piesă',
    'search_label' => 'Caută piese',
    'search_placeholder' => 'Caută după cod intern, nume sau cod piesă',
    'view_part' => 'Vezi :sku',

    'tabs' => [
        'label' => 'Filtre catalog',
        'all' => 'Toate',
        'low_stock' => 'Stoc redus',
    ],

    'trashed' => [
        'show' => 'Afișează șterse',
        'hide' => 'Ascunde șterse',
        'badge' => 'Ștearsă',
    ],

    'headers' => [
        'sku' => 'Cod intern',
        'name' => 'Nume',
        'brand' => 'Marcă',
        'stock' => 'Stoc',
        'price' => 'Preț',
        'cost' => 'Cost',
        'shelf' => 'Raft',
    ],

    'stock' => [
        'low' => 'Redus',
        'negative' => 'Negativ',
    ],

    'picker' => [
        'placeholder' => 'Selectați o piesă',
        'option' => ':sku · :name · :stock în stoc',
        'shelf' => ':label · raft :location',
        'low_stock' => ':label · STOC REDUS',
        'out_of_stock' => ':label · STOC EPUIZAT',
    ],

    'reasons' => [
        'job_use' => 'Consum la lucrare',
        'job_return' => 'Retur de la lucrare',
        'restock' => 'Recepție marfă',
        'manual_adjustment' => 'Ajustare manuală',
    ],

    'numbers' => [
        'section' => 'Coduri piesă',
        'empty' => 'Niciun cod înregistrat încă. Adăugați codul OE pentru ca piesa să poată fi găsită după el.',
        'add' => 'Adaugă cod piesă',
        'submit' => 'Adaugă codul',
        'type_label' => 'Tip',
        'number_label' => 'Cod',
        'delete_action' => 'Șterge :number',
        'confirm_remove' => 'Ștergeți codul :number de la această piesă? Piesa nu va mai fi găsită la căutarea după acest cod.',
        'note' => 'Spațiile, cratimele și majusculele sunt ignorate la căutare, deci 1K0 615 301 AA și 1k0-615-301-aa găsesc aceeași piesă.',

        'types' => [
            'oe' => 'OE',
            'catalog' => 'Catalog',
            'ean' => 'Cod de bare',
            'supplier' => 'Furnizor',
        ],

        'validation' => [
            'not_a_number' => 'Acesta nu este un cod de piesă utilizabil — trebuie să conțină cel puțin o literă sau o cifră.',
            'duplicate' => 'Această piesă are deja acest cod, la același tip.',
        ],

        'flash' => [
            'added' => 'Codul :number a fost adăugat.',
            'removed' => 'Codul :number a fost eliminat.',
            'catalogue_conflict' => ':number este și cod de catalog la :sku (:name), de la aceeași marcă. Ambele au fost păstrate — verificați dacă unul este duplicat.',
        ],
    ],

    'import' => [
        'title' => 'Import piese din CSV',
        'action' => 'Import CSV',
        'intro' => 'Un rând cu un cod intern nou creează o piesă; un rând cu un cod intern existent adaugă cantitatea în stoc. Un import nu modifică niciodată o piesă existentă — numele, prețul, marca, categoria și raftul rămân exact cum sunt.',
        'file_label' => 'Fișier CSV',
        'submit' => 'Importă',
        'template' => 'Descarcă șablonul',
        'template_note' => 'Șablonul conține exact rândul de antet pe care îl așteaptă această pagină.',
        'limits' => 'Până la :rows rânduri pe fișier. Salvați din Excel ca CSV — sunt acceptate ca separatoare virgula, punctul și virgula și tabul.',
        'movement_note' => 'Import CSV',

        'columns_heading' => 'Coloane',
        'column_header' => 'Coloană',
        'column_required' => 'Obligatorie',
        'column_meaning' => 'Semnificație',
        'required_always' => 'Întotdeauna',
        'required_new' => 'Doar pentru piese noi',
        'required_never' => 'Opțională',

        'columns' => [
            'sku' => 'Codul intern al piesei. Decide dacă rândul creează o piesă sau adaugă în stoc.',
            'quantity' => 'Câte bucăți au sosit. Număr întreg, cel puțin 1.',
            'name' => 'Denumirea piesei.',
            'unit_price' => 'Prețul de vânzare.',
            'cost_price' => 'Prețul de achiziție. Doar pentru administratori — coloana este refuzată pentru oricine altcineva.',
            'brand' => 'Numele unei mărci existente, scris exact ca în Mărci. Mărcile necunoscute sunt refuzate.',
            'category' => 'O grupare liberă, de exemplu Frânare.',
            'location' => 'Raft sau cutie.',
            'reorder_level' => 'Pragul de stoc redus.',
            'supplier' => 'De la cine a venit această livrare. Un nume nou este reținut pentru data viitoare.',
            'oe_numbers' => 'Coduri OE, separate prin bară verticală: 1K0615301AA|8K0698451D',
        ],

        'errors' => [
            'heading' => 'Nu s-a importat nimic',
            'intro' => 'Întregul fișier este respins dacă oricare rând este greșit, deci catalogul rămâne neatins. Corectați aceste erori și încărcați din nou.',
            'no_rows' => 'Fișierul are un rând de antet, dar niciun rând de date.',
            'too_many_rows' => 'Fișierul are :count rânduri; limita este :max. Împărțiți-l în fișiere mai mici.',
            'missing_column' => 'Rândului de antet îi lipsește coloana obligatorie „:column”.',
            'unknown_column' => 'Rândul de antet conține o coloană pe care acest import nu o recunoaște: „:column”.',
            'duplicate_column' => 'Rândul de antet conține aceeași coloană de două ori.',
            'cost_price_forbidden' => 'Doar un administrator poate importa o coloană cost_price. Eliminați-o și încărcați din nou.',
            'missing_sku' => 'Linia :line nu are cod intern.',
            'duplicate_sku' => 'Codul :sku apare pe linia :first și din nou pe linia :line. Fiecare piesă poate apărea o singură dată.',
            'deleted_part' => 'Linia :line: :sku aparține unei piese șterse. Restaurați-o înainte de import.',
            'bad_quantity' => 'Linia :line (:sku): cantitatea trebuie să fie un număr întreg de cel puțin 1.',
            'missing_for_new_part' => 'Linia :line (:sku) ar crea o piesă nouă, deci „:column” este obligatorie.',
            'bad_price' => 'Linia :line (:sku): unit_price trebuie să fie un număr.',
            'bad_cost' => 'Linia :line (:sku): cost_price trebuie să fie un număr.',
            'bad_reorder_level' => 'Linia :line (:sku): reorder_level trebuie să fie un număr întreg.',
            'unknown_brand' => 'Linia :line: nu există nicio marcă numită „:brand”. Adăugați-o mai întâi în Mărci.',
        ],

        'flash' => [
            'imported' => 'Import finalizat: piese create: :created; piese cu stoc completat: :restocked.',
            'details_ignored' => 'Rânduri cu detalii pentru piese deja existente: :count. Acelor piese li s-a completat doar stocul; în rest au rămas neschimbate.',
            'unreadable' => 'Fișierul nu a putut fi citit ca CSV. Verificați că se deschide într-un program de calcul tabelar și că are un rând de antet.',
        ],
    ],

    'show' => [
        'details' => 'Detalii piesă',
        'brand' => 'Marcă',
        'category' => 'Categorie',
        'stock' => 'Stoc',
        'reorder_level' => 'Prag de reaprovizionare',
        'price' => 'Preț',
        'cost' => 'Cost',
        'shelf' => 'Raft',
        'description' => 'Observații',
        'movements' => 'Mișcări de stoc',
        'movements_empty' => 'Nicio mișcare înregistrată încă.',
    ],

    'movements' => [
        'headers' => [
            'when' => 'Când',
            'change' => 'Modificare',
            'reason' => 'Motiv',
            'who' => 'Înregistrat de',
        ],
        'showing' => 'Se afișează :shown din :total mișcări',
        'from_supplier' => 'de la :name',
    ],

    'adjust' => [
        'title' => 'Ajustare stoc · :sku',
        'action' => 'Ajustează',
        'action_for' => 'Ajustează stocul pentru :sku',
        'current' => 'Stoc curent',
        'reason_label' => 'Ce fel de mișcare este aceasta?',
        'quantity' => 'Cantitate',
        'note' => 'Notă',
        'supplier' => 'Furnizor (opțional)',
        'supplier_note' => 'De la cine a venit acest lot. Un nume nou este reținut pentru data viitoare; spațiile și majusculele sunt ignorate la potrivire.',
        'submit' => 'Înregistrează',
        'help' => [
            'restock' => 'Marfă sosită. Cantitatea trebuie să fie pozitivă.',
            'manual_adjustment' => 'O corecție după numărarea raftului. Poate fi negativă, iar nota este obligatorie pentru a explica motivul.',
        ],
    ],

    'create' => [
        'title' => 'Piesă nouă',
        'submit' => 'Adaugă piesa',
    ],

    'edit' => [
        'title' => 'Editează :sku',
        'action' => 'Editează :sku',
        'submit' => 'Salvează modificările',
    ],

    'form' => [
        'sku' => 'Cod intern',
        'name' => 'Nume',
        'brand_id' => 'Marcă (opțional)',
        'category' => 'Categorie (opțional)',
        'description' => 'Observații (opțional)',
        'unit_price' => 'Preț de vânzare',
        'cost_price' => 'Preț de achiziție (opțional)',
        'reorder_level' => 'Prag de reaprovizionare',
        'location' => 'Locație raft (opțional)',
        'stock_title' => 'Stocul nu se introduce manual',
        'stock_note' => 'O piesă nouă pornește de la zero. Stocul se modifică doar prin recepție marfă sau prin montarea la o lucrare, așa că fiecare modificare lasă o înregistrare.',
    ],

    'delete' => [
        'action' => 'Șterge :sku',
        'confirm' => 'Ștergeți piesa :sku? Codul intern rămâne rezervat și nu poate fi folosit pentru altă piesă până la restaurarea acesteia.',
    ],

    'restore' => [
        'action' => 'Restaurează :sku',
        'label' => 'Restaurează',
        'confirm' => 'Restaurați piesa :sku? Revine în catalog cu stocul neschimbat.',
    ],

    'empty' => [
        'none' => [
            'title' => 'Nicio piesă încă',
            'body' => 'Catalogul este gol. Adăugați prima piesă pentru a începe urmărirea stocului.',
        ],
        'no_matches' => [
            'title' => 'Nicio piesă nu corespunde căutării',
            'body' => 'Verificați codul intern sau încercați o parte din nume ori un cod OE.',
        ],
        'none_low' => [
            'title' => 'Nicio piesă cu stoc redus',
            'body' => 'Toate piesele sunt peste pragul de reaprovizionare.',
            'clear' => 'Afișează toate piesele',
        ],
    ],

    'validation' => [
        'sku_deleted' => 'Acest cod intern aparține unei piese șterse, :name. Un administrator o poate restaura din catalog.',
        'adjustment_note_required' => 'O ajustare manuală are nevoie de o notă care să explice motivul.',
    ],

    'flash' => [
        'created' => 'Piesa :sku a fost adăugată.',
        'updated' => 'Piesa :sku a fost actualizată.',
        'deleted' => 'Piesa :sku a fost ștearsă. Codul intern rămâne rezervat.',
        'restored' => 'Piesa :sku a fost restaurată.',
        'stock_recorded' => ':sku: :change înregistrat. Stocul este acum :balance.',
        'stock_negative' => 'Stocul pentru :sku este acum :balance, sub zero. Mișcarea a fost totuși salvată.',
    ],
];
