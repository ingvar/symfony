<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

abstract class FrameworkExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testCsrfProtection()
    {
        $container = $this->createContainerFromFile('full');

        $def = $container->getDefinition('form.type_extension.csrf');

        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
        $this->assertEquals('%form.type_extension.csrf.enabled%', $def->getArgument(0));
        $this->assertEquals('_csrf', $container->getParameter('form.type_extension.csrf.field_name'));
        $this->assertEquals('%form.type_extension.csrf.field_name%', $def->getArgument(1));
        $this->assertEquals('s3cr3t', $container->getParameterBag()->resolveValue($container->findDefinition('form.csrf_provider')->getArgument(1)));
    }

    public function testEsi()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('esi'), '->registerEsiConfiguration() loads esi.xml');
    }

    public function testProfiler()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('profiler'), '->registerProfilerConfiguration() loads profiling.xml');
        $this->assertTrue($container->hasDefinition('data_collector.config'), '->registerProfilerConfiguration() loads collectors.xml');
        $this->assertTrue($container->getParameter('profiler_listener.only_exceptions'));
        $this->assertEquals('%profiler_listener.only_exceptions%', $container->getDefinition('profiler_listener')->getArgument(2));
    }

    public function testRouter()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('router.real'), '->registerRouterConfiguration() loads routing.xml');
        $arguments = $container->getDefinition('router.real')->getArguments();
        $this->assertEquals($container->getParameter('kernel.root_dir').'/config/routing.xml', $container->getParameter('router.resource'), '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('%router.resource%', $arguments[1], '->registerRouterConfiguration() sets routing resource');
        $this->assertEquals('xml', $arguments[2]['resource_type'], '->registerRouterConfiguration() sets routing resource type');
        $this->assertTrue($container->getDefinition('router.cache_warmer')->hasTag('kernel.cache_warmer'), '->registerRouterConfiguration() tags router cache warmer if cache warming is set');
        $this->assertEquals('router.cached', (string) $container->getAlias('router'), '->registerRouterConfiguration() changes router alias to cached if cache warming is set');
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testRouterRequiresResourceOption()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load(array(array('router' => true)), $container);
    }

    public function testSession()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('session'), '->registerSessionConfiguration() loads session.xml');
        $this->assertEquals('fr', $container->getParameter('session.default_locale'));
        $this->assertEquals('%session.default_locale%', $container->getDefinition('session')->getArgument(1));
        $this->assertTrue($container->getDefinition('session')->hasMethodCall('start'));
        $this->assertEquals('session.storage.native', (string) $container->getAlias('session.storage'));

        $options = $container->getParameter('session.storage.options');
        $this->assertEquals('_SYMFONY', $options['name']);
        $this->assertEquals(86400, $options['lifetime']);
        $this->assertEquals('/', $options['path']);
        $this->assertEquals('example.com', $options['domain']);
        $this->assertTrue($options['secure']);
        $this->assertTrue($options['httponly']);
    }

    public function testTemplating()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('templating.name_parser'), '->registerTemplatingConfiguration() loads templating.xml');
        $arguments = $container->getDefinition('templating.helper.assets')->getArguments();
        $this->assertEquals('%templating.helper.assets.assets_version%', $arguments[2]);
        $this->assertEquals('SomeVersionScheme', $container->getParameter('templating.helper.assets.assets_version'));
        $this->assertEquals('%templating.helper.assets.assets_base_urls%', $arguments[1]);
        $this->assertEquals(array('http://cdn.example.com'), $container->getParameter('templating.helper.assets.assets_base_urls'));

        $this->assertTrue($container->getDefinition('templating.cache_warmer.template_paths')->hasTag('kernel.cache_warmer'), '->registerTemplatingConfiguration() tags templating cache warmer if cache warming is set');
        $this->assertEquals('templating.locator.cached', (string) $container->getAlias('templating.locator'), '->registerTemplatingConfiguration() changes templating.locator alias to cached if cache warming is set');

        $this->assertEquals('templating.engine.delegating', (string) $container->getAlias('templating'), '->registerTemplatingConfiguration() configures delegating loader if multiple engines are provided');

        $this->assertEquals($container->getDefinition('templating.loader.chain'), $container->getDefinition('templating.loader.wrapped'), '->registerTemplatingConfiguration() configures loader chain if multiple loaders are provided');

        $this->assertEquals($container->getDefinition('templating.loader'), $container->getDefinition('templating.loader.cache'), '->registerTemplatingConfiguration() configures the loader to use cache');

        $this->assertEquals('%templating.loader.cache.path%', $container->getDefinition('templating.loader.cache')->getArgument(1));
        $this->assertEquals('/path/to/cache', $container->getParameter('templating.loader.cache.path'));

        $this->assertEquals(array('php', 'twig'), $container->getParameter('templating.engines'), '->registerTemplatingConfiguration() sets a templating.engines parameter');
    }

    public function testTranslator()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('translator.real'), '->registerTranslatorConfiguration() loads translation.xml');
        $this->assertSame($container->getDefinition('translator.real'), $container->getDefinition('translator'), '->registerTranslatorConfiguration() redefines translator service from identity to real translator');

        $resources = array();
        foreach ($container->getDefinition('translator.real')->getMethodCalls() as $call) {
            if ('addResource' == $call[0]) {
                $resources[] = $call[1];
            }
        }

        $this->assertContains(
            realpath(__DIR__.'/../../Resources/translations/validators.fr.xliff'),
            array_map(function($resource) use ($resources) { return realpath($resource[1]); }, $resources),
            '->registerTranslatorConfiguration() finds FrameworkExtension translation resources'
        );

        $calls = $container->getDefinition('translator.real')->getMethodCalls();
        $this->assertEquals('fr', $calls[0][1][0]);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testTemplatingRequiresAtLeastOneEngine()
    {
        $container = $this->createContainer();
        $loader = new FrameworkExtension();
        $loader->load(array(array('templating' => null)), $container);
    }

    public function testValidation()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertTrue($container->hasDefinition('validator'), '->registerValidationConfiguration() loads validator.xml');
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.xml_files_loader'), '->registerValidationConfiguration() defines the XML loader');
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.yaml_files_loader'), '->registerValidationConfiguration() defines the YAML loader');

        $xmlFiles = $container->getParameter('validator.mapping.loader.xml_files_loader.mapping_files');
        $this->assertContains(
            realpath(__DIR__.'/../../../../Component/Form/Resources/config/validation.xml'),
            array_map('realpath', $xmlFiles),
            '->registerValidationConfiguration() adds Form validation.xml to XML loader'
        );
    }

    public function testAnnotations()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertEquals($container->getParameter('kernel.cache_dir').'/annotations', $container->getDefinition('annotations.cache.file_cache')->getArgument(0));
        $this->assertEquals('annotations.cached_reader', (string) $container->getAlias('annotation_reader'));
    }

    public function testValidationAnnotations()
    {
        $container = $this->createContainerFromFile('validation_annotations');

        $this->assertTrue($container->hasDefinition('validator.mapping.loader.annotation_loader'), '->registerValidationConfiguration() defines the annotation loader');
        $loaders = $container->getDefinition('validator.mapping.loader.loader_chain')->getArgument(0);
        $found = false;
        foreach ($loaders as $loader) {
            if ('validator.mapping.loader.annotation_loader' === (string) $loader) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'validator.mapping.loader.annotation_loader is added to the loader chain.');
    }

    public function testValidationPaths()
    {
        require_once __DIR__ . "/Fixtures/TestBundle/TestBundle.php";

        $container = $this->createContainerFromFile('validation_annotations', array(
            'kernel.bundles' => array('TestBundle' => 'Symfony\Bundle\FrameworkBundle\Tests\TestBundle'),
        ));

        $yamlArgs = $container->getParameter('validator.mapping.loader.yaml_files_loader.mapping_files');
        $this->assertEquals(1, count($yamlArgs));
        $this->assertStringEndsWith('TestBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'validation.yml', $yamlArgs[0]);

        $xmlArgs = $container->getParameter('validator.mapping.loader.xml_files_loader.mapping_files');
        $this->assertEquals(2, count($xmlArgs));
        $this->assertStringEndsWith('Component/Form/Resources/config/validation.xml', $xmlArgs[0]);
        $this->assertStringEndsWith('TestBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'validation.xml', $xmlArgs[1]);
    }

    protected function createContainer(array $data = array())
    {
        return new ContainerBuilder(new ParameterBag(array_merge(array(
            'kernel.bundles'          => array('FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'),
            'kernel.cache_dir'        => __DIR__,
            'kernel.compiled_classes' => array(),
            'kernel.debug'            => false,
            'kernel.environment'      => 'test',
            'kernel.name'             => 'kernel',
            'kernel.root_dir'         => __DIR__,
        ), $data)));
    }

    protected function createContainerFromFile($file, $data = array())
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new FrameworkExtension());
        $this->loadFromFile($container, $file);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }
}
