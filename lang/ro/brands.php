<?php

return [

    'title' => 'Mărci',
    'new' => 'Marcă nouă',
    'count' => ':count marcă|:count mărci|:count de mărci|{0}Nicio marcă',
    'search_label' => 'Caută mărci',
    'search_placeholder' => 'Caută după numele mărcii',

    'tiers' => [
        'aftermarket' => 'Aftermarket',
        'original' => 'Original',
        'first_fit' => 'Prima montare',
        'genuine' => 'Original producător',
    ],

    'headers' => [
        'name' => 'Nume',
        'tier' => 'Calitate',
        'parts' => 'Piese',
        'actions' => 'Acțiuni',
    ],

    'picker' => [
        'placeholder' => 'Fără marcă',
        'option' => ':name · :tier',
    ],

    'trashed' => [
        'badge' => 'Ștearsă',
    ],

    'create' => [
        'title' => 'Marcă nouă',
        'submit' => 'Adaugă marca',
    ],

    'edit' => [
        'title' => 'Editează :name',
        'action' => 'Editează :name',
        'submit' => 'Salvează modificările',
    ],

    'form' => [
        'name' => 'Nume',
        'quality_tier' => 'Clasă de calitate',
        'tier_note' => 'Clasa descrie ce livrează marca, nu o piesă anume. Este afișată lângă fiecare piesă care are această marcă.',
    ],

    'delete' => [
        'action' => 'Șterge :name',
        'confirm' => 'Ștergeți marca :name? Piesele care o au deja își păstrează marca, iar numele rămâne rezervat până la restaurare.',
    ],

    'restore' => [
        'action' => 'Restaurează :name',
        'label' => 'Restaurează',
        'confirm' => 'Restaurați marca :name? Va putea fi selectată din nou în formularul pentru piese.',
    ],

    'empty' => [
        'none' => [
            'title' => 'Nicio marcă încă',
            'body' => 'Adăugați prima marcă pentru a înregistra cine produce piesele de pe raft.',
        ],
        'no_matches' => [
            'title' => 'Nicio marcă nu corespunde căutării',
            'body' => 'Încercați o parte din nume.',
        ],
    ],

    'parts_count' => ':count piesă|:count piese|:count de piese|{0}Niciuna',

    'validation' => [
        'name_deleted' => 'Acest nume aparține unei mărci șterse. Restaurați-o în loc să creați una nouă.',
    ],

    'flash' => [
        'created' => 'Marca :name a fost adăugată.',
        'updated' => 'Marca :name a fost actualizată.',
        'deleted' => 'Marca :name a fost ștearsă. Numele rămâne rezervat.',
        'restored' => 'Marca :name a fost restaurată.',
    ],

];
