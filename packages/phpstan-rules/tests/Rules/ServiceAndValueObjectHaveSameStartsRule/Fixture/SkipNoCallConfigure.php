<?php

declare(strict_types=1);

namespace Symplify\PHPStanRules\Tests\Rules\ServiceAndValueObjectHaveSameStartsRule\Fixture;

use Rector\Transform\Rector\StaticCall\StaticCallToFuncCallRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(StaticCallToFuncCallRector::class)
        ->call('anotherMethod');
};
