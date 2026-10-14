<?php

namespace App\Services;

use App\Models\Article;
use App\Models\HomePage;
use App\Models\LookbookItem;
use App\Models\ProductImage;
use App\Support\PublicImageUrl;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Browses, uploads and deletes files on the public disk and tells which records still reference them. */
class MediaLibraryService
{
    public const UPLOAD_DIRECTORY = 'media';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'];

    private function disk(): Filesystem
    {
        return Storage::disk(PublicImageUrl::DISK);
    }

    /**
     * @return Collection<int, array{path:string,name:string,folder:string,size:int,url:string,isImage:bool,modifiedAt:int,usedBy:array<int,string>}>
     */
    public function list(?string $folder = null, ?string $search = null, string $usage = 'all'): Collection
    {
        $used = $this->usageMap();
        $search = $search !== null ? mb_strtolower(trim($search)) : null;

        return collect($this->disk()->allFiles())
            ->reject(fn (string $p) => str_starts_with(basename($p), '.'))
            ->map(fn (string $path) => [
                'path' => $path,
                'name' => basename($path),
                'folder' => dirname($path) === '.' ? '' : explode('/', $path)[0],
                'size' => (int) $this->disk()->size($path),
                'url' => (string) PublicImageUrl::for($path),
                'isImage' => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true),
                'modifiedAt' => (int) $this->disk()->lastModified($path),
                'usedBy' => $used[$path] ?? [],
            ])
            ->when($folder, fn (Collection $c) => $c->where('folder', $folder))
            ->when($search, fn (Collection $c) => $c->filter(fn ($f) => str_contains(mb_strtolower($f['path']), $search)))
            ->when($usage === 'used', fn (Collection $c) => $c->filter(fn ($f) => $f['usedBy'] !== []))
            ->when($usage === 'unused', fn (Collection $c) => $c->filter(fn ($f) => $f['usedBy'] === []))
            ->sortByDesc('modifiedAt')
            ->values();
    }

    /** @return array<int, string> */
    public function folders(): array
    {
        return collect($this->disk()->allFiles())
            ->map(fn (string $p) => str_contains($p, '/') ? explode('/', $p)[0] : '')
            ->filter()->unique()->sort()->values()->all();
    }

    /** @return array<string, array<int, string>> path => human readable usages */
    public function usageMap(): array
    {
        $map = [];
        $add = function (?string $path, string $label) use (&$map): void {
            if ($path) {
                $map[$path][] = $label;
            }
        };

        ProductImage::with('product:id,name')->get()->each(fn ($i) => $add($i->path, 'Product: '.($i->product?->name ?? '#'.$i->product_id)));
        Article::select('id', 'title', 'cover_image_path')->get()->each(fn ($a) => $add($a->cover_image_path, 'Article: '.$a->title));
        HomePage::select('id', 'editorial_image_path')->get()->each(fn ($h) => $add($h->editorial_image_path, 'Home page: editorial image'));
        LookbookItem::select('id', 'title', 'image_path')->get()->each(fn ($l) => $add($l->image_path, 'Lookbook: '.$l->title));

        return $map;
    }

    public function store(UploadedFile $file): string
    {
        return $file->store(self::UPLOAD_DIRECTORY, PublicImageUrl::DISK);
    }

    /** Deletes an unreferenced file; throws when the path is invalid, missing or still in use. */
    public function delete(string $path): void
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || ! $this->disk()->exists($path)) {
            throw new InvalidArgumentException('File not found.');
        }

        if (isset($this->usageMap()[$path])) {
            throw new InvalidArgumentException('This file is in use: '.implode(', ', $this->usageMap()[$path]));
        }

        $this->disk()->delete($path);
    }
}
