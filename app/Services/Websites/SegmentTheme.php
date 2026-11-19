<?php

namespace App\Services\Websites;

/**
 * Visual adaptation of the single site template to the company's segment: a background tone for the main area and a
 * simple line icon drawn inline (no external image, no extra request). Only appearance changes here; no text is
 * invented for the company. A segment without its own entry, or a version published before segment themes existed,
 * gets the default theme.
 */
class SegmentTheme
{
    /**
     * @var array<string, array{tone: string, icon: string}>
     */
    private const array THEMES = [
        'climatizacao' => ['tone' => '#E0F2FE', 'icon' => 'M12 2v20M4.9 4.9l14.2 14.2M2 12h20M4.9 19.1 19.1 4.9M9 4l3 3 3-3M9 20l3-3 3 3M4 9l3 3-3 3M20 9l-3 3 3 3'],
        'eletricistas' => ['tone' => '#FEF9C3', 'icon' => 'M13 2 4 14h7l-1 8 9-12h-7l1-8z'],
        'encanadores' => ['tone' => '#DBEAFE', 'icon' => 'M12 2.7c3.4 4 6 7.4 6 10.3a6 6 0 0 1-12 0c0-2.9 2.6-6.3 6-10.3z'],
        'oficinas' => ['tone' => '#E5E7EB', 'icon' => 'M14.7 6.3a4 4 0 0 0-5.3 5.3L3 18l3 3 6.4-6.4a4 4 0 0 0 5.3-5.3l-2.6 2.6-2.4-.6-.6-2.4z'],
        'assistencia-tecnica' => ['tone' => '#EDE9FE', 'icon' => 'M4 4h16v12H4zM8 20h8M12 16v4M9 8h6M9 11h4'],
        'jardinagem' => ['tone' => '#DCFCE7', 'icon' => 'M11 20A7 7 0 0 1 4 13c0-5 4-9 16-9 0 12-4 16-9 16zM4 21c3-5 6-8 10-10'],
        'limpeza' => ['tone' => '#CFFAFE', 'icon' => 'M12 3l1.5 3.5L17 8l-3.5 1.5L12 13l-1.5-3.5L7 8l3.5-1.5zM18 14l.8 2.2L21 17l-2.2.8L18 20l-.8-2.2L15 17l2.2-.8zM6 15l.6 1.4L8 17l-1.4.6L6 19l-.6-1.4L4 17l1.4-.6z'],
        'servicos-gerais' => ['tone' => '#FFEDD5', 'icon' => 'M3 9h18v11H3zM8 9V5h8v4M3 14h18'],
        'energia-solar' => ['tone' => '#FEF3C7', 'icon' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4'],
        'beleza-e-estetica' => ['tone' => '#FCE7F3', 'icon' => 'M6 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM6 15a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM8.1 7.9 20 20M8.1 16.1 20 4'],
        'servicos-profissionais' => ['tone' => '#F1F5F9', 'icon' => 'M3 7h18v13H3zM9 7V4h6v3M3 13h18'],
    ];

    private const array DEFAULT = ['tone' => '#F1F5F9', 'icon' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'];

    /**
     * @return array{tone: string, icon: string}
     */
    public static function for(?string $segmentSlug): array
    {
        return self::THEMES[(string) $segmentSlug] ?? self::DEFAULT;
    }
}
