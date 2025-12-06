<?php

declare(strict_types=1);

namespace App\Entity;

enum NoteVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case DRAFT = 'draft';
}
