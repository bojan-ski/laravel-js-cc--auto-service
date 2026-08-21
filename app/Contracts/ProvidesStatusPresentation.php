<?php

namespace App\Contracts;

interface ProvidesStatusPresentation
{
    public function label(): string;

    public function colorToken(): string;
}
