<?php

declare(strict_types=1);

namespace App\Preview;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Assets are the one endpoint JWT cannot cover: the browser issues those
 * requests itself from inside the preview iframe, and the token lives in
 * memory rather than in a cookie, so no Authorization header is attached.
 * A short-lived HMAC bound to the project and the path covers them instead.
 * It carries no personal data, so it is safe to put in a URL.
 */
final readonly class AssetUrlSigner
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private int $ttlSeconds = 3600,
    ) {
    }

    public function sign(string $projectId, string $path): string
    {
        $expiresAt = time() + $this->ttlSeconds;

        return $expiresAt.'.'.$this->digest($projectId, $path, $expiresAt);
    }

    public function isValid(string $projectId, string $path, string $token): bool
    {
        $parts = explode('.', $token, 2);

        if (2 !== \count($parts)) {
            return false;
        }

        [$expiresAt, $digest] = $parts;

        if ('' === $expiresAt || !ctype_digit($expiresAt)) {
            return false;
        }

        if ((int) $expiresAt < time()) {
            return false;
        }

        // hash_equals, zeby czas odpowiedzi nie zdradzal, ile znakow sie zgadza.
        return hash_equals($this->digest($projectId, $path, (int) $expiresAt), $digest);
    }

    private function digest(string $projectId, string $path, int $expiresAt): string
    {
        // Length-prefix every field instead of joining with a plain delimiter:
        // a bare '|' can't tell "A|B" + "C" apart from "A" + "B|C", so two
        // different (project, path) pairs could otherwise hash identically.
        $payload = \strlen($projectId).':'.$projectId
            .\strlen($path).':'.$path
            .$expiresAt;

        return hash_hmac('sha256', $payload, $this->secret);
    }
}
