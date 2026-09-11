<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\RefreshCookieService;
use App\Services\UserService;
use App\Utils\Input;
use App\Utils\Validator;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserService $users,
        private readonly Validator $validator,
        private readonly RefreshCookieService $refreshCookie,
    ) {
    }

    public function login(Request $request): Response
    {
        $data = Input::sanitize($request->body);
        $this->validator->validate($data, [
            'email' => ['required', 'email'],
            'password' => ['required'],
            'realm' => ['required', ['in' => ['client', 'employee']]],
        ]);
        return $this->tokenResponse($this->auth->login(
            strtolower($data['email']),
            $data['password'],
            $data['realm'],
            $request->ipAddress,
            $request->header('user-agent'),
        ));
    }

    public function register(Request $request): Response
    {
        return Response::success($this->users->register($request->body, $this->actor($request)), 201);
    }

    public function enrollTrustedDevice(Request $request): Response
    {
        $data = Input::sanitize($request->body);
        $this->validator->validate($data, [
            'pin' => ['required'],
            'device_name' => ['required', ['max' => 100]],
        ]);
        $this->assertPin((string) $data['pin']);
        return Response::success($this->auth->enrollTrustedDevice(
            $this->actor($request),
            (string) $data['pin'],
            (string) $data['device_name'],
            $request->ipAddress,
            $request->header('user-agent'),
        ), 201);
    }

    public function pinLogin(Request $request): Response
    {
        $data = Input::sanitize($request->body);
        $this->validator->validate($data, [
            'device_id' => ['required'],
            'device_token' => ['required'],
            'pin' => ['required'],
        ]);
        $this->assertPin((string) $data['pin']);
        return $this->tokenResponse($this->auth->pinLogin(
            (string) $data['device_id'],
            (string) $data['device_token'],
            (string) $data['pin'],
            $request->ipAddress,
            $request->header('user-agent'),
        ));
    }

    public function revokeTrustedDevice(Request $request): Response
    {
        $data = Input::sanitize($request->body);
        $this->validator->validate($data, ['device_id' => ['required']]);
        $this->auth->revokeTrustedDevice($this->actor($request), (string) $data['device_id']);
        return Response::success(['message' => 'Trusted device removed.']);
    }

    public function refresh(Request $request): Response
    {
        $refreshToken = $this->refreshCookie->read($request);
        if ($refreshToken === null) {
            throw new \App\Exceptions\AuthenticationException('The refresh session is missing.');
        }
        return $this->tokenResponse($this->auth->refresh(
            $refreshToken,
            $request->ipAddress,
            $request->header('user-agent'),
        ));
    }

    public function logout(Request $request): Response
    {
        $refreshToken = $this->refreshCookie->read($request);
        if ($refreshToken !== null) {
            $this->auth->logout($refreshToken);
        }
        return Response::success(['message' => 'Logged out successfully.'])
            ->withHeader('Set-Cookie', $this->refreshCookie->clear());
    }

    public function forgotPassword(Request $request): Response
    {
        $data = Input::sanitize($request->body);
        $this->validator->validate($data, [
            'email' => ['required', 'email'],
            'realm' => ['required', ['in' => ['client', 'employee']]],
        ]);
        $this->auth->requestPasswordReset(strtolower($data['email']), $data['realm']);
        return Response::success(['message' => 'If the account exists, a reset email has been queued.']);
    }

    public function resetPassword(Request $request): Response
    {
        $data = Input::sanitize($request->body);
        $this->validator->validate($data, [
            'token' => ['required'],
            'password' => ['required', ['min' => 12]],
            'realm' => ['required', ['in' => ['client', 'employee']]],
        ]);
        $this->auth->resetPassword($data['token'], $data['password'], $data['realm']);
        return Response::success(['message' => 'Password updated successfully.']);
    }

    public function changePassword(Request $request): Response
    {
        $data = Input::sanitize($request->body);
        $this->validator->validate($data, [
            'current_password' => ['required'],
            'new_password' => ['required', ['min' => 12]],
        ]);
        $this->auth->changePassword(
            $this->actor($request),
            (string) $data['current_password'],
            (string) $data['new_password'],
        );
        return Response::success(['message' => 'Password changed. Sign in again on this device.']);
    }

    private function tokenResponse(array $tokens): Response
    {
        $refreshToken = (string) $tokens['refresh_token'];
        unset($tokens['refresh_token']);
        return Response::success($tokens)
            ->withHeader('Set-Cookie', $this->refreshCookie->issue($refreshToken));
    }

    private function assertPin(string $pin): void
    {
        if (preg_match('/^\d{6}$/', $pin) !== 1) {
            throw new ValidationException(['pin' => ['PIN must contain exactly 6 digits.']]);
        }
    }
}
