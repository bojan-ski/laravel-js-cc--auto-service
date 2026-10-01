<?php

return [
    'show' => [
        'title' => 'Prijem vozila :reference',
        'back' => 'Nazad na :reference',
        'print' => 'Štampaj zapisnik',
        'none' => 'Za ovaj radni nalog nije zabeležen prijem vozila.',
        'recorded_by' => 'Zabeležio/la :name',
    ],

    'fields' => [
        'fuel_level' => 'Nivo goriva',
        'damage_notes' => 'Oštećenja',
        'valuables' => 'Vredne stvari ostavljene u vozilu',
        'mileage_in' => 'Kilometraža pri prijemu',
        'received' => 'Primljeno',
        'signed_by_name' => 'Ime klijenta',
        'signature' => 'Potpis',
        'signature_method' => 'Način potpisivanja',
        'photo' => 'Fotografija',
        'vehicle' => 'Vozilo',
        'customer' => 'Klijent',
        'vin' => 'Broj šasije',
    ],

    'fuel_levels' => [
        'empty' => 'Prazan',
        'quarter' => '1/4',
        'half' => '1/2',
        'three_quarters' => '3/4',
        'full' => 'Pun',
    ],

    'signature_methods' => [
        'drawn' => 'Potpisano na tabletu',
        'paper' => 'Potpisano na papiru',
    ],

    'form' => [
        'heading' => 'Stanje pri prijemu',
        'submit' => 'Sačuvaj prijem',
    ],

    'photos' => [
        'heading' => 'Fotografije oštećenja',
        'print_alt' => 'Fotografija oštećenja :position od :count, nalog :reference',
        'empty' => 'Nema fotografija oštećenja.',
        'label' => 'Dodaj fotografiju oštećenja',
        'upload' => 'Otpremi fotografiju',
        'upload_for' => 'Otpremi fotografiju na prijem vozila za :reference',
        'hint' => 'JPEG, PNG ili WebP, do 10 MB.',
    ],

    'sign' => [
        'heading' => 'Potpis',
        'pad_label' => 'Polje za potpis',
        'pad_hint' => 'Klijent se potpisuje u polju prstom ili olovkom. Ako ne može da crta, odštampajte zapisnik i ispod potvrdite potpis na papiru.',
        'clear' => 'Obriši',
        'submit' => 'Potpiši',
        'empty' => 'Klijent se još nije potpisao.',
        'paper_hint' => 'Odštampajte zapisnik, dajte ga klijentu na potpis, pa potvrdite ovde.',
        'paper_submit' => 'Potvrdi potpis na papiru',
        'signed' => 'Potpisao/la :name · :date',
        'image_alt' => 'Potpis: :name',
        'read_only' => 'Ovaj zapisnik je potpisan i više se ne može menjati.',
        'errors' => [
            'unreadable' => 'Potpis nije moguće pročitati. Obrišite polje i potpišite se ponovo.',
            'empty' => 'Nacrtajte potpis u polju, pa pritisnite Potpiši.',
        ],
    ],

    'print' => [
        'title' => 'Zapisnik o prijemu vozila',
        'printed' => 'Odštampano :date',
        'declaration' => 'Potvrđujem da su stanje vozila i vredne stvari ostavljene u njemu tačno navedeni iznad.',
        'signature_line' => 'Potpis klijenta',
        'name_line' => 'Ime',
        'date_line' => 'Datum',
        'signed_on_paper' => 'Na papiru potpisao/la :name, :date',
        'action' => 'Štampaj',
        'back' => 'Nazad na prijem vozila',
    ],

    'summary' => [
        'label' => 'Prijem vozila',
        'not_recorded' => 'Nije zabeležen',
        'unsigned' => 'Zabeležen, nije potpisan',
        'signed' => 'Potpisan :date · :method',
        'photos' => ':count fotografija|:count fotografije|:count fotografija',
        'record' => 'Zabeleži prijem',
        'open' => 'Otvori zapisnik',
        'view' => 'Pogledaj zapisnik',
        'methods' => [
            'drawn' => 'na tabletu',
            'paper' => 'na papiru',
        ],
    ],

    'flash' => [
        'saved' => 'Prijem vozila je sačuvan.',
        'photo_added' => 'Fotografija je dodata.',
        'photo_deleted' => 'Fotografija je obrisana.',
        'signed' => 'Prijem vozila je potpisao/la :name.',
        'not_open' => 'Ovaj prijem vozila se više ne može menjati.',
    ],
];
