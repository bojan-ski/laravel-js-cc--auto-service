<?php

return [
    'name' => 'Auto Service',
    'document_title' => ':page — :app',

    'nav' => [
        'primary' => 'Primary navigation',
        'jobs' => 'Jobs',
        'appointments' => 'Appointments',
        'customers' => 'Customers',
        'parts' => 'Parts',
        'toggle' => 'Menu',
        'user_menu' => 'Account menu',
        'sign_out' => 'Sign out',
    ],

    'value_none' => '—',
    'value_none_spoken' => 'Not provided',
    'deleted' => 'deleted',

    'actions' => [
        'view' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'cancel' => 'Cancel',
        'search' => 'Search',
        'clear_search' => 'Clear search',
    ],

    'roles' => [
        'mechanic' => 'Mechanic',
        'receptionist' => 'Receptionist',
        'admin' => 'Administrator',
    ],

    'flash' => [
        'success' => 'Success',
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Information',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'empty' => 'Nothing to show yet.',
    ],

    'auth' => [
        'login' => [
            'title' => 'Sign in',
            'email' => 'Email',
            'password' => 'Password',
            'remember' => 'Remember me',
            'submit' => 'Sign in',
            'forgot_link' => 'Forgot your password?',
        ],

        'forgot' => [
            'title' => 'Reset your password',
            'intro' => 'Enter your email address and we will send you a reset link if the address is on file.',
            'email' => 'Email',
            'submit' => 'Send reset link',
            'back' => 'Back to sign in',
        ],

        'reset' => [
            'title' => 'Choose a new password',
            'email' => 'Email',
            'password' => 'New password',
            'confirm' => 'Confirm new password',
            'submit' => 'Save new password',
        ],

        'reset_link_generic' => 'If that address is on file, a password reset link is on its way.',

        'flash' => [
            'logged_out' => 'You have been signed out.',
            'password_reset' => 'Your password has been reset. Please sign in.',
        ],
    ],
];
