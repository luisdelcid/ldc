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

use LDC\Deps\Cloudinary\StringUtils;
/**
 * Class LayerRemoteSource
 */
class RemoteSourceValue extends QualifierMultiValue
{
    /**
     * Serializes to string.
     *
     * @return string
     */
    public function __toString()
    {
        $rawValue = parent::__toString();
        return StringUtils::base64UrlEncode($rawValue);
    }
}
