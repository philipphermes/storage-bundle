<?php

declare(strict_types=1);

namespace PhilippHermes\StorageBundle;

use PhilippHermes\StorageBundle\Client\StorageClient;
use PhilippHermes\StorageBundle\Client\StorageClientInterface;
use PhilippHermes\StorageBundle\Client\StorageConfig;
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
        //Parameters
        $definition
            ->rootNode()
            ->children()
                ->arrayNode('parameters')
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
                        ->booleanNode('alias')
                            ->defaultValue(null)
                        ->end()
                        ->integerNode('database')
                            ->defaultValue(null)
                        ->end()
                        ->booleanNode('async')
                            ->defaultFalse()
                        ->end()
                        ->floatNode('timeout')
                            ->defaultValue(5.0)
                        ->end()
                        ->floatNode('read_write_timeout')
                            ->defaultValue(null)
                        ->end()
                        ->integerNode('weight')
                            ->defaultValue(null)
                        ->end()
                    ->end()
                ->end()
            ->end();

        //Options
        $definition
            ->rootNode()
            ->children()
                ->arrayNode('options')
                    ->children()
                        ->scalarNode('cluster')
                            ->defaultValue(null)
                        ->end()
                        ->scalarNode('replication')
                            ->defaultValue(null)
                        ->end()
                        ->booleanNode('persistent')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('service')
                            ->defaultValue(null)
                        ->end()
                        ->arrayNode('parameters')
                            ->children()
                                ->scalarNode('password')
                                    ->defaultValue(null)
                                ->end()
                                ->integerNode('database')
                                    ->defaultValue(null)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
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
        $builder->setParameter('storage.parameters', $config['parameters']);
        $builder->setParameter('storage.options', $config['options']);

        $builder->register(StorageConfig::class, StorageConfig::class);

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