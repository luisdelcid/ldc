<?php

/**
 * This file is part of the Cloudinary PHP package.
 *
 * (c) Cloudinary
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace LDC\Deps\Cloudinary\Transformation;

use LDC\Deps\Cloudinary\ClassUtils;
use LDC\Deps\Cloudinary\Transformation\Argument\ColorValue;
use LDC\Deps\Cloudinary\Transformation\Argument\ColorTrait as QualifierColorTrait;
# Fix PHP5.6 conflict
use LDC\Deps\Cloudinary\Transformation\Qualifier\BaseQualifier;
use LDC\Deps\Cloudinary\Transformation\Qualifier\Value\ColorValueTrait;
/**
 * Class Color
 */
class ColorQualifier extends BaseQualifier
{
    use QualifierColorTrait;
    use ColorValueTrait;
    /**
     * @var string $key Serialization key.
     */
    protected static string $key = 'co';
    /**
     * Color constructor.
     *
     */
    public function __construct($color)
    {
        parent::__construct(ClassUtils::verifyInstance($color, ColorValue::class));
    }
}
