<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserSSO extends Model
{
    protected $table = 'user_sso';
	protected $primaryKey = 'sso_id';
	
	// Relationships
	public function user() {
		return $this->belongsTo('App\User', 'user_id');
	}
	
}
