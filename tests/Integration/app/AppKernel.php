<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Tests\Integration\app;

use Codefog\HasteBundle\CodefogHasteBundle;
use Composer\InstalledVersions;
use Contao\CoreBundle\ContaoCoreBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use Knp\Bundle\TimeBundle\KnpTimeBundle;
use League\FlysystemBundle\FlysystemBundle;
use Markocupic\ContaoTwigAssets\MarkocupicContaoTwigAssets;
use Markocupic\ResourceBookingBundle\MarkocupicResourceBookingBundle;
use Psr\Log\NullLogger;
use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Cmf\Bundle\RoutingBundle\CmfRoutingBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Terminal42\DcawizardBundle\Terminal42DcawizardBundle;
use Terminal42\NotificationCenterBundle\Terminal42NotificationCenterBundle;
use Terminal42\ServiceAnnotationBundle\Terminal42ServiceAnnotationBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use Webauthn\Bundle\WebauthnBundle;

/**
 * Kernel of the integration tests (suite "integration"): the bundles of the test
 * kernel of Contao 5.7 (core-bundle/tests/Functional/app) plus the bundles the
 * resource booking bundle depends on.
 *
 * The cache and the project directory are in the temporary directory of the
 * system, nothing is written into the bundle.
 */
class AppKernel extends Kernel
{
    /**
     * Log channels of Contao (ContaoCoreExtension::prepend()).
     */
    private const CONTAO_LOG_CHANNELS = [
        'contao.access',
        'contao.configuration',
        'contao.cron',
        'contao.email',
        'contao.error',
        'contao.files',
        'contao.forms',
        'contao.general',
    ];

    public function registerBundles(): array
    {
        $bundles = [
            new FrameworkBundle(),
            new SecurityBundle(),
            new TwigBundle(),
        ];

        // Optional, as in the test kernel of Contao (less output, Twig extras)
        if (class_exists(TwigExtraBundle::class)) {
            $bundles[] = new TwigExtraBundle();
        }

        if (class_exists(MonologBundle::class)) {
            $bundles[] = new MonologBundle();
        }

        return [
            ...$bundles,
            new DoctrineBundle(),
            new SchebTwoFactorBundle(),
            new WebauthnBundle(),
            new KnpTimeBundle(),
            new KnpMenuBundle(),
            new CmfRoutingBundle(),
            new Terminal42ServiceAnnotationBundle(),
            new FlysystemBundle(),
            new ContaoCoreBundle(),
            new CodefogHasteBundle(),
            new Terminal42DcawizardBundle(),
            new Terminal42NotificationCenterBundle(),
            new MarkocupicContaoTwigAssets(),
            new MarkocupicResourceBookingBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/rbb-integration-tests';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/config_'.$this->environment.'.yaml');

        // Configuration of the bundle (as in ContaoManager\Plugin::registerContainerConfiguration())
        $loader->load(__DIR__.'/../../../config/config.yaml');

        // Errors (e.g. exceptions caught by AjaxRequestHandler) are written to a file: the tests
        // display them if a request fails (see IntegrationTestCase::sendApiRequest())
        $loader->load(
            static function (ContainerBuilder $container): void {
                if (class_exists(MonologBundle::class)) {
                    $container->loadFromExtension('monolog', [
                        'handlers' => [
                            'integration_tests' => [
                                'type' => 'stream',
                                'path' => self::getErrorLogPath(),
                                'level' => 'error',
                            ],
                        ],
                    ]);
                }
            },
        );

        // Doctrine ORM 3 needs lazy objects. DoctrineBundle 3 enables the native lazy objects of PHP 8.4 by
        // itself. With DoctrineBundle 2 they are enabled here on PHP 8.4 (symfony/var-exporter 8 has no
        // LazyGhost objects anymore), otherwise the LazyGhost objects of symfony/var-exporter are used.
        $loader->load(
            static function (ContainerBuilder $container): void {
                $version = InstalledVersions::isInstalled('doctrine/doctrine-bundle') ? (string) InstalledVersions::getVersion('doctrine/doctrine-bundle') : '';

                if ('' === $version || version_compare($version, '3.0.0', '>=')) {
                    return;
                }

                $orm = \PHP_VERSION_ID >= 80400 && version_compare($version, '2.15.0', '>=')
                    ? ['enable_native_lazy_objects' => true]
                    : ['auto_generate_proxy_classes' => true, 'enable_lazy_ghost_objects' => true];

                $container->loadFromExtension('doctrine', ['orm' => $orm]);
            },
        );
    }

    /**
     * Log file of the errors of the requests (see registerContainerConfiguration()).
     */
    public static function getErrorLogPath(): string
    {
        return sys_get_temp_dir().'/rbb-integration-tests/var/logs/errors.log';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->setParameter('rbb_test.config_dir', __DIR__.'/config');
        $container->register('monolog.logger.contao', NullLogger::class);

        // Without the MonologBundle there are no log channels: the services of Contao still need
        // the loggers of its channels (e.g. monolog.logger.contao.access)
        if (!class_exists(MonologBundle::class)) {
            foreach (self::CONTAO_LOG_CHANNELS as $channel) {
                $container->register('monolog.logger.'.$channel, NullLogger::class);
            }
        }
    }
}
