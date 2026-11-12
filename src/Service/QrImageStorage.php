<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class QrImageStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    public function store(UploadedFile $upload): string|false
    {
        if (!$upload->isValid() || ($upload->getSize() ?? PHP_INT_MAX) > 2 * 1024 * 1024) {
            return false;
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mimeType = $upload->getMimeType();
        if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
            return false;
        }

        $directory = $this->projectDir . '/public/uploads/qr';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the QR upload directory.');
        }

        $filename = 'qr_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        try {
            $upload->move($directory, $filename);
        } catch (FileException) {
            return false;
        }

        return $filename;
    }

    public function remove(?string $filename): void
    {
        if ($filename === null || preg_match('/^qr_[a-f0-9]{32}\.(jpg|png|webp)$/', $filename) !== 1) {
            return;
        }

        $path = $this->projectDir . '/public/uploads/qr/' . $filename;
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Unable to remove the previous QR image.');
        }
    }
}
