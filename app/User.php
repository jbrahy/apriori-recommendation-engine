<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'client', //'secret', 'password'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at', 'password', 'secret' 
    ];
    
    /**
     * Has-many transactions relationship
     * 
     * @return mixed
     */
    public function redisKeys()
    {
        return $this->hasMany('App\RedisKey');
    }
    
    /**
     * Add a transaction to a redis key
     * 
     * @return boolean
     */
    public function addRedisKey(RedisKey $redisKey)
    {
        return $this->redisKeys()->save($redisKey);
    }
    
    /**
     * Verify user's credentials.
     *
     * @param  string $email
     * @param  string $password
     * @param  string $clientId
     * 
     * @return int|boolean
     */
    public function verify(string $email, string $password, string $clientId)
    {
        
        $user = self::where('email', $email)->first();
        
        if($user && Hash::check($password, $user->password) && $clientId == $user->client){
            return $user->id;
        }
        
        return false;
    }
    
    /**
     * @param Request   $request
     * @param string    $request
     * @param string    $client
     * @param string    $secret
     * 
     * @return User
     */ 
    public function setUser(Request $request, $client, $secret) : User
    {
		$this->name = $request->get('name');
		$this->email = $request->get('email');
		$this->secret = $secret;
		$this->client = $client;
		$this->password = Hash::make($request->get('password'));
		
		return $this;
    }
}
