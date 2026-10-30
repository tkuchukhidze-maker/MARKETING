<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A version of the work: an uploaded file, or a link to the finished
 * product (Drive, Figma, Canva, Dropbox…). Optionally tied to one platform.
 */
#[Fillable(['task_id', 'task_channel_id', 'user_id', 'version', 'kind', 'url', 'disk', 'path', 'original_name', 'mime', 'size'])]
class TaskAsset extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TaskChannel::class, 'task_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLink(): bool
    {
        return $this->kind === 'link';
    }

    public function isImage(): bool
    {
        if ($this->isLink()) {
            return (bool) preg_match('/\.(jpe?g|png|webp|gif)(\?.*)?$/i', (string) $this->url);
        }

        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isVideo(): bool
    {
        return ! $this->isLink() && str_starts_with((string) $this->mime, 'video/');
    }

    /** Where the browser should open this version. */
    public function href(): string
    {
        return $this->isLink() ? (string) $this->url : route('assets.show', $this);
    }

    public function host(): string
    {
        return (string) parse_url((string) $this->url, PHP_URL_HOST);
    }
}
