<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Resources\UserDetailResource;
use App\Http\Resources\UserListResource;
use App\Models\User;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Resources\UserFavoriteResource;
use App\Models\UserFavorite;


/**
 * UserController
 * 
 * This class is responsible for handling user-related API requests.
 */
class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        // Get all users
        try {
            $currentUser = JWTAuth::parseToken()->authenticate();
        
            // 检查当前用户是否为管理员或者请求的是自己的信息
            if ($currentUser->category != 'admin' && $currentUser->user_id != $id) {
                return $this->sendError('Unauthorized', [], 403);
            }
            $users = User::all();
            return  $this->sendResponse(UserListResource::collection($users), 'Users fetched successfully'); 
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return $this->sendError('Error fetching users', [], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        Log::info('User creation request: ', $request->except('password'));

        try{
            $validatedData = $request->validate([
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => 'required|string|min:6',
                'profile_image_path' => 'nullable|string',
                'category' => 'nullable|string|max:50',
            ]);

            $validatedData['password'] = Hash::make($validatedData['password']);
            $user = User::create($validatedData);
            Log::info('User created successfully: ', ['id' => $user->user_id]);

            return $this->sendResponse(new UserDetailResource($user), 'User created successfully');
        } catch (QueryException $e) {
            Log::error('User creation failed: ' . $e->getMessage());
            return $this->sendError('User creation failed(Database issue)', [], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error during user creation: '. $e->getMessage());
            return $this->sendError( $e->getMessage(), [], 500);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id) 
    {
        try {
            $currentUser = JWTAuth::parseToken()->authenticate();
        
            // 检查当前用户是否为管理员或者请求的是自己的信息
            if ($currentUser->category != 'admin' && $currentUser->user_id != $id) {
                return $this->sendError('Unauthorized', [], 403);
            }
            $user = User::findOrFail($id);
            return $this->sendResponse(new UserDetailResource($user), 'User retrieved successfully');
        } catch (QueryException $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            $errorMessage = $this->simplifySqlErrorMessage($e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        } catch (ModelNotFoundException $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return $this->sendError('User not found', [], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return $this->sendError('Error fetching user', [], 500);
        }
    }


    /**
     * Get the current user.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentUser(Request $request)
    {
        try {
            // Try to get the user using JWT
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return $this->sendError('User not found', [], 404);
            }
            return $this->sendResponse(new UserDetailResource($user), 'User retrieved successfully');
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            Log::error('Token expired error: ' . $e->getMessage());
            return $this->sendError('token_expired', [], $e->getStatusCode());
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            Log::error('Token invalid error: ' . $e->getMessage());
            return $this->sendError('token_invalid', [], $e->getStatusCode());
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::error('Error retrieving user: ' . $e->getMessage());
            return $this->sendError('token_absent', [], $e->getStatusCode());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        try{
            $currentUser = JWTAuth::parseToken()->authenticate();

            if ($currentUser->category != 'admin' && $currentUser->user_id != $id) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $user = User::findOrFail($id);

            $validatedData = $request->validate([
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'email' => 'required|string|email|max:100|unique:users,email,' .  $id . ',user_id',
                'password' => 'nullable|string|min:6',
                'profile_image_path' => 'nullable|string',
                'category' => 'nullable|string|max:50',
            ]);
            if (!empty($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            }

            if (!empty($validatedData['profile_image_path']) && !empty($user->profile_image_path)) {
                // delete previous img
                Storage::delete($user->profile_image_path);
            }

            $user->update($validatedData);
            return $this->sendResponse(new UserDetailResource($user), 'User updated successfully');
        } catch (QueryException $e) {
            Log::error('Error Updating user: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        } catch (\Exception $e) {
            Log::error('User update failed: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        try{
            $currentUser = JWTAuth::parseToken()->authenticate();

            if ($currentUser->category != 'admin' && $currentUser->user_id != $id) {
                return $this->sendError('Unauthorized', [], 403);
            }

        $user = User::findOrFail($id);
        $user->delete();

        return $this->sendResponse(null, 'User deleted successfully');
        } catch (QueryException $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        } catch (\Exception $e) {
            Log::error('User deletion failed: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function addFavorite(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $recipeId = $request->input('recipe_id');

            if(!$recipeId) {
                return $this->sendError('Recipe ID is required', [], 400);
            }
            
            // validate whether the recipe is added to favorite
            $isFavorite = UserFavorite::where('user_id', $user->user_id)
                ->where('recipe_id', $recipeId)
                ->exists();

            if ($isFavorite) {
                return $this->sendError('Recipe already added to favorite', [], 400);
            }

            // add to favorite
            $favorite = UserFavorite::create([
                'user_id' => $user->id,
                'recipe_id' => $recipeId
            ]);

            return $this->sendResponse(new UserFavoriteResource($favorite), 'Favorite added successfully');
        } catch (QueryException $e) {
            Log::error('Error adding favorite: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        } catch (\Exception $e) {
            Log::error('Error adding favorite: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function removeFavorite(Request $request, $userId, $recipeId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if ($currentUser->category != 'admin' && $currentUser->user_id != $userId) {
                return $this->sendError('Unauthorized', [], 403);
            }
    
            $favorite = UserFavorite::where('user_id', $userId)
                                    ->where('recipe_id', $recipeId)
                                    ->first();
    
            if (!$favorite) {
                return $this->sendError('Favorite not found', [], 404);
            }
    
            $favorite->delete();
            return $this->sendResponse(null, 'Favorite removed successfully');
        } catch (\Exception $e) {
            Log::error('Error removing favorite: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function listFavorites($userId)
    {
        try {
            $currentUser = JWTAuth::parseToken()->authenticate();
            if ($currentUser->category != 'admin' && $currentUser->user_id != $userId) {
                return $this->sendError('Unauthorized', [], 403);
            }
            $favorites = UserFavorite::with(['user', 'recipe'])
            ->where('user_id', $userId)
            ->get();
    
            return $this->sendResponse(UserFavoriteResource::collection($favorites), 'Favorites fetched successfully');
        } catch (\Exception $e) {
            Log::error('Error listing favorites: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        }
    }

    public function getFavorite($userId, $recipeId)
    {
        try {
            $currentUser = JWTAuth::parseToken()->authenticate();
            
            if ($currentUser->category != 'admin' && $currentUser->user_id != $userId) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $favorite = UserFavorite::where('user_id', $userId)
                                    ->where('recipe_id', $recipeId)
                                    ->first();

            if (!$favorite) {
                return $this->sendError('Favorite not found', [], 404);
            }

            return $this->sendResponse(new UserFavoriteResource($favorite), 'Favorite retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error retrieving favorite: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), [], 500);
        }
    }
}
