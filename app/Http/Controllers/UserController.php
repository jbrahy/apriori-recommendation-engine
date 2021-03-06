<?php

namespace App\Http\Controllers;

use App\OauthClient;
use App\User;
use Illuminate\Http\Request;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('oauth', ['except' => ['store']]);
        $this->middleware('authorize:'.__CLASS__, ['except' => ['store']]);
    }

    /**
     * @param int $id
     *
     * @return mixed
     */
    public function show(int $id)
    {
        if ($user = User::find($id)) {
            return $this->success($user, 200);
        }

        return $this->error("The user with {$id} doesn't exist", 422);
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        return $this->success($this->respondWithPagination(User::paginate(self::LIMIT), $request->get('access_token')), 200);
    }

    /**
     * @param Request $request
     * @param int     $id
     *
     * @return mixed
     */
    public function update(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error("The user with {$id} doesn't exist", 404);
        }

        if ($request->get('email') !== $user->email) {
            $this->validateRequest($request);
        } else {
            $this->validate($request, [
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required',
            'name'                  => 'required|max:180',
        ]);
        }

        $user->setUser($request)->save();

        return $this->success([
            'message' => "User with id {$user->id} updated successfully.",
            'data'    => $user,
        ], 200);
    }

    /**
     * @param int $id
     *
     * @return mixed
     */
    public function credentials($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error("The user with id {$id} doesn't exist", 404);
        }

        OauthClient::find($user->client)->delete();

        $secret = $this->generateCredentials();
        $client = $this->generateCredentials();

        (new OauthClient())->setOauthClient($client, $secret, $user->name)->save();

        $user->setUser(null, $client, $secret)->save();

        return $this->success([
            'message' => "User with id {$user->id} credentials updated successfully.",
            'data'    => $user,
        ], 200);
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function store(Request $request)
    {
        $this->validateRequest($request);

        $secret = $this->generateCredentials();
        $client = $this->generateCredentials();

        (new OauthClient())->setOauthClient($client, $secret, $request->get('name'))->save();

        $user = (new User())->setUser($request, $client, $secret);
        $user->save();

        return $this->success([
            'message' => "User with id {$user->id} was created successfully.",
            'data'    => $user,
        ], 201);
    }

    /**
     * @param int $id
     *
     * @return mixed
     */
    public function destroy(int $id)
    {
        $user = User::find($id);

        foreach ($user->redisKeys as $redisKey) {
            $redisKey->remove()->delete();
        }

        OauthClient::find($user->client)->delete();

        $user->delete();

        return $this->success("User with id {$id} successfully deleted.", 200);
    }

    /**
     * @return string
     */
    private function generateCredentials() : string
    {
        return (string) bin2hex(random_bytes(20));
    }

    /**
     * @param Request $request
     */
    private function validateRequest(Request $request)
    {
        $this->validate($request, [
        'email'                 => 'required|email|unique:users',
        'password'              => 'required|min:6|confirmed',
        'password_confirmation' => 'required',
        'name'                  => 'required|max:180',
    ]);
    }

     /**
      * @param Request   $request
      *
      * @return mixed
      */
     public function isAuthorized(Request $request)
     {
         $resource = 'users';

         $id = Authorizer::getResourceOwnerId();

         if (isset($this->getArgs($request)['id'])) {
             $id = $this->getArgs($request)['id'];
         }

         $user = User::find($id);

         return $this->authorizeUser($request, $resource, $user);
     }
}
