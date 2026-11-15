<?php

namespace App\Http\Controllers\Api\V1\Guardian;

use App\Domain\Communication\Contracts\GuardianNotifier;
use App\Domain\Student\Actions\RequestGuardianOtp;
use App\Domain\Student\Actions\VerifyGuardianOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Guardian\RequestGuardianOtpRequest;
use App\Http\Requests\Api\V1\Guardian\VerifyGuardianOtpRequest;
use App\Http\Resources\Api\V1\CurrentUserResource;
use Illuminate\Http\JsonResponse;

/**
 * Guardians sign in with a one-time code sent to the phone on file, and get
 * a bearer token for the mobile app.
 */
class GuardianAuthController extends Controller
{
    public function sendCode(RequestGuardianOtpRequest $request, RequestGuardianOtp $requestGuardianOtp, GuardianNotifier $notifier): JsonResponse
    {
        // In production a code that would never arrive is refused, not promised (D47).
        if (app()->isProduction() && ! $notifier->canSend()) {
            return response()->json([
                'message' => 'Sign-in codes cannot be sent yet: this school has no message service set up. Ask the office to set one up.',
            ], 503);
        }

        $requestGuardianOtp->handle($request->validated('phone'));

        return response()->json([
            'message' => 'If this number is on file, a code has been sent. It is valid for five minutes.',
        ], 202);
    }

    public function verifyCode(VerifyGuardianOtpRequest $request, VerifyGuardianOtp $verifyGuardianOtp): JsonResponse
    {
        $user = $verifyGuardianOtp->handle($request->validated('phone'), $request->validated('code'));
        $token = $user->createToken($request->validated('device_name') ?? 'guardian-app');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new CurrentUserResource($user),
        ], 201);
    }
}
