<?php

declare(strict_types=1);

namespace App\Entity;

enum NoteVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Draft = 'draft';
}

