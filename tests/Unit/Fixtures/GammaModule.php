<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;

#[Module(name: 'gamma', imports: [BetaModule::class], providers: [], exports: [])]
class GammaModule extends BaseModule {}
