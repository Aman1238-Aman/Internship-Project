<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    protected $fillable = [
        'quiz_id',
        'type',
        'question_html',
        'media_image_path',
        'media_video_url',
        'marks',
        'display_order',
        'settings',
    ];

    protected $casts = [
        'type' => QuestionType::class,
        'settings' => 'array',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('display_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->media_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->media_image_path);
    }

    public function youtubeEmbedUrl(): ?string
    {
        if (! $this->media_video_url) {
            return null;
        }

        if (str_contains($this->media_video_url, 'youtube.com/embed/')) {
            return $this->media_video_url;
        }

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/', $this->media_video_url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        return $this->media_video_url;
    }
}
