<?php 

namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    const ACCESS_NONE = 0;          // Banned
    const ACCESS_READONLY = 10;     // Can view members profiles
    const ACCESS_ASSIGN = 20;       // Can add members and assign decorations
    const ACCESS_CREATE = 30;       // Can add new decorations
    const ACCESS_ADMIN = 40;        // Can add other users

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    protected $dates = ['deleted_at'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['password', 'remember_token', 'last_login_time'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    // Relationships
    public function userSSO() 
    {
		return $this->hasMany('App\UserSSO', 'user_id');
    }
    
    /**
     * Convenience matcher
     * @param accessLevelName A name to fuzzy match on
     */
    public function hasAccessLevel($accessLevelName) 
    {
        switch (strtolower((string) $accessLevelName)) {
            case 'assign':
            case ((string) User::ACCESS_ASSIGN):
                return $this->access_level >= User::ACCESS_ASSIGN;
            case 'create':
            case ((string) User::ACCESS_CREATE):
                return $this->access_level >= User::ACCESS_CREATE;
            case 'admin':
            case ((string) User::ACCESS_ADMIN):
                return $this->access_level >= User::ACCESS_ADMIN;
            case 'none':
            case ((string) User::ACCESS_NONE):
                return $this->access_level >= User::ACCESS_NONE;
            default:
            case 'readonly':
            case ((string) User::ACCESS_READONLY):
                return $this->access_level >= User::ACCESS_READONLY;
        }
    }

}
