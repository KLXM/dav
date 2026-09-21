<?php

declare(strict_types=1);

namespace KLXM\Dav;

use Sabre\DAV\Collection;
use Sabre\DAVACL\PrincipalCollection;

/**
 * Wurzel des DAV-Baums. Die Knoten der Provider entstehen erst beim ersten Zugriff, also nach der
 * Anmeldung: Dann steht im Kontext, wer angemeldet ist, und nur dessen Provider hängen sich ein.
 *
 * @internal
 */
final class LazyRoot extends Collection
{
    /** @var list<\Sabre\DAV\INode>|null */
    private ?array $children = null;

    public function __construct(
        private readonly Context $context,
        private readonly PrincipalBackend $principals,
    ) {}

    public function getName(): string
    {
        return '';
    }

    public function getChildren(): array
    {
        if (null !== $this->children) {
            return $this->children;
        }

        $children = [new PrincipalCollection($this->principals, PrincipalBackend::PREFIX)];
        $providers = null !== $this->context->user ? Dav::providersFor($this->context->user) : Dav::providers();
        foreach ($providers as $provider) {
            array_push($children, ...$provider->nodes($this->context, $this->principals));
        }

        return $this->children = $children;
    }
}
