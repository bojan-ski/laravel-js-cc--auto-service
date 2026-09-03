<?php

namespace App\Enums;

enum AttachmentType: string
{
    case Photo = 'photo';
    case Document = 'document';

    public function label(): string
    {
        return __('jobs.attachments.types.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Photo => ['image/jpeg', 'image/png', 'image/webp'],
            self::Document => ['application/pdf'],
        };
    }

    public function maxKilobytes(): int
    {
        return match ($this) {
            self::Photo => 10240,
            self::Document => 20480,
        };
    }

    public function hasVariants(): bool
    {
        return $this === self::Photo;
    }

    /**
     * @return list<string>
     */
    public static function allowedMimeTypes(): array
    {
        return array_merge(...array_map(
            fn (self $type): array => $type->mimeTypes(),
            self::cases(),
        ));
    }

    public static function forMimeType(?string $mimeType): ?self
    {
        if ($mimeType === null) {
            return null;
        }

        foreach (self::cases() as $type) {
            if (in_array($mimeType, $type->mimeTypes(), true)) {
                return $type;
            }
        }

        return null;
    }
}
