<?php

/**
 * This file is part of the Cloudinary PHP package.
 *
 * (c) Cloudinary
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace LDC\Deps\Cloudinary\Tag;

use LDC\Deps\Cloudinary\ArrayUtils;
use LDC\Deps\Cloudinary\Asset\AssetType;
use LDC\Deps\Cloudinary\Asset\Video;
use LDC\Deps\Cloudinary\Configuration\Configuration;
/**
 *
 * Generates an HTML `<img>` tag based on a captured frame from the specified video source.
 *
 * @api
 */
class VideoThumbnailTag extends ImageTag
{
    /**
     * Sets the image of the tag.
     *
     * @param mixed              $image         The public ID of the video.
     * @param Configuration|null $configuration The Configuration source.
     *
     */
    public function image(mixed $image, ?Configuration $configuration = null): static
    {
        parent::image(new Video($image, $configuration), $configuration);
        $this->image->setFormat($configuration->tag->videoPosterFormat, $configuration->tag->useFetchFormat);
        return $this;
    }
    /**
     * Creates a video poster image tag for a video from the provided source and an array of parameters.
     *
     * @param string $source The public ID of the asset.
     * @param array  $params The asset parameters.
     *
     */
    public static function fromParams(string $source, array $params = []): VideoThumbnailTag
    {
        $configuration = self::fromParamsDefaultConfig();
        ArrayUtils::setDefaultValue($params, 'resource_type', AssetType::VIDEO);
        ArrayUtils::setDefaultValue($params, 'format', $configuration->tag->videoPosterFormat);
        return parent::fromParams($source, $params);
    }
}
