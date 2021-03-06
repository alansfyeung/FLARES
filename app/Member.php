<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
	use SoftDeletes;
	
	protected $primaryKey = 'regt_num';
	protected $dates = ['deleted_at', 'dob'];
	protected $guarded = ['regt_num', 'coms_username', 'coms_id', 'forums_username', 'forums_userid', 'is_enrolled'];
    protected $dateFormat = '';
	
	// Disable any auto-increment business
	public $incrementing = false;
	
	
	// Relationships
	public function postings()
    {
		return $this->hasMany('App\Promotion', 'regt_num'); 
	}
    
	public function pictures()
    {
		return $this->hasMany('App\MemberPicture', 'regt_num'); 
	}
    
    public function decorations()
    {
        return $this->hasMany('App\MemberDecoration', 'regt_num');
    }

    public function decoration_approvals() 
    {
        return $this->hasMany('App\DecorationApproval', 'regt_num');
    }
    
	public function current_posting()
    {
        // return $this->hasOne('App\PostingPromo', 'regt_num')->select('new_posting as posting', 'effective_date', 'is_discharge')->whereNotNull('new_posting')->orderBy('effective_date', 'desc');
        return $this->hasOne('App\Promotion', 'regt_num')->whereNotNull('new_posting')->orderBy('effective_date', 'desc');
        // ->select('new_posting as posting', 'effective_date', 'is_discharge')
    }
    
	public function current_rank()
    {
        return $this->hasOne('App\Promotion', 'regt_num')->whereNotNull('new_rank')->orderBy('effective_date', 'desc');
        // ->select('new_rank as rank', 'effective_date', 'is_acting')
    }
    
	public function current_platoon()
    {
        return $this->hasOne('App\Promotion', 'regt_num')->whereNotNull('new_platoon')->orderBy('effective_date', 'desc');
        // ->select('new_platoon as platoon', 'effective_date')
    }
    
	// Basic statistics
	public function num_members()
    {
		$num = $this->query()->count();
		return $num;
	}
	
}