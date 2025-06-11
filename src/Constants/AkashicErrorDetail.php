<?php

declare(strict_types=1);

namespace Akashic\Constants;

class AkashicErrorDetail
{
    /** @var array */
    private static $errorDetails = [
        AkashicErrorCode::TEST_NET_OTK_ONBOARDING_FAILED => 'Failed to setup test-otk. Please try again',
        AkashicErrorCode::INCORRECT_PRIVATE_KEY_FORMAT   => 'Private Key is not in correct format',
        AkashicErrorCode::UNKNOWN_ERROR                  => 'Akashic failed with an unknown error. Please try again later',
        AkashicErrorCode::KEY_CREATION_FAILURE           => 'Failed to generate new wallet. Try again.',
        AkashicErrorCode::UNHEALTHY_KEY                  => 'New wallet was not created safely, please re-create',
        AkashicErrorCode::ACCESS_DENIED                  => 'Unauthorized attempt to access production Akashic Link secrets',
        AkashicErrorCode::L2_ADDRESS_NOT_FOUND           => 'L2 Address not found',
        AkashicErrorCode::IS_NOT_BP                      => 'Please sign up on AkashicPay.com first',
        AkashicErrorCode::SAVINGS_EXCEEDED               => 'Transaction amount exceeds total savings',
        AkashicErrorCode::ASSIGNED_KEY_FAILURE           => 'Failed to assign wallet. Please try again',
        AkashicErrorCode::NETWORK_ENVIRONMENT_MISMATCH   => 'The L1-network does not match the SDK-environment'
    ];

    /**
     * Retrieves the error detail message for a given error code.
     */
    public static function get(string $errorCode): ?string
    {
        return self::$errorDetails[$errorCode] ?? null;
    }
}
