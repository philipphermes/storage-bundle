<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle\Client;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StorageConfig
{
    /**
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(
        protected ParameterBagInterface $parameterBag,
    )
    {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getParameters(): array
    {
        if (!$this->parameterBag->has('storage.parameters')) {
            return [];
        }

        return $this->parameterBag->get('storage.parameters');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        if (!$this->parameterBag->has('storage.options')) {
            return [];
        }

        return $this->parameterBag->get('storage.options');
    }
}