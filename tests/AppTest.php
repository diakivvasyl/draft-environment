<?php

declare(strict_types=1);

namespace Lemberg\Tests\Draft\Environment;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\PolicyInterface;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;
use Lemberg\Draft\Environment\App;
use Lemberg\Draft\Environment\Config\Manager\InstallManagerInterface;
use Lemberg\Draft\Environment\Config\Manager\UpdateManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests Draft Environment app.
 *
 * @covers \Lemberg\Draft\Environment\App
 */
final class AppTest extends TestCase {

  /**
   * @var \Composer\Composer
   */
  private $composer;

  /**
   * @var \Composer\IO\IOInterface
   */
  private $io;

  /**
   * @var \Lemberg\Draft\Environment\App
   */
  private $app;

  /**
   * @var \Lemberg\Draft\Environment\Config\Manager\InstallManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private $configInstallManager;

  /**
   * @var \Lemberg\Draft\Environment\Config\Manager\UpdateManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private $configUpdateManager;

  /**
   *
   * @var \Composer\DependencyResolver\PolicyInterface
   */
  private $policy;

  /**
   *
   * @var \Composer\DependencyResolver\Pool
   */
  private $pool;

  /**
   *
   * @var \Composer\DependencyResolver\Request
   */
  private $request;

  /**
   *
   * @var \Composer\Repository\CompositeRepository
   */
  private $installedRepo;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->composer = new Composer();
    $this->composer->setConfig(new ComposerConfig());
    $package = new RootPackage(App::PACKAGE_NAME, '^3.0', '3.0.0.0');
    $this->composer->setPackage($package);
    $manager = $this->getMockBuilder(RepositoryManager::class)
      ->disableOriginalConstructor()
      ->setMethods([
        'getLocalRepository',
        'findPackage',
      ])
      ->getMock();
    $manager->expects(self::any())
      ->method('getLocalRepository')
      ->willReturnSelf();
    $manager->expects(self::any())
      ->method('findPackage')
      ->with(App::PACKAGE_NAME, '*')
      ->willReturn($package);
    $this->composer->setRepositoryManager($manager);
    $this->io = $this->createMock(IOInterface::class);

    // Mock required PackageEvent constructor arguments.
    $this->policy = $this->createMock(PolicyInterface::class);
    $this->pool = $this->createMock(Pool::class);
    $this->request = new Request();
    $this->installedRepo = $this->createMock(CompositeRepository::class);

    $this->configInstallManager = $this->createMock(InstallManagerInterface::class);
    $this->configUpdateManager = $this->createMock(UpdateManagerInterface::class);

    $this->app = new App($this->composer, $this->io, $this->configInstallManager, $this->configUpdateManager);
  }

  /**
   * Tests Composer PackageEvents::PRE_PACKAGE_UNINSTAL event handler.
   */
  public function testComposerPrePackageUninstallEventHandlerDoesNotRunWithOtherPackages(): void {
    // Clean up must not run when any package other than
    // "lemberg/draft-environment" is being uninstalled.
    $package = new Package('dummy', '1.0.0.0', '^1.0');
    $operation = new UninstallOperation($package);
    $event = new PackageEvent(PackageEvents::PRE_PACKAGE_UNINSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $this->configInstallManager
      ->expects(self::never())
      ->method('uninstall');
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer PackageEvents::PRE_PACKAGE_UNINSTAL event handler.
   */
  public function testComposerPrePackageUninstallEventHandlerDoesNotRunWithOtherEvents(): void {
    // Clean up must not run when other than
    // PackageEvents::PRE_PACKAGE_UNINSTALL event is dispatched.
    $package = new Package('dummy', '1.0.0.0', '^1.0');
    $operation = new InstallOperation($package);
    $event = new PackageEvent(PackageEvents::PRE_PACKAGE_INSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $this->configInstallManager
      ->expects(self::never())
      ->method('uninstall');
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer PackageEvents::PRE_PACKAGE_UNINSTAL event handler.
   */
  public function testComposerPrePackageUninstallEventHandlerDoesRun(): void {
    // Clean up must run when "lemberg/draft-environment" is being uninstalled.
    $package = new Package(App::PACKAGE_NAME, '1.0.0.0', '^1.0');
    $operation = new UninstallOperation($package);
    $event = new PackageEvent(PackageEvents::PRE_PACKAGE_UNINSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $this->configInstallManager
      ->expects(self::once())
      ->method('uninstall');
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer PackageEvents::POST_PACKAGE_UPDATE event handler.
   */
  public function testComposerPostPackageUpdateEventHandlerDoesNotRunWithOtherPackages(): void {
    // Update must not run when any package other than
    // "lemberg/draft-environment" is being updated.
    $initial = new Package('dummy', '1.0.0.0', '^1.0');
    $target = new Package('dummy', '1.2.0.0', '^1.0');
    $operation = new UpdateOperation($initial, $target);
    $event = new PackageEvent(PackageEvents::POST_PACKAGE_UPDATE, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $this->configUpdateManager
      ->expects(self::never())
      ->method('update');
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer PackageEvents::POST_PACKAGE_UPDATE event handler.
   */
  public function testComposerPostPackageUpdateEventHandlerDoesNotRunWithOtherEvents(): void {
    // Update must not run when other than
    // PackageEvents::PRE_PACKAGE_UNINSTALL event is dispatched.
    $initial = new Package(App::PACKAGE_NAME, '1.0.0.0', '^1.0');
    $operation = new InstallOperation($initial);
    $event = new PackageEvent(PackageEvents::PRE_PACKAGE_INSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $this->configUpdateManager
      ->expects(self::never())
      ->method('update');
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer PackageEvents::POST_PACKAGE_UPDATE event handler.
   */
  public function testComposerPostPackageUpdateEventHandlerDoesNotRunWhenDowngrading(): void {
    // Update must run when "lemberg/draft-environment" is being updated.
    $initial = new Package(App::PACKAGE_NAME, '1.0.0.0', '^1.0');
    $initial->setReleaseDate(new \DateTime());
    $target = new Package(App::PACKAGE_NAME, '1.2.0.0', '^1.0');
    $target->setReleaseDate(new \DateTime('yesterday'));
    $operation = new UpdateOperation($initial, $target);
    $event = new PackageEvent(PackageEvents::POST_PACKAGE_UPDATE, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $this->configUpdateManager
      ->expects(self::never())
      ->method('update');
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer PackageEvents::POST_PACKAGE_UPDATE event handler.
   */
  public function testComposerPostPackageUpdateEventHandlerDoesRun(): void {
    // Update must run when "lemberg/draft-environment" is being updated.
    $initial = new Package(App::PACKAGE_NAME, '1.0.0.0', '^1.0');
    $target = new Package(App::PACKAGE_NAME, '1.2.0.0', '^1.0');
    $operation = new UpdateOperation($initial, $target);
    $event = new PackageEvent(PackageEvents::POST_PACKAGE_UPDATE, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $this->configUpdateManager
      ->expects(self::once())
      ->method('update');
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer ScriptEvents::POST_INSTALL_CMD and
   * ScriptEvents::POST_UPDATE_CMD event handlers.
   *
   * @dataProvider composerPostInstallOrUpdateCommandEventHandlerDataProvider
   */
  public function testComposerPostInstallOrUpdateCommandEventHandlerDoesNotRunWithEveryEvent(string $scriptEventName): void {
    // Install should not run on every ScriptEvents::POST_INSTALL_CMD or
    // ScriptEvents::POST_UPDATE_CMD event dispatch. Install should only run if
    // the package itself is being installed.
    $this->configInstallManager
      ->expects(self::never())
      ->method('install');

    $event = new ScriptEvent($scriptEventName, $this->composer, $this->io);
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer ScriptEvents::POST_INSTALL_CMD and
   * ScriptEvents::POST_UPDATE_CMD event handlers.
   */
  public function testComposerPostInstallOrUpdateCommandEventHandlerDoesNotRunWithOtherScriptEvents(): void {
    // Install should not run on any other script event even if the package
    // itself is being installed.
    $package = new Package(App::PACKAGE_NAME, '1.0.0.0', '^1.0');
    $operation = new InstallOperation($package);
    $packageEvent = new PackageEvent(PackageEvents::POST_PACKAGE_INSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $event = new ScriptEvent(ScriptEvents::POST_ARCHIVE_CMD, $this->composer, $this->io);

    $this->configInstallManager
      ->expects(self::never())
      ->method('install');

    $this->app->handleEvent($packageEvent);
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer ScriptEvents::POST_INSTALL_CMD and
   * ScriptEvents::POST_UPDATE_CMD event handlers.
   *
   * @dataProvider composerPostInstallOrUpdateCommandEventHandlerDataProvider
   */
  public function testComposerPostInstallOrUpdateCommandEventHandlerDoesNotRunWithOtherPackageEvents(string $scriptEventName): void {
    // Install should not run on any other script event even if the package
    // itself is being installed.
    $package = new Package(App::PACKAGE_NAME, '1.0.0.0', '^1.0');
    $operation = new InstallOperation($package);
    $packageEvent = new PackageEvent(PackageEvents::PRE_PACKAGE_INSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $event = new ScriptEvent($scriptEventName, $this->composer, $this->io);

    $this->configInstallManager
      ->expects(self::never())
      ->method('install');

    $this->app->handleEvent($packageEvent);
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer ScriptEvents::POST_INSTALL_CMD and
   * ScriptEvents::POST_UPDATE_CMD event handlers.
   *
   * @dataProvider composerPostInstallOrUpdateCommandEventHandlerDataProvider
   */
  public function testComposerPostInstallOrUpdateCommandEventHandlerDoesNotRunWithOtherPackages(string $scriptEventName): void {
    // Install should not run if any other package is being installed.
    $package = new Package('dummy', '1.0.0.0', '^1.0');
    $operation = new InstallOperation($package);
    $packageEvent = new PackageEvent(PackageEvents::POST_PACKAGE_INSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $event = new ScriptEvent($scriptEventName, $this->composer, $this->io);

    $this->configInstallManager
      ->expects(self::never())
      ->method('install');

    $this->app->handleEvent($packageEvent);
    $this->app->handleEvent($event);
  }

  /**
   * Tests Composer ScriptEvents::POST_INSTALL_CMD and
   * ScriptEvents::POST_UPDATE_CMD event handlers.
   *
   * @dataProvider composerPostInstallOrUpdateCommandEventHandlerDataProvider
   */
  public function testComposerPostInstallOrUpdateCommandEventHandlerDoesRun(string $scriptEventName): void {
    $package = new Package(App::PACKAGE_NAME, '1.0.0.0', '^1.0');
    $operation = new InstallOperation($package);
    $packageEvent = new PackageEvent(PackageEvents::POST_PACKAGE_INSTALL, $this->composer, $this->io, FALSE, $this->policy, $this->pool, $this->installedRepo, $this->request, [$operation], $operation);
    $event = new ScriptEvent($scriptEventName, $this->composer, $this->io);

    // Install should run.
    $this->configInstallManager
      ->expects(self::once())
      ->method('install');

    // Ensure that install manager has marked all available updates as already
    // applied.
    $this->configUpdateManager
      ->expects(self::once())
      ->method('setLastAppliedUpdateWeight');

    $this->app->handleEvent($packageEvent);
    $this->app->handleEvent($event);
  }

  /**
   * @return array<int,array<int,string>>
   */
  public function composerPostInstallOrUpdateCommandEventHandlerDataProvider(): array {
    return [
      [ScriptEvents::POST_INSTALL_CMD],
      [ScriptEvents::POST_UPDATE_CMD],
    ];
  }

}
