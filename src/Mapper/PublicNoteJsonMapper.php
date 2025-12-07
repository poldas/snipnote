<?php

declare(strict_types=1);

namespace App\Mapper;

use App\DTO\Note\PublicNoteListItemDto;
use App\DTO\Note\PublicNoteListResponseDto;
use App\DTO\Note\PublicNoteResponseDTO;

final class PublicNoteJsonMapper
{
    public function mapResponse(PublicNoteListResponseDto $response): array
    {
        return [
            'data' => array_map(fn(PublicNoteListItemDto $item): array => $this->mapItem($item), $response->data),
            'meta' => [
                'page' => $response->meta->page,
                'per_page' => $response->meta->perPage,
                'total_items' => $response->meta->totalItems,
                'total_pages' => $response->meta->totalPages,
            ],
        ];
    }

    public function mapItem(PublicNoteListItemDto $item): array
    {
        return [
            'title' => $item->title,
            'description_excerpt' => $item->descriptionExcerpt,
            'labels' => $item->labels,
            'created_at' => $item->createdAt->format(\DateTimeInterface::ATOM),
            'url_token' => $item->urlToken,
        ];
    }

    public function mapPublicNote(PublicNoteResponseDTO $dto): array
    {
        return [
            'title' => $dto->title,
            'description' => $dto->description,
            'labels' => $dto->labels,
            'created_at' => $dto->createdAt,
        ];
    }
}

