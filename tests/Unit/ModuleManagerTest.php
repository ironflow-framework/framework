<?php

declare(strict_types=1);

use Ironflow\Container;
use Ironflow\Exceptions\ModuleException;
use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;
use Ironflow\Module\ModuleManager;

// ── Fixtures ──────────────────────────────────────────────────────────────────

#[Module(name: 'alpha', imports: [], providers: [], exports: [])]
class AlphaModule extends BaseModule {}

#[Module(name: 'beta', imports: [AlphaModule::class], providers: [], exports: [])]
class BetaModule extends BaseModule {}

#[Module(name: 'gamma', imports: [BetaModule::class], providers: [], exports: [])]
class GammaModule extends BaseModule {}

// Cycle pair: epsilon → delta → epsilon
#[Module(name: 'epsilon', imports: [DeltaModule::class], providers: [], exports: [])]
class EpsilonModule extends BaseModule {}

#[Module(name: 'delta', imports: [EpsilonModule::class], providers: [], exports: [])]
class DeltaModule extends BaseModule {}

// ── Tests ─────────────────────────────────────────────────────────────────────

function makeManager(): ModuleManager
{
    return new ModuleManager(new Container(), sys_get_temp_dir());
}

test('register and boot single module', function () {
    $manager = makeManager();
    $manager->register(AlphaModule::class);
    $manager->boot();
    expect(true)->toBeTrue();
});

test('dependency boot order respected', function () {
    $manager = makeManager();
    $manager->register(GammaModule::class);
    $manager->register(BetaModule::class);
    $manager->register(AlphaModule::class);
    $manager->boot();

    $order = $manager->getBootOrder();
    $alpha = array_search(AlphaModule::class, $order, true);
    $beta  = array_search(BetaModule::class,  $order, true);
    $gamma = array_search(GammaModule::class,  $order, true);

    expect($alpha)->toBeLessThan($beta);
    expect($beta)->toBeLessThan($gamma);
});

test('cycle throws ModuleException', function () {
    $manager = makeManager();
    $manager->register(DeltaModule::class);
    $manager->register(EpsilonModule::class);

    expect(fn () => $manager->boot())
        ->toThrow(ModuleException::class);
});

test('missing import throws ModuleException', function () {
    $manager = makeManager();
    $manager->register(BetaModule::class); // imports Alpha but Alpha not registered

    expect(fn () => $manager->boot())
        ->toThrow(ModuleException::class);
});

test('renderGraph returns string containing module names', function () {
    $manager = makeManager();
    $manager->register(AlphaModule::class);
    $manager->register(BetaModule::class);
    $manager->register(GammaModule::class);
    $manager->boot();

    $graph = $manager->renderGraph();
    expect($graph)->toBeString();
    expect($graph)->toContain('alpha');
    expect($graph)->toContain('beta');
});
