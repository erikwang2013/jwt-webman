<?php

declare(strict_types=1);

namespace ErikJwt\Hyperf;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class JWT extends AbstractAnnotation
{
}
