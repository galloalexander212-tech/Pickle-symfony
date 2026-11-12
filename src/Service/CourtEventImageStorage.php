<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CourtEventImageStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function store(UploadedFile $upload, string $folder): string|false
    {
        $prefix = match ($folder) {
            'courts' => 'court',
            'events' => 'event',
            default => throw new \InvalidArgumentException('Unsupported image upload folder.'),
        };

        if (!$upload->isValid() || ($upload->getSize() ?? PHP_INT_MAX) > 2 * 1024 * 1024) {
            return false;
        }

        $image = @getimagesize($upload->getPathname());
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if ($image === false || !isset($extensions[$image['mime']])) {
            return false;
        }

        $directory = $this->projectDir . '/public/uploads/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the image upload directory.');
        }

        $filename = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $extensions[$image['mime']];
        try {
            $upload->move($directory, $filename);
        } catch (FileException) {
            return false;
        }

        return 'uploads/' . $folder . '/' . $filename;
    }

    public function remove(?string $imagePath, string $folder): void
    {
        if ($imagePath === null || !in_array($folder, ['courts', 'events'], true)) {
            return;
        }

        $prefix = 'uploads/' . $folder . '/';
        $filename = str_starts_with($imagePath, $prefix) ? substr($imagePath, strlen($prefix)) : '';
        if ($filename === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $filename) !== 1 || str_contains($filename, '..')) {
            return;
        }

        $path = $this->projectDir . '/public/' . $imagePath;
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Unable to remove the previous court or event image.');
        }
    }
}
