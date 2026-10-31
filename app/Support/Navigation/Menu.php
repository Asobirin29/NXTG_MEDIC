<?php

namespace App\Support\Navigation;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menyusun sidebar dari config('medic.navigation') untuk user tertentu:
 * item tanpa izin atau tanpa route dibuang, item aktif ditandai.
 */
class Menu
{
    public function __construct(private User $user) {}

    public function sections(): array
    {
        $sections = [];

        foreach (config('medic.navigation') as $section) {
            $items = array_values(array_filter(array_map(
                fn (array $item) => $this->item($item),
                $section['items'],
            )));

            if ($items) {
                $sections[] = [
                    'label' => $section['label'],
                    'id' => 'nxSec'.preg_replace('/\W/', '', $section['label']),
                    'items' => $items,
                    'active' => (bool) array_filter($items, fn ($i) => $i['active']),
                ];
            }
        }

        return $sections;
    }

    /** Entri command palette (⌘K) — hanya halaman yang boleh dibuka user. */
    public function palette(): array
    {
        $pages = [];

        foreach ($this->sections() as $section) {
            foreach ($section['items'] as $item) {
                foreach ($item['children'] ?: [$item] as $page) {
                    $pages[] = [
                        'g' => 'Halaman',
                        't' => $page['label'],
                        'u' => $page['url'],
                        'p' => $section['label'],
                        'i' => $page['icon'] ?? $item['icon'],
                    ];
                }
            }
        }

        return $pages;
    }

    private function item(array $item): ?array
    {
        if (isset($item['can']) && ! $this->user->can($item['can'])) {
            return null;
        }

        $children = array_values(array_filter(array_map(
            fn (array $child) => $this->link($child),
            $item['children'] ?? [],
        )));

        if (isset($item['children'])) {
            if (! $children) {
                return null;
            }

            return [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'url' => null,
                'id' => 'menu'.preg_replace('/\W/', '', $item['label']),
                'children' => $children,
                'active' => (bool) array_filter($children, fn ($c) => $c['active']),
            ];
        }

        $link = $this->link($item);

        return $link ? $link + ['children' => []] : null;
    }

    private function link(array $item): ?array
    {
        if (! Route::has($item['route'])) {
            return null;
        }

        return [
            'label' => $item['label'],
            'icon' => $item['icon'] ?? null,
            'url' => route($item['route']),
            'active' => request()->routeIs($item['active'] ?? $item['route']),
        ];
    }
}
