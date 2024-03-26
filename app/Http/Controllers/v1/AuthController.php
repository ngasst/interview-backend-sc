<?php

namespace App\Http\Controllers\v1;

use App\Classes\HubSpotFlow;
use App\Models\Auth0;
use App\Models\Language;
use App\Models\User;
use App\Models\UserInvitation;
use App\Traits\CacheTrait;
use App\Traits\IntercomService;
use App\Traits\IpInfoService;
use App\Traits\PartnerPortalService;
use App\Traits\SegmentService;
use BeyondCode\ServerTiming\Facades\ServerTiming;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as RequestApi;
use GuzzleHttp\Utils;
use Hashids\Hashids;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JetBrains\PhpStorm\ArrayShape;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AuthController extends Controller
{
    use CacheTrait;

    private Request $request;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->middleware('auth:api')->only([
            'userProfile',
        ]);

        $this->request = $request;
    }

    /**
     * Register a User.
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @throws ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        $isFromInvitation = false;
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:user',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[0-9]/',
                'regex:/[!"#$%&\'()*+,-\.\/:;<=>?@[\]^_`{|}~]/',
                'confirmed',
            ],
            'language_id' => 'integer',
        ]);

        $validator->validate();

        // set user language
        $userLanguage = Language::active()->where('id', (int) $request->language_id)->firstOr(function () {
            return Language::find(1); // default: EN
        });

        $user = User::create(array_merge(
            $validator->validated(),
            [
                'hashed_password' => Hash::make($request->password),
                'language_id' => $userLanguage->id,
                'is_active' => true,
                'uuid' => Str::uuid(),
                'role_id' => 1,
                'email_verified_at' => now(),
            ]
        ));

        $token = auth()->login($user);

        return response()->json(array_merge(
            ['message' => 'User successfully registered'],
            $this->createNewToken($token)
        ), 201);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        ServerTiming::start('Auth-Validation');
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        $dataValidated = $validator->validate();
        ServerTiming::stop('Auth-Validation');

        ServerTiming::start('Auth-Attempt');
        $token = auth()->attempt($dataValidated);
        ServerTiming::stop('Auth-Attempt');

        if (!$token) {
            return response()->json(['error' => 'Bad Request'], 400);
        }

        if (!Auth::user()->is_active) {
            auth()->logout();

            return response()->json(['error' => 'User is not active'], 403);
        }

        ServerTiming::start('Auth-CreateNewToken');
        $newToken = $this->createNewToken($token);
        ServerTiming::stop('Auth-CreateNewToken');

        return response()->json($newToken);
    }

    /**
     * Get the token array structure.
     *
     * @param  string  $token
     * @return array
     */
    #[ArrayShape([
        'access_token' => 'string',
        'token_type' => 'string',
        'expires_in' => 'float|int',
        'user' => 'mixed',
    ])]
    protected function createNewToken(string $token): array
    {
        $user = auth()->user();

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user,
        ];
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function userProfile(): JsonResponse
    {
        return response()->json(['user' => auth()->user()]);
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            $new_token = auth()->refresh();
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token has expired and can no longer be refreshed'], 498);
        }

        return response()->json($this->createNewToken($new_token));
    }

    public function test(): JsonResponse
    {
        return response()->json(['message' => 'test']);
    }

    public function debug(): JsonResponse
    {
        $params = $this->request->all();

        $fr_check = ($params["language"] ?? '') === "fr" || ($params["lang"] ?? '') === "fr"; // leaving the "language" key in case this was an upgrade and some client is still relying on this!! TODO: ensure no client is using potentially old "language" key and clean up!

        if ($fr_check) {
            return response()->json([
                'language' => "Français"
            ]);
        }

        return response()->json([
            'language' => "English"
        ]);
    }
}
