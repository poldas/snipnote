<?php

declare(strict_types=1);

namespace App\Mapper;

use App\DTO\Note\PublicNoteListItemDto;
use App\DTO\Note\PublicNoteListResponseDto;
use App\DTO\Note\PublicNoteResponseDTO;

final class PublicNoteJsonMapper
{
    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function mapResponse(PublicNoteListResponseDto $response): array
    {
        return [
            'data' => array_map(fn (PublicNoteListItemDto $item): array => $this->mapItem($item), $response->data),
            'meta' => [
                'page' => $response->meta->page,
                'per_page' => $response->meta->perPage,
                'total_items' => $response->meta->totalItems,
                'total_pages' => $response->meta->totalPages,
            ],
        ];
    }

    /**
     * @return array{title: string, description_excerpt: string, labels: list<string>, created_at: string, url_token: string}
     */
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

    /**
     * @return array{title: string, description: string, labels: list<string>, created_at: string}
     */
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
