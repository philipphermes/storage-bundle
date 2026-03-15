<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle;

use PhilippHermes\StorageBundle\Client\StorageClient;
use PhilippHermes\StorageBundle\Client\StorageClientInterface;
use PhilippHermes\StorageBundle\Command\StorageCleanCommand;
use PhilippHermes\StorageBundle\Command\StorageReadCommand;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class PhilippHermesStorageBundle extends AbstractBundle
{
    protected string $extensionAlias = 'storage';

    /**
     * @inheritDoc
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('schema')
                    ->defaultValue('tcp')
                ->end()
                ->scalarNode('host')
                    ->defaultValue(null)
                ->end()
                ->integerNode('port')
                    ->defaultValue(6379)
                ->end()
                ->scalarNode('path')
                    ->defaultValue(null)
                ->end()
                ->scalarNode('username')
                    ->defaultValue(null)
                ->end()
                ->scalarNode('password')
                    ->defaultValue(null)
                ->end()
                ->booleanNode('persistent')
                    ->defaultFalse()
                ->end()
            ->end();
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('storage.schema', $config['schema']);
        $builder->setParameter('storage.host', $config['host']);
        $builder->setParameter('storage.port', $config['port']);
        $builder->setParameter('storage.path', $config['path']);
        $builder->setParameter('storage.username', $config['username']);
        $builder->setParameter('storage.password', $config['password']);
        $builder->setParameter('storage.persistent', $config['persistent']);

        $builder
            ->register(StorageClientInterface::class, StorageClient::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $builder
            ->register(StorageCleanCommand::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('console.command');

        $builder
            ->register(StorageReadCommand::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('console.command');
    }
}