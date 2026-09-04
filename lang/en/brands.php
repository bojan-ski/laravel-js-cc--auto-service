<?php

return [

    'title' => 'Brands',
    'new' => 'New brand',
    'count' => '{0} No brands|{1} 1 brand|[2,*] :count brands',
    'search_label' => 'Search brands',
    'search_placeholder' => 'Search brand name',

    'tiers' => [
        'aftermarket' => 'Aftermarket',
        'original' => 'Original',
        'first_fit' => 'First fit',
        'genuine' => 'Genuine',
    ],

    'headers' => [
        'name' => 'Name',
        'tier' => 'Quality',
        'parts' => 'Parts',
        'actions' => 'Actions',
    ],

    'picker' => [
        'placeholder' => 'No brand',
        'option' => ':name · :tier',
    ],

    'trashed' => [
        'badge' => 'Deleted',
    ],

    'create' => [
        'title' => 'New brand',
        'submit' => 'Add brand',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'action' => 'Edit :name',
        'submit' => 'Save changes',
    ],

    'form' => [
        'name' => 'Name',
        'quality_tier' => 'Quality tier',
        'tier_note' => 'The tier describes what the brand supplies, not an individual part. It is shown beside every part carrying this brand.',
    ],

    'delete' => [
        'action' => 'Delete :name',
        'confirm' => 'Delete :name? Parts already carrying it keep the brand, and the name stays reserved until it is restored.',
    ],

    'restore' => [
        'action' => 'Restore :name',
        'label' => 'Restore',
        'confirm' => 'Restore :name? It becomes selectable on the part form again.',
    ],

    'empty' => [
        'none' => [
            'title' => 'No brands yet',
            'body' => 'Add the first brand to record who makes the parts on the shelf.',
        ],
        'no_matches' => [
            'title' => 'No brands match that search',
            'body' => 'Try part of the name.',
        ],
    ],

    'parts_count' => '{0} None|{1} 1 part|[2,*] :count parts',

    'validation' => [
        'name_deleted' => 'That name belongs to a deleted brand. Restore it instead of creating a second one.',
    ],

    'flash' => [
        'created' => ':name has been added.',
        'updated' => ':name has been updated.',
        'deleted' => ':name has been deleted. Its name stays reserved.',
        'restored' => ':name has been restored.',
    ],

];
