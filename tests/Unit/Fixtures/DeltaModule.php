<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;

#[Module(name: 'delta', imports: [EpsilonModule::class], providers: [], exports: [])]
class DeltaModule extends BaseModule {}
