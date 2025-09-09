<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $environment
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests (those starting with /api/)
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'An error occurred';
        $details = null;

        // Handle different types of exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        } elseif ($exception instanceof NotFoundHttpException) {
            $statusCode = Response::HTTP_NOT_FOUND;
            $message = 'Endpoint not found';
        } elseif ($exception instanceof ValidationFailedException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $message = 'Validation failed';
            $details = $this->formatValidationErrors($exception);
        } elseif ($exception instanceof \InvalidArgumentException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $message = $exception->getMessage();
        }

        // Log the exception
        $this->logger->error('API Exception occurred', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_uri' => $request->getRequestUri(),
            'request_method' => $request->getMethod(),
            'status_code' => $statusCode,
            'trace' => $exception->getTraceAsString()
        ]);

        // Prepare response data
        $responseData = [
            'error' => $this->getErrorType($statusCode),
            'message' => $message,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'path' => $request->getPathInfo()
        ];

        // Add details if available
        if ($details !== null) {
            $responseData['details'] = $details;
        }

        // Add debug information in development environment
        if ($this->environment === 'dev') {
            $responseData['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice($exception->getTrace(), 0, 5) // Limit trace for readability
            ];
        }

        $response = new JsonResponse($responseData, $statusCode);
        $event->setResponse($response);
    }

    private function getErrorType(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => 'Bad Request',
            Response::HTTP_UNAUTHORIZED => 'Unauthorized',
            Response::HTTP_FORBIDDEN => 'Forbidden',
            Response::HTTP_NOT_FOUND => 'Not Found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
            Response::HTTP_INTERNAL_SERVER_ERROR => 'Internal Server Error',
            Response::HTTP_BAD_GATEWAY => 'Bad Gateway',
            Response::HTTP_SERVICE_UNAVAILABLE => 'Service Unavailable',
            default => 'Error'
        };
    }

    private function formatValidationErrors(ValidationFailedException $exception): array
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[] = [
                'property' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
                'invalid_value' => $violation->getInvalidValue()
            ];
        }
        return $errors;
    }
}
