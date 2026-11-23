<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Answers a failed reset-link request (unknown email, or the broker's own per-user throttle) with the same
 * success body as a sent link, so the endpoint never reveals whether an account exists.
 */
class NeutralPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => trans(Password::RESET_LINK_SENT)], 200);
    }
}
