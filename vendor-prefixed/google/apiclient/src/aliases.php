<?php

namespace LDC\Deps;

if (\class_exists('LDC\Deps\Google_Client', \false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}
$classMap = ['LDC\Deps\Google\Client' => 'Google_Client', 'LDC\Deps\Google\Service' => 'Google_Service', 'LDC\Deps\Google\AccessToken\Revoke' => 'Google_AccessToken_Revoke', 'LDC\Deps\Google\AccessToken\Verify' => 'Google_AccessToken_Verify', 'LDC\Deps\Google\Model' => 'Google_Model', 'LDC\Deps\Google\Utils\UriTemplate' => 'Google_Utils_UriTemplate', 'LDC\Deps\Google\AuthHandler\Guzzle6AuthHandler' => 'Google_AuthHandler_Guzzle6AuthHandler', 'LDC\Deps\Google\AuthHandler\Guzzle7AuthHandler' => 'Google_AuthHandler_Guzzle7AuthHandler', 'LDC\Deps\Google\AuthHandler\AuthHandlerFactory' => 'Google_AuthHandler_AuthHandlerFactory', 'LDC\Deps\Google\Http\Batch' => 'Google_Http_Batch', 'LDC\Deps\Google\Http\MediaFileUpload' => 'Google_Http_MediaFileUpload', 'LDC\Deps\Google\Http\REST' => 'Google_Http_REST', 'LDC\Deps\Google\Task\Retryable' => 'Google_Task_Retryable', 'LDC\Deps\Google\Task\Exception' => 'Google_Task_Exception', 'LDC\Deps\Google\Task\Runner' => 'Google_Task_Runner', 'LDC\Deps\Google\Collection' => 'Google_Collection', 'LDC\Deps\Google\Service\Exception' => 'Google_Service_Exception', 'LDC\Deps\Google\Service\Resource' => 'Google_Service_Resource', 'LDC\Deps\Google\Exception' => 'Google_Exception'];
foreach ($classMap as $class => $alias) {
    \class_alias($class, $alias);
}
/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Google_Task_Composer extends \LDC\Deps\Google\Task\Composer
{
}
/** @phpstan-ignore-next-line */
if (\false) {
    class Google_AccessToken_Revoke extends \LDC\Deps\Google\AccessToken\Revoke
    {
    }
    class Google_AccessToken_Verify extends \LDC\Deps\Google\AccessToken\Verify
    {
    }
    class Google_AuthHandler_AuthHandlerFactory extends \LDC\Deps\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class Google_AuthHandler_Guzzle6AuthHandler extends \LDC\Deps\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle7AuthHandler extends \LDC\Deps\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class Google_Client extends \LDC\Deps\Google\Client
    {
    }
    class Google_Collection extends \LDC\Deps\Google\Collection
    {
    }
    class Google_Exception extends \LDC\Deps\Google\Exception
    {
    }
    class Google_Http_Batch extends \LDC\Deps\Google\Http\Batch
    {
    }
    class Google_Http_MediaFileUpload extends \LDC\Deps\Google\Http\MediaFileUpload
    {
    }
    class Google_Http_REST extends \LDC\Deps\Google\Http\REST
    {
    }
    class Google_Model extends \LDC\Deps\Google\Model
    {
    }
    class Google_Service extends \LDC\Deps\Google\Service
    {
    }
    class Google_Service_Exception extends \LDC\Deps\Google\Service\Exception
    {
    }
    class Google_Service_Resource extends \LDC\Deps\Google\Service\Resource
    {
    }
    class Google_Task_Exception extends \LDC\Deps\Google\Task\Exception
    {
    }
    interface Google_Task_Retryable extends \LDC\Deps\Google\Task\Retryable
    {
    }
    class Google_Task_Runner extends \LDC\Deps\Google\Task\Runner
    {
    }
    class Google_Utils_UriTemplate extends \LDC\Deps\Google\Utils\UriTemplate
    {
    }
}
