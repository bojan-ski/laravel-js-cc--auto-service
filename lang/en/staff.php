<?php

return [
    'title' => 'Staff',
    'new' => 'New staff',
    'count' => '{0} No staff|{1} 1 staff member|[2,*] :count staff members',
    'search_label' => 'Search staff',
    'search_placeholder' => 'Search name or email',

    'tabs' => [
        'label' => 'Filter staff by role',
        'all' => 'All',
    ],

    'headers' => [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'actions' => 'Actions',
    ],

    'offboarded' => [
        'on' => 'Offboarded :date',
    ],

    'you' => 'You',

    'actions' => [
        'edit' => 'Edit :name',
        'offboard' => 'Offboard',
        'offboard_named' => 'Offboard :name',
        'restore' => 'Restore',
        'restore_named' => 'Restore :name',
    ],

    'create' => [
        'title' => 'New staff member',
        'submit' => 'Create account',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'submit' => 'Save changes',
    ],

    'form' => [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'role_placeholder' => 'Choose a role',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'password_optional' => 'New password',
        'password_note' => 'Leave blank to keep the current password.',
        'own_role_note' => 'You cannot change your own role or offboard yourself. Ask another administrator.',
    ],

    'offboard' => [
        'title' => 'Offboard :name?',
        'consequences' => [
            'They can no longer sign in or reset their password',
            'Their name stays on every job, part and invoice they worked on',
            'Their email address stays reserved',
            'You can restore the account later',
        ],
        'submit' => 'Offboard :name',
    ],

    'restore' => [
        'confirm' => 'Restore :name? They will be able to sign in again.',
    ],

    'empty' => [
        'title' => 'No staff match those filters',
        'body' => 'Check the spelling, or clear the filters to see everyone.',
        'clear' => 'Clear filters',
    ],

    'validation' => [
        'email_offboarded' => 'That address belongs to :name, whose account is offboarded. Restore their account instead of creating a new one.',
    ],

    'flash' => [
        'created' => ':name can now sign in.',
        'updated' => 'Saved :name.',
        'offboarded' => ':name has been offboarded.',
        'restored' => ':name has been restored and can sign in again.',
        'last_admin' => 'That would leave the shop with no administrator. Give someone else the administrator role first.',
    ],
];
