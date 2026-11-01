<?php

namespace App\Models;

use App\Support\Studio;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'avatar_color', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Approve / send back work and confirm it went live:
     * the admin (CMO), the marketing lead and the social media manager.
     */
    public function canApprove(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'smm'], true);
    }

    public function canPublish(): bool
    {
        return $this->canApprove();
    }

    /** Designers, photographers, copywriters: they get the simple workspace. */
    public function isMaker(): bool
    {
        return ! $this->canApprove();
    }

    /** CMO reporting (revenue, ROMI, budgets): admin/CMO and the marketing lead only. */
    public function canSeeReports(): bool
    {
        return $this->is_active && in_array($this->role, ['admin', 'manager'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function roleLabel(): string
    {
        return Studio::ROLES[$this->role] ?? $this->role;
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }
}
