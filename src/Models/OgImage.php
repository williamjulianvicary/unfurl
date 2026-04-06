<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read int $id
 * @property-read string $key
 * @property-read string $variant
 * @property-read string $disk
 * @property-read string $path
 * @property-read int $width
 * @property-read int $height
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 *
 * @method static Builder<static> fresh()
 */
final class OgImage extends Model
{
    protected $table = 'ogify_og_images';

    protected $fillable = [
        'key',
        'variant',
        'disk',
        'path',
        'width',
        'height',
    ];

    /**
     * Scope to only include images updated within the refresh threshold.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFresh(Builder $query): Builder
    {
        $days = config('og-image.refresh_after_days');

        if ($days === null) {
            return $query;
        }

        return $query->where('updated_at', '>=', Carbon::now()->subDays(config()->integer('og-image.refresh_after_days', 30)));
    }

    public function url(): string
    {
        $filesystem = Storage::disk($this->disk);

        if ($filesystem instanceof FilesystemAdapter) {
            return $filesystem->url($this->path);
        }

        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
        ];
    }
}
