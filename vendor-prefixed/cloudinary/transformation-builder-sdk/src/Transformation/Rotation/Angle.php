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

use LDC\Deps\Cloudinary\ArrayUtils;
use LDC\Deps\Cloudinary\Transformation\Argument\AngleTrait;
use LDC\Deps\Cloudinary\Transformation\Argument\Degree;
use LDC\Deps\Cloudinary\Transformation\Argument\RotationMode;
use LDC\Deps\Cloudinary\Transformation\Argument\RotationModeTrait;
use LDC\Deps\Cloudinary\Transformation\Qualifier\BaseQualifier;
/**
 * Class Angle
 */
class Angle extends BaseQualifier implements RotationDegreeInterface, RotationModeInterface
{
    protected const VALUE_CLASS = Degree::class;
    use AngleTrait;
    use RotationModeTrait;
    /**
     * Sets the angle in degrees.
     *
     * @param int|string|array $degree The rotation angle degree.
     */
    public function setAngle(...$degree): void
    {
        $this->setQualifierValue(Degree::byAngle(...$degree));
    }
    /**
     *  Creates a new instance using provided qualifier.
     */
    public static function fromParams(mixed $value): static
    {
        return new static(...ArrayUtils::build($value));
    }
    /**
     * Creates the instance.
     *
     * @param int|array $degree Given degrees or mode.
     *
     *
     * @internal
     */
    public static function createWithDegree(...$degree): static
    {
        return new static(...$degree);
    }
    /**
     * Creates the instance.
     *
     * @param string|RotationMode|array $mode Given mode.
     *
     *
     * @internal
     */
    public static function createWithMode(...$mode): static
    {
        return new static(...$mode);
    }
}
