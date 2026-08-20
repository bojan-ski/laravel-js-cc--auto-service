<?php

namespace App\Enums;

enum UserRole: string
{
    case Mechanic = 'mechanic';
    case Receptionist = 'receptionist';
    case Admin = 'admin';
}
