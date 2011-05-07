<?php

namespace Liip\CacheControlBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor,
    Symfony\Component\Config\FileLocator,
    Symfony\Component\HttpKernel\DependencyInjection\Extension,
    Symfony\Component\DependencyInjection\Loader\XmlFileLoader,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Reference,
    Symfony\Component\DependencyInjection\DefinitionDecorator;

class LiipCacheControlExtension extends Extension
{
    /**
     * Loads the services based on your application configuration.
     *
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $loader =  new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('cache_control.xml');

        if (isset($config['rules'])) {
            foreach ($config['rules'] as $cache) {
                $matcher = $this->createRequestMatcher(
                    $container,
                    $cache['path']
                );

                $container->getDefinition($this->getAlias().'.response_listener')
                          ->addMethodCall('add', array($matcher, $cache['controls']));
            }
        }

        if (isset($config['purger'])) {
            $container->setParameter($this->getAlias().'.varnishes', $config['purger']['varnishes']);
            $container->setParameter($this->getAlias().'.domain', $config['purger']['domain']);
            $container->setParameter($this->getAlias().'.port', $config['purger']['port']);
        }
    }

    protected function createRequestMatcher(ContainerBuilder $container, $path = null)
    {
        $serialized = serialize(array($path));
        $id = $this->getAlias().'.request_matcher.'.md5($serialized).sha1($serialized);

        if (!$container->hasDefinition($id)) {
            // only add arguments that are necessary
            $arguments = array($path);
            while (count($arguments) > 0 && !end($arguments)) {
                array_pop($arguments);
            }

            $container
                ->setDefinition($id, new DefinitionDecorator($this->getAlias().'.request_matcher'))
                ->setArguments($arguments)
            ;
        }

        return new Reference($id);
    }
}