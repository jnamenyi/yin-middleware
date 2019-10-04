<?php

declare(strict_types=1);

namespace WoohooLabs\YinMiddleware\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WoohooLabs\Yin\JsonApi\Exception\DefaultExceptionFactory;
use WoohooLabs\Yin\JsonApi\Exception\MediaTypeUnacceptable;
use WoohooLabs\Yin\JsonApi\Exception\MediaTypeUnsupported;
use WoohooLabs\Yin\JsonApi\Exception\QueryParamUnrecognized;
use WoohooLabs\Yin\JsonApi\Exception\RequestBodyInvalidJson;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequest;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequestInterface;
use WoohooLabs\YinMiddleware\Exception\JsonApiRequestException;
use WoohooLabs\YinMiddleware\Middleware\JsonApiRequestValidatorMiddleware;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;

class JsonApiRequestValidatorMiddlewareTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     * @test
     */
    public function successOnValidHeaders(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            true,
            false,
            false
        );

        $request = $this->createRequest(
            [],
            "",
            ["content-type" => "application/vnd.api+json", "accept" => "application/vnd.api+json"]
        );

        $middleware->process($request, $this->createHandler());
    }

    /**
     * @doesNotPerformAssertions
     * @test
     */
    public function successOnMissingHeaders(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            true,
            false,
            false
        );

        $request = $this->createRequest();

        $middleware->process($request, $this->createHandler());
    }

    /**
     * @test
     */
    public function exceptionOnInvalidContentTypeHeader(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            true,
            false,
            false
        );
        $request = $this->createRequest([], "", ["content-type" => "application/vnd.api+json; version=1", "accept" => "application/vnd.api+json"]);

        $this->expectException(MediaTypeUnsupported::class);

        $middleware->process($request, $this->createHandler());
    }

    /**
     * @test
     */
    public function exceptionOnInvalidAcceptHeader(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            true,
            false,
            false
        );

        $request = $this->createRequest([], "", ["content-type" => "application/vnd.api+json", "accept" => "application/vnd.api+json; version=1"]);

        $this->expectException(MediaTypeUnacceptable::class);
        $middleware->process($request, $this->createHandler());
    }

    /**
     * @doesNotPerformAssertions
     * @test
     */
    public function successOnValidQueryParams(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            false,
            true,
            false
        );
        $request = $this->createRequest(["page" => ["number" => "1", "size" => "10"]]);

        $middleware->process($request, $this->createHandler());
    }

    /**
     * @test
     */
    public function exceptionOnInvalidQueryParams(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            false,
            true,
            false
        );
        $request = $this->createRequest(["foo" => "bar"]);

        $this->expectException(QueryParamUnrecognized::class);

        $middleware->process($request, $this->createHandler());
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function successOnEmptyRequestBody(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            false,
            false,
            true
        );

        $request = $this->createRequest();

        $middleware->process($request, $this->createHandler());
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function successOnValidRequestBody(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            false,
            false,
            true
        );

        $request = $this->createRequest([], $this->getValidRequestBody());

        $middleware->process($request, $this->createHandler());
    }

    /**
     * @test
     */
    public function exceptionOnServerRequest(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware();

        $this->expectException(JsonApiRequestException::class);

        $middleware->process(new ServerRequest(), $this->createHandler());
    }

    /**
     * @test
     */
    public function exceptionOnInvalidJsonRequestBody(): void
    {
        $middleware = new JsonApiRequestValidatorMiddleware(
            null,
            true,
            false,
            false,
            true
        );
        $request = $this->createRequest([], $this->getInvalidJsonRequestBody());

        $this->expectException(RequestBodyInvalidJson::class);

        $middleware->process($request, $this->createHandler());
    }

    private function getValidRequestBody(): string
    {
        return <<<EOF
{
  "data": {
    "type": "photos",
    "attributes": {
      "title": "Ember Hamster",
      "src": "https://example.com/images/productivity.png"
    },
    "relationships": {
      "photographer": {
        "data": { "type": "people", "id": "9" }
      }
    }
  }
}
EOF;
    }

    private function getInvalidJsonRequestBody(): string
    {
        return <<<EOF
{
  "data": {
    "type": "photos"
    "attributes": {
      "title": "Ember Hamster",
      "src": "https://example.com/images/productivity.png"
    },
  }
}
EOF;
    }

    /**
     * @param array<mixed, mixed> $queryParams
     * @param array<mixed, mixed> $headers
     */
    private function createRequest(array $queryParams = [], string $body = "", array $headers = []): JsonApiRequestInterface
    {
        $request = new ServerRequest([], [], "", "POST", new Stream("php://memory", "rw"));
        $request = $request->withQueryParams($queryParams);
        $request->getBody()->write($body);
        foreach ($headers as $header => $value) {
            $request = $request->withHeader($header, $value);
        }

        return new JsonApiRequest($request, new DefaultExceptionFactory());
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }
}
