<?php

/**
 * This file is part of the SegmentIoBundle project.
 *
 * (c) Vladislav Marin <vladislav.marin92@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 */

namespace Farmatholin\SegmentIoBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * Class SegmentIoExtension
 *
 * @author Vladislav Marin <vladislav.marin92@gmail.com>
 */
class SegmentIoExtension extends Extension implements CompilerPassInterface
{
    /**
     * @see https://segment.com/docs/connections/data-residency/
     */
    private const DATA_RESIDENCIES = [
        'Dublin' => 'in.eu2.segmentapis.com',
        'Singapore' => 'in.ap1.segmentapis.com',
        'Sydney' => 'in.au1.segmentapis.com'
    ];

    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $loader->load('annotations.xml');
        if (PHP_VERSION_ID >= 80000) {
            // PHP Attributes
            $loader->load('attributes.xml');
        }

        if (isset(static::DATA_RESIDENCIES[$config['data_residency']]) && !isset($config['options']['host'])) {
            $config['options']['host'] = static::DATA_RESIDENCIES[$config['data_residency']];
        }

        $container->setParameter('farma.segment_io_write_key', $config['write_key']);
        $container->setParameter('farma.segment_io_sources', $config['sources']??[]);
        $container->setParameter('farma.segment_io_guest_id', $config['guest_id']);
        $container->setParameter('farma.segment_io_env', $config['env']);
        $container->setParameter('farma.segment_io_options', $config['options']);
    }

    public function process(ContainerBuilder $container)
    {
        // Doctrine annotations can be disabled by Symfony Framework or if doctrine/annotations is missing
        try {
            $container->findDefinition('annotation_reader');
        } catch (ServiceNotFoundException $ignored) {
            // Remove Doctrine annotation listener on Kernel request
            $container->getDefinition('farma.segment_io.annotation_listener')
                ->clearTag('kernel.event_listener');
        }
    }
}
