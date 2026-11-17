<?php

/**
 * This file is part of the Cloudinary PHP package.
 *
 * (c) Cloudinary
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace LDC\Deps\Cloudinary\Asset;

use LDC\Deps\Cloudinary\Transformation\CommonTransformation;
use LDC\Deps\Cloudinary\Transformation\ImageTransformation;
use LDC\Deps\Cloudinary\Transformation\ImageTransformationInterface;
use LDC\Deps\Cloudinary\Transformation\ImageTransformationTrait;
/**
 * Class Image
 *
 * @api
 */
class Image extends BaseMediaAsset implements ImageTransformationInterface
{
    use ImageTransformationTrait;
    protected const SHORTEN_ASSET_TYPE = 'iu';
    /**
     * @var array A list of the delivery types that support SEO suffix.
     */
    protected static array $suffixSupportedDeliveryTypes = [AssetType::IMAGE => [DeliveryType::UPLOAD => 'images', DeliveryType::PRIVATE_DELIVERY => 'private_images', DeliveryType::AUTHENTICATED => 'authenticated_images']];
    /**
     * Gets the transformation.
     *
     */
    public function getTransformation(): CommonTransformation
    {
        if (!isset($this->transformation)) {
            $this->transformation = new ImageTransformation();
        }
        return $this->transformation;
    }
    /**
     * Finalizes the asset type.
     *
     */
    protected function finalizeAssetType(): ?string
    {
        return $this->finalizeShorten(parent::finalizeAssetType());
    }
}
