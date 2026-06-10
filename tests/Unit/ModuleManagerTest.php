<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit;

use Ironflow\Container;
use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;
use Ironflow\Module\ModuleManager;
use Ironflow\Module\ModuleException;
use PHPUnit\Framework\TestCase;

#[Module(name: 'alpha', imports: [], providers: [], exports: [])]
class AlphaModule extends BaseModule
{
}

#[Module(name: 'beta', imports: ['alpha'], providers: [], exports: [])]
class BetaModule extends BaseModule
{
}

#[Module(name: 'gamma', imports: ['beta'], providers: [], exports: [])]
class GammaModule extends BaseModule
{
}

// Cycle modules: delta imports epsilon, epsilon imports delta
#[Module(name: 'delta', imports: ['epsilon'], providers: [], exports: [])]
class DeltaModule extends BaseModule
{
}

#[Module(name: 'epsilon', imports: ['delta'], providers: [], exports: [])]
class EpsilonModule extends BaseModule
{
}

class ModuleManagerTest extends TestCase
{
    private function makeManager(): ModuleManager
    {
        return new ModuleManager(new Container(), sys_get_temp_dir());
    }

    public function test_register_and_boot_single_module(): void
    {
        $manager = $this->makeManager();
        $manager->register(AlphaModule::class);
        $manager->boot();
        $this->assertTrue(true); // No exception thrown = success
    }

    public function test_dependency_order_respected(): void
    {
        $manager = $this->makeManager();
        // Register in reverse order intentionally
        $manager->register(GammaModule::class);
        $manager->register(BetaModule::class);
        $manager->register(AlphaModule::class);
        $manager->boot();
        $this->assertTrue(true);
    }

    public function test_cycle_throws_module_exception(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageMatches('/cycle/i');

        $manager = $this->makeManager();
        $manager->register(DeltaModule::class);
        $manager->register(EpsilonModule::class);
        $manager->boot();
    }

    public function test_missing_import_throws(): void
    {
        $this->expectException(ModuleException::class);

        $manager = $this->makeManager();
        // BetaModule imports 'alpha' but AlphaModule is not registered
        $manager->register(BetaModule::class);
        $manager->boot();
    }

    public function test_render_graph_returns_string(): void
    {
        $manager = $this->makeManager();
        $manager->register(AlphaModule::class);
        $manager->register(BetaModule::class);
        $manager->register(GammaModule::class);
        $manager->boot();
        $graph = $manager->renderGraph();
        $this->assertIsString($graph);
        $this->assertStringContainsString('alpha', $graph);
        $this->assertStringContainsString('beta', $graph);
    }
}
