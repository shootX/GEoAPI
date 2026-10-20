<?php

namespace App\Models;










use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Electrik\Concerns\ExportsElectrikPersonalData;
use Spatie\PersonalDataExport\ExportsPersonalData;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Mpociot\Teamwork\Traits\UserHasTeams;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail, ExportsPersonalData, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use AuthenticationLoggable, ExportsElectrikPersonalData, HasApiTokens, HasFactory, HasRoles, Impersonate, Notifiable, PasskeyAuthenticatable, UserHasTeams;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }
}
