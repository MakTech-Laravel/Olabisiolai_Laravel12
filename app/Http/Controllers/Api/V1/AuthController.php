<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\PhoneLoginRequestOtpRequest;
use App\Http\Requests\Api\V1\PhoneVerifyOtpRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResendForgotPasswordOtpRequest;
use App\Http\Requests\Api\V1\ResendNewDeviceOtpRequest;
use App\Http\Requests\Api\V1\ResendRegistrationOtpRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\VerifyForgotPasswordTokenRequest;
use App\Http\Requests\Api\V1\VerifyNewDeviceOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Requests\Api\V1\VerifyTwoFactorLoginRequest;
use App\Http\Resources\Api\V1\AdminResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Admin;
use App\Models\AuthOtp;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ReferralService;
use App\Services\TwoFactorAuthenticationService;
use App\Support\LoginRoleCompatibility;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly ReferralService $referralService,
    ) {}

    #[OA\Post(
        path: '/v1/auth/register',
        summary: 'Register a new user or vendor account',
        description: 'Creates an unverified account and sends an OTP to the chosen verification channel (email or phone). '
            .'The account must be verified via POST /v1/auth/otp/verify before it can log in.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'verification_channel', 'role', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', maxLength: 120, example: 'Ada'),
                    new OA\Property(property: 'last_name', type: 'string', maxLength: 120, example: 'Obi'),
                    new OA\Property(property: 'verification_channel', type: 'string', enum: ['email', 'phone']),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, description: 'Required if verification_channel is email; must be omitted/null if verification_channel is phone.'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, description: 'Nigerian phone number; required if verification_channel is phone; must be omitted/null if verification_channel is email.'),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor']),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                    new OA\Property(property: 'wants_marketing_emails', type: 'boolean', nullable: true),
                    new OA\Property(property: 'ref', type: 'string', maxLength: 32, nullable: true, description: 'Referral code.'),
                ],
                example: [
                    'first_name' => 'Ada',
                    'last_name' => 'Obi',
                    'verification_channel' => 'email',
                    'email' => 'ada@example.com',
                    'phone' => null,
                    'role' => 'user',
                    'password' => 'Passw0rd123',
                    'password_confirmation' => 'Passw0rd123',
                    'wants_marketing_emails' => true,
                    'ref' => null,
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registration successful, OTP sent',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'verification_status', type: 'string', example: 'unverified'),
                        new OA\Property(property: 'verification_channel', type: 'string', enum: ['email', 'phone']),
                        new OA\Property(property: 'otp', type: 'string', description: 'Present in non-production environments only.'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function register(RegisterRequest $request)
    {
        try {
            ['user' => $user, 'otp' => $otp, 'verification_channel' => $channel] = $this->authService->register($request->validated());

            $this->referralService->attachReferralOnRegister($user, $request->input('ref'));

            return sendResponse(true, 'Registration successful. Verify OTP to activate your account.', [
                'verification_status' => 'unverified',
                'verification_channel' => $channel,
                'otp' => $otp->code,
                'user' => UserResource::make($user),
            ], Response::HTTP_CREATED);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/forgot-password/verify-token',
        summary: 'Check whether a password reset token is still valid',
        description: 'Optional pre-check before showing the OTP + new-password form.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'code', type: 'string', nullable: true),
                    new OA\Property(property: 'token', type: 'string', minLength: 64, maxLength: 64),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor', 'admin'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token is valid', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Invalid or expired token', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verifyForgotPasswordToken(VerifyForgotPasswordTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $isValid = $this->authService->verifyForgotPasswordToken(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['token'],
                $validated['role'] ?? null,
            );

            if (! $isValid) {
                return sendResponse(false, 'Invalid or expired reset token. Please request a new reset OTP.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'Reset token is valid. You can proceed to OTP verification and password reset.');
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/otp/verify',
        summary: 'Verify a registration OTP and log in',
        description: 'Confirms the OTP sent during registration, activates the account, and (for a fresh '
            .'registration) returns an access token so the client is logged in immediately.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', pattern: '^[0-9]{6}$', example: '482913'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'device_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'device_name', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP verified, account activated',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'verification_status', type: 'string', example: 'verified'),
                        new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken', nullable: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Invalid or expired OTP / validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verifyOtp(VerifyOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $authUser = $request->user('api');
            $result = $this->authService->verifyOtp(
                (string) $validated['code'],
                isset($validated['phone']) ? (string) $validated['phone'] : null,
                $authUser instanceof User ? $authUser : null,
                isset($validated['email']) ? (string) $validated['email'] : null,
            );

            if (! $result) {
                return sendResponse(false, 'Invalid or expired OTP.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $verifiedUser = $result['user'] ?? null;

            if ($verifiedUser instanceof User) {
                $this->authService->rememberTrustedDevice(
                    $verifiedUser,
                    isset($validated['device_id']) ? (string) $validated['device_id'] : null,
                    isset($validated['device_name']) ? (string) $validated['device_name'] : null,
                );
            }

            return sendResponse(true, 'OTP verified successfully. You are logged in.', [
                'verification_status' => 'verified',
                'token' => $result['token'] ?? null,
                'user' => $verifiedUser instanceof User
                    ? UserResource::make($verifiedUser)
                    : ($verifiedUser instanceof Admin ? AdminResource::make($verifiedUser) : null),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/phone/request-otp',
        summary: 'Request an OTP to log in by phone number',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', minLength: 10, maxLength: 20, example: '08012345678'),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP sent (or generated even if SMS delivery failed)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'masked_phone', type: 'string', example: '080****5678'),
                        new OA\Property(property: 'sms_delivered', type: 'boolean'),
                        new OA\Property(property: 'otp', type: 'string', description: 'Present in non-production environments only.'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function requestPhoneLoginOtp(PhoneLoginRequestOtpRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->authService->requestPhoneLoginOtp(
                $validated['phone'],
                $validated['role'] ?? null,
            );

            $message = ($result['sms_delivered'] ?? true)
                ? 'OTP sent successfully to your phone number.'
                : 'OTP generated. SMS could not be delivered — verify your Termii sender ID or check Laravel logs in local dev.';

            return sendResponse(true, $message, [
                'masked_phone' => $result['masked_phone'],
                'sms_delivered' => $result['sms_delivered'] ?? true,
                'otp' => $result['otp']->code,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/phone/verify-otp',
        summary: 'Verify a phone login OTP and log in',
        description: 'Returns an access token directly, or a two-factor challenge if 2FA is enabled on the account.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'code'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', minLength: 10, maxLength: 20),
                    new OA\Property(property: 'code', type: 'string', pattern: '^[0-9]{6}$'),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful, or two-factor challenge issued',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken', nullable: true),
                        new OA\Property(property: 'verification_status', type: 'string', example: 'verified'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User', nullable: true),
                        new OA\Property(property: 'two_factor_required', type: 'boolean', nullable: true),
                        new OA\Property(property: 'two_factor_token', type: 'string', nullable: true),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Invalid OTP / validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verifyPhoneLoginOtp(PhoneVerifyOtpRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->authService->verifyPhoneLoginOtp(
                $validated['phone'],
                $validated['code'],
                $validated['role'] ?? null,
            );

            if ($result['two_factor_required']) {
                return sendResponse(true, 'Two-factor authentication required.', $this->twoFactorLoginPayload($result));
            }

            $this->authService->rememberTrustedDevice(
                $result['user'],
                isset($validated['device_id']) ? (string) $validated['device_id'] : null,
                isset($validated['device_name']) ? (string) $validated['device_name'] : null,
            );

            return sendResponse(true, 'Login successful.', [
                'token' => $result['token'],
                'verification_status' => 'verified',
                'user' => UserResource::make($result['user']),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/phone/resend-otp',
        summary: 'Resend the phone login OTP',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', minLength: 10, maxLength: 20),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OTP resent', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resendPhoneLoginOtp(PhoneLoginRequestOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendPhoneLoginOtp(
                $validated['phone'],
                $validated['role'] ?? null,
            );

            return sendResponse(true, 'A new OTP has been sent to your phone number.', [
                'otp' => $otp->code,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/login',
        summary: 'Log in with email/phone and password',
        description: 'Issues a Passport personal access token on success. Depending on account state, the '
            .'response may instead ask for account verification, new-device verification, or two-factor '
            .'authentication before a token is issued.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, description: 'Required if phone is omitted.'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, description: 'Nigerian phone number; required if email is omitted.', example: '08012345678'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor'], nullable: true),
                    new OA\Property(property: 'device_id', type: 'string', format: 'uuid', nullable: true, description: 'Identifies this device for the trusted-device / new-device-verification flow.'),
                    new OA\Property(property: 'device_name', type: 'string', nullable: true, example: 'iPhone 15 Pro'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful, or a verification/2FA/device challenge was issued instead of a token',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Login successful.'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken', nullable: true, description: 'Present only when no further verification is required.'),
                        new OA\Property(property: 'verification_status', type: 'string', enum: ['verified', 'unverified', 'device_verification_required', 'two_factor_required']),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User', nullable: true),
                        new OA\Property(property: 'device_verification_required', type: 'boolean', nullable: true),
                        new OA\Property(property: 'device_verification_token', type: 'string', nullable: true),
                        new OA\Property(property: 'two_factor_required', type: 'boolean', nullable: true),
                        new OA\Property(property: 'two_factor_token', type: 'string', nullable: true),
                        new OA\Property(property: 'verification_channel', type: 'string', enum: ['email', 'phone'], nullable: true),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 403,
                description: 'Account unverified, role mismatch, or admin attempting user login',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function login(LoginRequest $request)
    {
        try {
            $validated = $request->validated();

            try {
                $user = $this->authService->resolveLoginUserByCredentials(
                    $validated['email'] ?? null,
                    $validated['phone'] ?? null,
                    $validated['password'],
                );
            } catch (\Exception $e) {
                return sendResponse(false, $e->getMessage(), null, Response::HTTP_UNAUTHORIZED);
            }

            if ($user->role === 'admin') {
                return sendResponse(false, 'Admins must use the admin login URL.', null, Response::HTTP_FORBIDDEN);
            }

            if (isset($validated['role']) && ! LoginRoleCompatibility::matches($validated['role'], $user->role)) {
                return sendResponse(false, "This account is registered as a {$user->role}. Please log in with role: {$user->role}.", null, Response::HTTP_FORBIDDEN);
            }

            if (! $user->isAccountVerified()) {
                $channel = $user->registrationVerificationChannel()
                    ?? ($user->phone && ! $user->email ? 'phone' : 'email');
                $destination = $channel === 'phone' ? 'phone number' : 'email';

                return sendResponse(
                    false,
                    "Please verify your account to continue. Use the signup verification code sent to your {$destination}, or request a new code on the verification page.",
                    [
                        'verification_status' => 'unverified',
                        'verification_required' => true,
                        'verification_channel' => $channel,
                        'user' => UserResource::make($user),
                    ],
                    Response::HTTP_FORBIDDEN,
                );
            }

            $deviceId = isset($validated['device_id']) ? (string) $validated['device_id'] : null;
            $deviceName = isset($validated['device_name']) ? (string) $validated['device_name'] : null;

            if (! $this->authService->isTrustedDevice($user, $deviceId)) {
                $verificationChannel = filled($validated['phone']) ? 'phone' : 'email';
                $challenge = $this->authService->initiateNewDeviceLogin(
                    $user,
                    (string) $deviceId,
                    $deviceName,
                    $validated['role'] ?? null,
                    $verificationChannel,
                );

                return sendResponse(true, 'Please verify this device to continue.', [
                    'device_verification_required' => true,
                    'device_verification_token' => $challenge['token'],
                    'verification_channel' => $challenge['channel'],
                    'verification_status' => 'device_verification_required',
                    'masked_email' => $challenge['masked_email'],
                    'masked_phone' => $challenge['masked_phone'],
                    'otp' => $challenge['otp']->code,
                    'user' => UserResource::make($user),
                ]);
            }

            if ($this->twoFactor->isEnabled($user)) {
                $challenge = $this->authService->initiateTwoFactorLogin(
                    $user,
                    $validated['role'] ?? null,
                );

                return sendResponse(true, 'Two-factor authentication required.', $this->twoFactorLoginPayload($challenge));
            }

            $token = $this->authService->issueAccessToken($user);
            $this->authService->touchTrustedDevice($user, $deviceId);

            return sendResponse(true, 'Login successful.', [
                'token' => $token,
                'verification_status' => 'verified',
                'user' => UserResource::make($user),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/two-factor/verify',
        summary: 'Complete a two-factor login challenge',
        description: 'Consumes the two_factor_token issued by /v1/auth/login (or the phone/device login flows) and returns an access token.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['two_factor_token', 'code'],
                properties: [
                    new OA\Property(property: 'two_factor_token', type: 'string', minLength: 32, maxLength: 128),
                    new OA\Property(property: 'code', type: 'string', maxLength: 32),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor'], nullable: true),
                    new OA\Property(property: 'device_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'device_name', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken'),
                        new OA\Property(property: 'verification_status', type: 'string', example: 'verified'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 403, description: 'Role mismatch or admin using the wrong endpoint', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Invalid/expired code or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verifyTwoFactorLogin(VerifyTwoFactorLoginRequest $request)
    {
        try {
            $validated = $request->validated();

            $user = $this->authService->completeTwoFactorLogin(
                $validated['two_factor_token'],
                $validated['code'],
            );

            if ($user->role === 'admin') {
                return sendResponse(false, 'Admins must use the admin login URL.', null, Response::HTTP_FORBIDDEN);
            }

            if (isset($validated['role']) && ! LoginRoleCompatibility::matches($validated['role'], $user->role)) {
                return sendResponse(
                    false,
                    "This account is registered as a {$user->role}. Please log in with role: {$user->role}.",
                    null,
                    Response::HTTP_FORBIDDEN,
                );
            }

            $token = $this->authService->issueAccessToken($user);
            $this->authService->rememberTrustedDevice(
                $user,
                isset($validated['device_id']) ? (string) $validated['device_id'] : null,
                isset($validated['device_name']) ? (string) $validated['device_name'] : null,
            );

            return sendResponse(true, 'Login successful.', [
                'token' => $token,
                'verification_status' => 'verified',
                'user' => UserResource::make($user),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/device/verify-otp',
        summary: 'Verify a new-device login challenge',
        description: 'Consumes the device_verification_token issued by /v1/auth/login when logging in from an untrusted device.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['device_verification_token', 'code'],
                properties: [
                    new OA\Property(property: 'device_verification_token', type: 'string', minLength: 32, maxLength: 128),
                    new OA\Property(property: 'code', type: 'string', pattern: '^[0-9]{6}$'),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful, or a two-factor challenge was issued',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken', nullable: true),
                        new OA\Property(property: 'verification_status', type: 'string'),
                        new OA\Property(property: 'two_factor_required', type: 'boolean', nullable: true),
                        new OA\Property(property: 'two_factor_token', type: 'string', nullable: true),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 403, description: 'Role mismatch or admin using the wrong endpoint', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Invalid/expired code or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verifyNewDeviceLogin(VerifyNewDeviceOtpRequest $request)
    {
        try {
            $validated = $request->validated();

            $result = $this->authService->completeNewDeviceLogin(
                $validated['device_verification_token'],
                $validated['code'],
                $validated['role'] ?? null,
            );

            $user = $result['user'];

            if ($user->role === 'admin') {
                return sendResponse(false, 'Admins must use the admin login URL.', null, Response::HTTP_FORBIDDEN);
            }

            if (isset($validated['role']) && ! LoginRoleCompatibility::matches($validated['role'], $user->role)) {
                return sendResponse(
                    false,
                    "This account is registered as a {$user->role}. Please log in with role: {$user->role}.",
                    null,
                    Response::HTTP_FORBIDDEN,
                );
            }

            if ($result['two_factor_required']) {
                return sendResponse(true, 'Two-factor authentication required.', $this->twoFactorLoginPayload($result));
            }

            return sendResponse(true, 'Login successful.', [
                'token' => $result['token'],
                'verification_status' => 'verified',
                'user' => UserResource::make($user),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/two-factor/resend-otp',
        summary: 'Resend a two-factor login OTP',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['two_factor_token'],
                properties: [
                    new OA\Property(property: 'two_factor_token', type: 'string', minLength: 32, maxLength: 128),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'A new verification code was sent',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'verification_channel', type: 'string', enum: ['email', 'phone']),
                        new OA\Property(property: 'masked_email', type: 'string', nullable: true),
                        new OA\Property(property: 'masked_phone', type: 'string', nullable: true),
                        new OA\Property(property: 'otp', type: 'string', nullable: true, description: 'Present in non-production environments only.'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Invalid/expired token or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resendTwoFactorLoginOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'two_factor_token' => ['required', 'string', 'min:32', 'max:128'],
            ]);

            $delivery = $this->authService->resendTwoFactorLoginOtp($validated['two_factor_token']);

            return sendResponse(true, 'A new verification code has been sent.', [
                'verification_channel' => $delivery['verification_channel'],
                'masked_email' => $delivery['masked_email'],
                'masked_phone' => $delivery['masked_phone'],
                'otp' => $delivery['otp']?->code,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/device/resend-otp',
        summary: 'Resend a new-device login OTP',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['device_verification_token'],
                properties: [
                    new OA\Property(property: 'device_verification_token', type: 'string', minLength: 32, maxLength: 128),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'A new verification code was sent',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'otp', type: 'string', description: 'Present in non-production environments only.'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Invalid/expired token or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resendNewDeviceLoginOtp(ResendNewDeviceOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendNewDeviceLoginOtp($validated['device_verification_token']);

            return sendResponse(true, 'A new verification code has been sent.', [
                'otp' => $otp->code,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/admin/two-factor/verify',
        summary: 'Complete an admin two-factor login challenge',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['two_factor_token', 'code'],
                properties: [
                    new OA\Property(property: 'two_factor_token', type: 'string', minLength: 32, maxLength: 128),
                    new OA\Property(property: 'code', type: 'string', maxLength: 32),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin login successful',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken'),
                        new OA\Property(property: 'verification_status', type: 'string', example: 'verified'),
                        new OA\Property(property: 'admin', ref: '#/components/schemas/Admin'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Invalid/expired code or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verifyAdminTwoFactorLogin(VerifyTwoFactorLoginRequest $request)
    {
        try {
            $validated = $request->validated();

            $admin = $this->authService->completeAdminTwoFactorLogin(
                $validated['two_factor_token'],
                $validated['code'],
            );

            $token = $this->authService->issueAdminAccessToken($admin);

            return sendResponse(true, 'Admin login successful.', [
                'token' => $token,
                'verification_status' => 'verified',
                'admin' => AdminResource::make($admin),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/admin/login',
        summary: 'Log in as an admin',
        description: 'Alternate admin login path within the Auth controller. See also POST /v1/admin/login on the Admin controller.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin login successful, or a two-factor challenge was issued',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'token', ref: '#/components/schemas/AccessToken', nullable: true),
                        new OA\Property(property: 'verification_status', type: 'string'),
                        new OA\Property(property: 'admin', ref: '#/components/schemas/Admin', nullable: true),
                        new OA\Property(property: 'two_factor_required', type: 'boolean', nullable: true),
                        new OA\Property(property: 'two_factor_token', type: 'string', nullable: true),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Admin account not yet email-verified', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function adminLogin(LoginRequest $request)
    {
        try {
            $validated = $request->validated();
            try {
                $admin = $this->authService->resolveAdminLoginUser($validated['email'], $validated['password']);
            } catch (\Exception $e) {
                return sendResponse(false, $e->getMessage(), null, Response::HTTP_UNAUTHORIZED);
            }
            if (! $admin->email_verified_at) {
                return sendResponse(false, 'Please verify your account via OTP before logging in.', [
                    'verification_status' => 'unverified',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($this->twoFactor->isEnabled($admin)) {
                $challenge = $this->authService->initiateAdminTwoFactorLogin($admin);

                return sendResponse(true, 'Two-factor authentication required.', $this->twoFactorLoginPayload($challenge));
            }

            $token = $this->authService->issueAdminAccessToken($admin);

            return sendResponse(true, 'Admin login successful.', [
                'token' => $token,
                'verification_status' => 'verified',
                'admin' => AdminResource::make($admin),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/forgot-password',
        summary: 'Start a password reset',
        description: 'Sends an OTP and a reset token to the given email or phone number.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, description: 'Required if phone is omitted.'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, description: 'Nigerian phone number; required if email is omitted.'),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor', 'admin'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reset OTP sent',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'Otp', type: 'string', description: 'Present in non-production environments only.'),
                        new OA\Property(property: 'Token', type: 'string', description: 'Opaque reset token, required by the verify/reset-password steps.'),
                        new OA\Property(property: 'verification_channel', type: 'string', enum: ['email', 'phone']),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 404, description: 'No account found for the given contact', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            $validated = $request->validated();
            $result = $this->authService->forgotPassword(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['role'] ?? null,
            );

            if (! isset($result)) {
                $message = filled($validated['phone'] ?? null)
                    ? 'No account was found with this phone number.'
                    : 'No account was found with this email address.';

                return sendResponse(false, $message, null, Response::HTTP_NOT_FOUND);
            }

            $deliveryMessage = ($result['verification_channel'] ?? 'email') === 'phone'
                ? 'Reset OTP sent successfully to your phone number.'
                : 'Reset OTP sent successfully to your email address.';

            return sendResponse(true, $deliveryMessage, [
                'Otp' => ! isset($result) ? null : $result['otp']->code,
                'Token' => ! isset($result) ? null : $result['token'],
                'verification_channel' => $result['verification_channel'] ?? 'email',
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/forgot-password/resend-otp',
        summary: 'Resend a password reset OTP',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'token', type: 'string', minLength: 64, maxLength: 64),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor', 'admin'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'A new OTP was sent',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'Otp', type: 'string', description: 'Present in non-production environments only.'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Invalid/expired reset token or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resendForgotPasswordOtp(ResendForgotPasswordOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendForgotPasswordOtp(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['token'],
                $validated['role'] ?? null,
            );

            if (! $otp) {
                return sendResponse(false, 'Invalid or expired reset token. Please request a new password reset.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $message = filled($validated['phone'] ?? null)
                ? 'A new OTP has been sent to your phone number.'
                : 'A new OTP has been sent to your email address.';

            return sendResponse(true, $message, [
                'Otp' => $otp->code,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/forgot-password/verify-otp',
        summary: 'Verify a password reset OTP',
        description: 'Confirms the OTP + token pair before allowing POST /v1/auth/reset-password.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'code', type: 'string', pattern: '^[0-9]{6}$', nullable: true),
                    new OA\Property(property: 'token', type: 'string', minLength: 64, maxLength: 64),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor', 'admin'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'OTP verified', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Invalid/expired OTP or token, or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verifyForgotPasswordOtp(VerifyForgotPasswordTokenRequest $request)
    {
        try {
            $validated = $request->validated();
            $isValid = $this->authService->verifyForgotPasswordOtp(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['code'],
                $validated['token'],
                $validated['role'] ?? null,
            );

            if (! $isValid) {
                return sendResponse(false, 'Invalid or expired OTP/token combination. Please request a new reset OTP.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'OTP verified successfully. You can now reset your password.');
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/reset-password',
        summary: 'Reset the account password',
        description: 'Final step of the forgot-password flow; consumes the reset token.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'token', type: 'string', minLength: 64, maxLength: 64),
                    new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor', 'admin'], nullable: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, description: 'Must contain an uppercase letter, a lowercase letter, a digit, and a symbol.'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
                example: [
                    'email' => 'ada@example.com',
                    'phone' => null,
                    'token' => 'a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4',
                    'role' => 'user',
                    'password' => 'Passw0rd!23',
                    'password_confirmation' => 'Passw0rd!23',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset successful', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Invalid/expired reset token or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $validated = $request->validated();
            if (! $this->authService->resetPasswordByEmail(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
                $validated['password'],
                $validated['token'],
                $validated['role'] ?? null,
            )) {
                return sendResponse(false, 'Unable to reset password. The reset token is invalid, expired, or does not match your account.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return sendResponse(true, 'Password reset successful. You can now log in with your new password.');
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/register/resend-otp',
        summary: 'Resend the registration OTP',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, description: 'Required if phone is omitted.'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, description: 'Required if email is omitted.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP resent',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'verification_status', type: 'string', example: 'unverified'),
                        new OA\Property(property: 'otp', type: 'string', description: 'Present in non-production environments only.'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 422, description: 'Could not resend / validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resendRegistrationOtp(ResendRegistrationOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $otp = $this->authService->resendRegistrationOtpForContact(
                isset($validated['email']) ? (string) $validated['email'] : null,
                isset($validated['phone']) ? (string) $validated['phone'] : null,
            );

            if (! $otp) {
                return sendResponse(
                    false,
                    'We could not resend a verification code for this account. Check your details or register again.',
                    null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            return sendResponse(true, 'OTP resent successfully.', [
                'verification_status' => 'unverified',
                'otp' => $otp->code,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/otp/resend',
        summary: 'Resend the current (authenticated) account\'s verification OTP',
        tags: ['Auth'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP resent, or account already verified',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'verification_status', type: 'string', enum: ['unverified', 'verified']),
                        new OA\Property(property: 'Otp', type: 'string', nullable: true, description: 'Present in non-production environments only.'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function resendOtp(Request $request)
    {
        try {
            $user = $request->user('api') ?? $request->user('admin_api');

            if (! $user instanceof User && ! $user instanceof Admin) {
                return sendResponse(false, 'Authentication is required to resend OTP.', null, Response::HTTP_UNAUTHORIZED);
            }

            if ($user->isAccountVerified()) {
                return sendResponse(true, 'Account already verified.', [
                    'verification_status' => 'verified',
                ]);
            }

            $otp = $this->authService->resendOtp($user);

            return sendResponse(true, 'OTP resent successfully.', [
                'verification_status' => 'unverified',
                'Otp' => $otp->code,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Post(
        path: '/v1/auth/logout',
        summary: 'Log out and revoke all access tokens for the current account',
        description: 'Revokes every Passport token belonging to the authenticated user or admin (not just the current one).',
        tags: ['Auth'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'User logged out successfully.'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'vendor', 'user', 'guest']),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function logout(Request $request)
    {
        try {
            $user = $request->user('api') ?? $request->user('admin_api');

            if ($user instanceof User || $user instanceof Admin) {
                $user->tokens()->delete();
            }

            $role = $user instanceof Admin ? 'admin' : ($user?->role ?? 'guest');
            $messages = [
                'admin' => 'Admin logged out successfully.',
                'vendor' => 'Vendor logged out successfully.',
                'user' => 'User logged out successfully.',
            ];

            $message = $messages[$role] ?? 'Logged out successfully.';

            return sendResponse(true, $message, [
                'role' => $role,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[OA\Get(
        path: '/v1/auth/profile',
        summary: 'Get the authenticated user\'s or admin\'s profile',
        tags: ['Auth'],
        security: [['passport' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(
                        property: 'data',
                        oneOf: [
                            new OA\Schema(ref: '#/components/schemas/User'),
                            new OA\Schema(ref: '#/components/schemas/Admin'),
                        ],
                    ),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function profile(Request $request)
    {
        try {
            // Prefer admin guard so a Passport token never resolves to the wrong model if IDs overlap.
            $authUser = $request->user('admin_api') ?? $request->user('api');

            if ($authUser instanceof Admin) {
                return sendResponse(true, 'Profile retrieved successfully.', AdminResource::make($authUser));
            }

            return sendResponse(true, 'Profile retrieved successfully.', UserResource::make($authUser));
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param  array<string, mixed>  $challenge
     * @return array<string, mixed>
     */
    private function twoFactorLoginPayload(array $challenge): array
    {
        $token = $challenge['token'] ?? $challenge['two_factor_token'] ?? '';
        $channel = $challenge['verification_channel'] ?? $challenge['two_factor_channel'] ?? 'email';
        $maskedEmail = $challenge['masked_email'] ?? $challenge['two_factor_masked_email'] ?? null;
        $maskedPhone = $challenge['masked_phone'] ?? $challenge['two_factor_masked_phone'] ?? null;
        $otp = $challenge['otp'] ?? $challenge['two_factor_otp'] ?? null;

        return [
            'two_factor_required' => true,
            'two_factor_token' => $token,
            'verification_status' => 'two_factor_required',
            'verification_channel' => $channel,
            'masked_email' => $maskedEmail,
            'masked_phone' => $maskedPhone,
            'otp' => $otp instanceof AuthOtp ? $otp->code : null,
        ];
    }
}
