<?php

return [
    'title' => 'Clienți',

    'index' => [
        'search_label' => 'Caută clienți',
        'search_placeholder' => 'Nume, telefon, nr. înmatriculare sau vehicul',
        'new' => 'Client nou',
        'count' => ':count client|:count clienți|:count de clienți',

        'headers' => [
            'name' => 'Nume',
            'phone' => 'Telefon',
            'vehicles' => 'Vehicule',
            'email' => 'E-mail',
        ],
    ],

    'empty' => [
        'none' => [
            'title' => 'Niciun client încă.',
            'body' => 'Adăugați primul client pentru a începe să preluați lucrări.',
        ],

        'no_matches' => [
            'title' => 'Niciun client nu corespunde căutării.',
            'body' => 'Verificați ortografia sau încercați o parte din numărul de telefon.',
        ],
    ],

    'show' => [
        'contact' => 'Date de contact',
        'phone' => 'Telefon',
        'email' => 'E-mail',
        'address' => 'Adresă',
        'tax_id' => 'Cod fiscal (PIB)',
        'registration_number' => 'Nr. de înregistrare (MB)',
        'receives_reminders' => 'Mementouri de service',
        'reminders_on' => 'Activate',
        'reminders_off' => 'Dezactivate',
        'notes' => 'Observații',
        'jobs' => 'Istoric lucrări',
        'jobs_empty' => 'Nicio lucrare înregistrată încă.',
        'jobs_view_all_for' => 'Vezi toate lucrările (:count) pentru :name',
    ],

    'create' => [
        'title' => 'Client nou',
        'submit' => 'Creează clientul',
    ],

    'edit' => [
        'title' => 'Editează :name',
        'submit' => 'Salvează modificările',
    ],

    'form' => [
        'name' => 'Nume',
        'phone' => 'Telefon',
        'email' => 'E-mail (opțional)',
        'address' => 'Adresă (opțional)',
        'company' => 'Companie',
        'company_hint' => 'Pentru o companie, introduceți atât PIB, cât și MB. Lăsați ambele goale pentru o persoană fizică.',
        'tax_id' => 'Cod fiscal (PIB)',
        'registration_number' => 'Nr. de înregistrare (MB)',
        'receives_reminders' => 'Trimite mementouri de service',
        'notes' => 'Observații (opțional)',
    ],

    'validation' => [
        'phone_taken' => 'Acest număr de telefon aparține deja clientului :name.',
        'tax_id_digits' => 'Un PIB are exact 9 cifre.',
        'tax_id_checksum' => 'Cifra de control a acestui PIB este greșită. Verificați numărul pentru o greșeală de tastare.',
        'registration_number_digits' => 'Un MB are exact 8 cifre.',
        'registration_number_required' => 'O companie are nevoie și de MB, pe lângă PIB.',
        'registration_number_without_tax_id' => 'MB se înregistrează doar pentru o companie. Introduceți și PIB-ul sau lăsați MB gol.',
    ],

    'intake' => [
        'legend' => 'Client și vehicul',
        'customer' => 'client',
        'vehicle' => 'vehicul',
        'attributes' => [
            'new_customer_name' => 'nume',
            'new_customer_phone' => 'telefon',
            'new_customer_email' => 'e-mail',
            'new_vehicle_plate' => 'număr de înmatriculare',
            'new_vehicle_make' => 'marcă',
            'new_vehicle_model' => 'model',
            'new_vehicle_year' => 'an',
        ],
        'search_label' => 'Găsiți un client',
        'search_hint' => 'Nume, telefon sau nr. înmatriculare. Lăsați gol pentru a adăuga un client nou mai jos.',
        'results' => ':count client găsit|:count clienți găsiți|:count de clienți găsiți',
        'results_none' => 'Niciun client nu corespunde. Completați datele clientului nou mai jos.',
        'picked' => 'Client',
        'change' => 'Schimbă clientul',
        'new_customer' => 'Client nou',
        'existing_vehicle' => 'Vehicul existent',
        'existing_vehicle_placeholder' => 'Selectați un vehicul sau adăugați un client nou mai jos',
        'vehicle_legend' => 'Vehicul',
        'new_vehicle_option' => 'Vehicul nou',
        'new_vehicle' => 'Vehicul nou',
        'locked_hint' => 'Introduceți mai întâi numele și telefonul clientului.',
        'required_customer' => 'Completați câmpul „:attribute” pentru clientul nou sau găsiți un client existent.',
        'required_vehicle' => 'Completați câmpul „:attribute” pentru vehicul sau alegeți un vehicul existent.',
        'vehicle_not_theirs' => 'Acest vehicul nu aparține clientului selectat.',
        'plate_taken' => ':plate aparține deja clientului :owner.',
    ],

    'delete' => [
        'action' => 'Șterge :name',
        'confirm' => 'Ștergeți clientul :name? Nu va mai apărea în lista de clienți. Nimic nu este eliminat definitiv.',
    ],

    'flash' => [
        'created' => 'Clientul :name a fost adăugat.',
        'updated' => 'Clientul :name a fost actualizat.',
        'deleted' => 'Clientul :name a fost șters.',
    ],
];
