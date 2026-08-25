<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

#[Fillable(['school_id', 'name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLES = ['admin', 'school', 'platform_admin', 'school_owner', 'school_admin', 'instructor', 'content_author', 'content_reviewer'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, bool> */
    private array $permissionChecks = [];

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
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'platform_admin'], true);
    }

    public function isSchool(): bool
    {
        return $this->school_id !== null && ! $this->isAdmin();
    }

    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }

    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        return $this->permissionChecks[$permission] ??= $this->permissions()->where('name', $permission)->exists()
            || DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('permission_role.role', $this->role)
                ->where('permissions.name', $permission)
                ->exists();
    }

    public function canAccessClassroom(Classroom $classroom): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isSchool() || $this->school_id !== $classroom->school_id) {
            return false;
        }

        return ! $this->isInstructor()
            || $this->instructor()->whereHas('classrooms', fn ($query) => $query->whereKey($classroom->id))->exists();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin', 'platform_admin' => 'Administrador da plataforma',
            'school', 'school_admin' => 'Administrador da escola',
            'school_owner' => 'Responsável da escola',
            'instructor' => 'Instrutor',
            'content_author' => 'Autor de conteúdo',
            'content_reviewer' => 'Revisor de conteúdo',
            default => $this->role,
        };
    }

    public function permissionNames(): Collection
    {
        if ($this->isAdmin()) {
            return Permission::orderBy('name')->pluck('name');
        }

        return $this->permissions()->pluck('name')
            ->merge(DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('permission_role.role', $this->role)
                ->pluck('permissions.name'))
            ->unique()->sort()->values();
    }
}
