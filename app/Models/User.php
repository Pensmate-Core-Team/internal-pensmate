<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'generation',
        'class_name',
        'study_program',
        'division',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'generation' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function setDivisionAttribute(?string $value): void
    {
        $this->attributes['division'] = filled($value) ? Str::squish($value) : null;
    }

    public static function divisionKey(?string $division): string
    {
        return Str::lower(Str::squish($division ?? ''));
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin', 'coordinator' => 'Koordinator', default => 'Member'
        };
    }

    public function activationLinks(): HasMany
    {
        return $this->hasMany(MemberActivationLink::class);
    }

    public function assignedContents(): HasMany
    {
        return $this->hasMany(Content::class, 'assigned_to');
    }
}
