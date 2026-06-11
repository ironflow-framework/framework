<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;

#[Module(name: 'beta', imports: [AlphaModule::class], providers: [], exports: [])]
class BetaModule extends BaseModule {}
