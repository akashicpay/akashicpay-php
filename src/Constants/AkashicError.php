<?php

namespace Akashic\Constants;

class AkashicError
{
    public const TEST_NET_OTK_ONBOARDING_FAILED = "Failed to setup test-otk. Please try again";
    public const INCORRECT_PRIVATE_KEY_FORMAT = "Private Key is not in correct format";
    public const TRANSACTION_CANNOT_BE_COMPLETED_IN_ONE = "Transaction could not be completed right now. Try a smaller amount or wait a few minutes";
    public const UNKNOWN_ERROR = "Akashic failed with an unknown error. Please try again later";
    public const KEY_CREATION_FAILURE = "Failed to generate new wallet. Try again.";
    public const UNHEALTHY_KEY = "New wallet was not created safely, please re-create";
    public const ACCESS_DENIED = "Unauthorized attempt to access production Akashic Link secrets";
    public const L2_ADDRESS_NOT_FOUND = "L2 Address not found";
}
