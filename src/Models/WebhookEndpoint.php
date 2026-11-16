<?php

declare(strict_types=1);

namespace Mulla\Models;

/**
 * Your account's webhook configuration (one per merchant).
 * The full $secret is only returned on create and rotateSecret(); otherwise it is masked.
 */
final class WebhookEndpoint extends AbstractModel
{
    public readonly string $id;
    public readonly ?string $url;
    public readonly ?string $secret;
    public readonly ?bool $isActive;
    /** @var list<string> */
    public readonly array $events;
    /**
     * IP addresses Mulla sends webhooks from; useful for firewall allowlists.
     *
     * @var list<string>
     */
    public readonly array $egressIps;
    public readonly ?string $signatureHeader;
    public readonly ?string $signatureAlgorithm;
    public readonly ?\DateTimeImmutable $createdAt;

    protected function hydrate(array $data): void
    {
        $this->id = (string) self::str($data, 'id');
        $this->url = self::str($data, 'url');
        $this->secret = self::str($data, 'secret');
        $this->isActive = self::bool($data, 'is_active');
        $this->events = array_values(array_map('strval', self::arr($data, 'events') ?? []));
        $this->egressIps = array_values(array_map('strval', self::arr($data, 'egress_ips') ?? []));
        $this->signatureHeader = self::str($data, 'signature_header');
        $this->signatureAlgorithm = self::str($data, 'signature_algorithm');
        $this->createdAt = self::date($data, 'created_at');
    }
}
