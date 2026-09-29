<?php

return [
    'title' => 'Klijenti',

    'index' => [
        'search_label' => 'Pretraga klijenata',
        'search_placeholder' => 'Ime, telefon, tablice ili vozilo',
        'new' => 'Novi klijent',
        'count' => ':count klijent|:count klijenta|:count klijenata',

        'headers' => [
            'name' => 'Ime',
            'phone' => 'Telefon',
            'vehicles' => 'Vozila',
            'email' => 'Email',
        ],
    ],

    'empty' => [
        'none' => [
            'title' => 'Još nema klijenata.',
            'body' => 'Dodajte prvog klijenta da biste mogli da primate posao.',
        ],

        'no_matches' => [
            'title' => 'Nijedan klijent ne odgovara pretrazi.',
            'body' => 'Proverite pravopis ili pokušajte sa delom broja telefona.',
        ],
    ],

    'show' => [
        'contact' => 'Kontakt podaci',
        'phone' => 'Telefon',
        'email' => 'Email',
        'address' => 'Adresa',
        'tax_id' => 'PIB',
        'registration_number' => 'Matični broj (MB)',
        'notes' => 'Napomene',
        'jobs' => 'Istorija radnih naloga',
        'jobs_empty' => 'Još nema evidentiranih radnih naloga.',
        'jobs_view_all_for' => 'Prikaži sve radne naloge za :name (:count)',
    ],

    'create' => [
        'title' => 'Novi klijent',
        'submit' => 'Dodaj klijenta',
    ],

    'edit' => [
        'title' => 'Izmeni :name',
        'submit' => 'Sačuvaj izmene',
    ],

    'form' => [
        'name' => 'Ime',
        'phone' => 'Telefon',
        'email' => 'Email (opciono)',
        'address' => 'Adresa (opciono)',
        'company' => 'Pravno lice',
        'company_hint' => 'Za pravno lice unesite i PIB i matični broj. Za fizičko lice ostavite oba polja prazna.',
        'tax_id' => 'PIB',
        'registration_number' => 'Matični broj (MB)',
        'notes' => 'Napomene (opciono)',
    ],

    'validation' => [
        'phone_taken' => 'Ovaj broj telefona već pripada klijentu :name.',
        'tax_id_digits' => 'PIB ima tačno 9 cifara.',
        'tax_id_checksum' => 'Kontrolna cifra ovog PIB-a nije ispravna. Proverite da li je broj tačno unet.',
        'registration_number_digits' => 'Matični broj ima tačno 8 cifara.',
        'registration_number_required' => 'Za pravno lice je potreban i matični broj, ne samo PIB.',
        'registration_number_without_tax_id' => 'Matični broj se unosi samo za pravno lice. Unesite i PIB ili ostavite matični broj prazan.',
    ],

    'intake' => [
        'legend' => 'Klijent i vozilo',
        'customer' => 'klijent',
        'vehicle' => 'vozilo',
        'attributes' => [
            'new_customer_name' => 'ime',
            'new_customer_phone' => 'telefon',
            'new_customer_email' => 'email',
            'new_vehicle_plate' => 'registarska oznaka',
            'new_vehicle_make' => 'marka',
            'new_vehicle_model' => 'model',
            'new_vehicle_year' => 'godište',
        ],
        'search_label' => 'Pronađi klijenta',
        'search_hint' => 'Ime, telefon ili tablice. Ostavite prazno da biste ispod dodali novog klijenta.',
        'results' => ':count klijent pronađen|:count klijenta pronađena|:count klijenata pronađeno',
        'results_none' => 'Nijedan klijent ne odgovara. Popunite podatke o novom klijentu ispod.',
        'picked' => 'Klijent',
        'change' => 'Promeni klijenta',
        'new_customer' => 'Novi klijent',
        'existing_vehicle' => 'Postojeće vozilo',
        'existing_vehicle_placeholder' => 'Izaberite vozilo ili ispod dodajte novog klijenta',
        'vehicle_legend' => 'Vozilo',
        'new_vehicle_option' => 'Novo vozilo',
        'new_vehicle' => 'Novo vozilo',
        'locked_hint' => 'Prvo unesite ime i telefon klijenta.',
        'required_customer' => 'Nedostaje :attribute novog klijenta — unesite podatak ili pronađite postojećeg klijenta.',
        'required_vehicle' => 'Nedostaje :attribute vozila — unesite podatak ili izaberite postojeće vozilo.',
        'vehicle_not_theirs' => 'To vozilo ne pripada izabranom klijentu.',
        'plate_taken' => ':plate već pripada klijentu :owner.',
    ],

    'delete' => [
        'action' => 'Obriši :name',
        'confirm' => 'Obrisati :name? Klijent se više neće prikazivati na listi klijenata. Ništa se trajno ne briše.',
    ],

    'flash' => [
        'created' => 'Klijent :name je dodat.',
        'updated' => 'Klijent :name je izmenjen.',
        'deleted' => 'Klijent :name je obrisan.',
    ],
];
