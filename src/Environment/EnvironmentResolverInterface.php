<?php

declare(strict_types=1);

namespace Mulla\Environment;

/**
 * decides which API base URL a client talks to.
 */
interface EnvironmentResolverInterface
{
    public function resolveBaseUrl(Environment $environment, ?string $configuredBaseUrl): string;
}
    