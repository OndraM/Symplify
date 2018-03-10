<?php declare(strict_types=1);

namespace Symplify\EasyCodingStandard\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symplify\EasyCodingStandard\Configuration\ConflictingCheckerGuard;

final class ConflictingCheckersCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder): void
    {
        $conflictingCheckerGuard = new ConflictingCheckerGuard();
        $conflictingCheckerGuard->processCheckers($containerBuilder->getServiceIds());
    }
}
