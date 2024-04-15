<?php

namespace App\Models;

use App\Models\Traits\AuthenticationLoggable;
use App\Models\Traits\WebAuthnManageCredentials;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laravel\Passport\HasApiTokens;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $last_seen_at
 * @property bool $is_admin
 * @property \Illuminate\Support\Collection<array-key,array-value> $preferences
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Client[] $clients
 * @property-read int|null $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Group[] $groups
 * @property-read int|null $groups_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Token[] $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TwoFAccount[] $twofaccounts
 * @property-read int|null $twofaccounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laragear\WebAuthn\Models\WebAuthnCredential[] $webAuthnCredentials
 * @property-read int|null $web_authn_credentials_count
 */
class User extends Authenticatable implements HasLocalePreference, WebAuthnAuthenticatable
{
    use AuthenticationLoggable;
    use HasApiTokens, HasFactory, Notifiable;
    use WebAuthnAuthentication, WebAuthnManageCredentials;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name', 'email', 'password', 'oauth_id', 'oauth_provider',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at'  => 'datetime',
        'password'           => 'hashed',
        'is_admin'           => 'boolean',
        'twofaccounts_count' => 'integer',
        'groups_count'       => 'integer',
    ];

    /**
     * These are extra user-defined events observers may subscribe to.
     */
    protected $observables = [
        'demoting',
    ];

    /**
     * Get the user's preferred locale.
     */
    public function preferredLocale() : string
    {
        return strval($this->preferences['lang']);
    }

    /**
     * Scope a query to only include admin users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    /**
     * Determine if the user is an administrator.
     *
     * @return bool
     */
    public function isAdministrator()
    {
        return $this->is_admin;
    }

    /**
     * Grant administrator permissions to the user.
     */
    public function promoteToAdministrator(bool $promote = true) : bool
    {
        if ($promote == false && $this->fireModelEvent('demoting') === false) {
            return false;
        }

        $this->is_admin = $promote;

        return true;
    }

    /**
     * Say if the user is the only registered administrator
     *
     * @return void
     */
    public function isLastAdministrator()
    {
        $admins = User::admins()->get();

        $toto = $admins->contains($this->id) && $admins->count() === 1;

        return $toto;
    }

    /**
     * Reset user password with a 12 chars random string.
     *
     * @return void
     */
    public function resetPassword()
    {
        $this->password = Hash::make(Str::password(12));

        event(new PasswordReset($this));
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Get Preferences attribute
     *
     * @param  string  $value
     * @return \Illuminate\Support\Collection<array-key, mixed>
     */
    public function getPreferencesAttribute($value)
    {
        $preferences = collect(config('2fauth.preferences'))->merge(json_decode($value));  /** @phpstan-ignore-line */

        return $preferences;
    }

    /**
     * set Email attribute
     *
     * @param  string  $value
     */
    public function setEmailAttribute($value) : void
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Returns an WebAuthnAuthenticatable user from a given Credential ID.
     */
    public static function getFromCredentialId(string $id) : ?WebAuthnAuthenticatable
    {
        return static::whereHas(
            'webauthnCredentials',
            static function ($query) use ($id) {
                return $query->whereKey($id);
            }
        )->first();
    }

    /**
     * Get the TwoFAccounts of the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<TwoFAccount>
     */
    public function twofaccounts()
    {
        return $this->hasMany(\App\Models\TwoFAccount::class);
    }

    /**
     * Get the Groups of the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Group>
     */
    public function groups()
    {
        return $this->hasMany(\App\Models\Group::class);
    }
}
