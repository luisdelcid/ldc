<?php

/**
 * This file is part of the Cloudinary PHP package.
 *
 * (c) Cloudinary
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace LDC\Deps\Cloudinary\HttpClient;

use LDC\Deps\GuzzleHttp\Exception\ConnectException;
use LDC\Deps\GuzzleHttp\Exception\RequestException;
use LDC\Deps\GuzzleHttp\Exception\TransferException;
use LDC\Deps\GuzzleHttp\Promise\Create;
use LDC\Deps\GuzzleHttp\Promise\PromiseInterface;
use LDC\Deps\Psr\Http\Client\ClientExceptionInterface;
use LDC\Deps\Psr\Http\Client\ClientInterface;
use LDC\Deps\Psr\Http\Client\NetworkExceptionInterface;
use LDC\Deps\Psr\Http\Message\RequestInterface;
/**
 * Guzzle handler that sends requests through a PSR-18 HTTP client.
 *
 * @internal
 */
class Psr18Handler
{
    /**
     * @var ClientInterface $client The PSR-18 client that performs the requests.
     */
    private ClientInterface $client;
    /**
     * Psr18Handler constructor.
     *
     * @param ClientInterface $client The PSR-18 client that performs the requests.
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }
    /**
     * Sends the request synchronously and returns a settled promise.
     *
     * @param RequestInterface $request The request to send.
     * @param array            $options Guzzle request options (transfer options are owned by the PSR-18 client).
     */
    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        try {
            return Create::promiseFor($this->client->sendRequest($request));
        } catch (TransferException $e) {
            return Create::rejectionFor($e);
        } catch (NetworkExceptionInterface $e) {
            return Create::rejectionFor(new ConnectException($e->getMessage(), $request, $e));
        } catch (ClientExceptionInterface $e) {
            return Create::rejectionFor(new RequestException($e->getMessage(), $request, previous: $e));
        }
    }
}
