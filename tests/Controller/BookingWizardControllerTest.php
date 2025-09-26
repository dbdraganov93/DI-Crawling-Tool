<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\BookingWizardController;
use App\Service\IprotoService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Container;

class BookingWizardControllerTest extends TestCase
{
    public function testFetchBrochuresPassesFiltersToService(): void
    {
        $request = new Request([
            'companyId' => '84867',
            'ownerId' => '1',
            'deletedFilter' => 'deleted',
            'timeConstraint' => [
                'current' => 'true',
                'past' => '1',
            ],
        ]);

        $iprotoService = $this->createMock(IprotoService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $iprotoService
            ->expects($this->once())
            ->method('getBrochuresByOwnerAndCompany')
            ->with(
                '1',
                '84867',
                [
                    'deletedFilter' => 'deleted',
                    'timeConstraints' => ['current', 'past'],
                ],
            )
            ->willReturn([
                ['id' => 1],
            ]);

        $controller = new BookingWizardController();
        $controller->setContainer(new Container());

        $response = $controller->fetchBrochures($request, $iprotoService, $logger);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('[{"id":1}]', $response->getContent());
    }

    public function testFetchBrochuresUsesDefaultFilters(): void
    {
        $request = new Request([
            'companyId' => '84867',
            'ownerId' => '1',
        ]);

        $iprotoService = $this->createMock(IprotoService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $iprotoService
            ->expects($this->once())
            ->method('getBrochuresByOwnerAndCompany')
            ->with(
                '1',
                '84867',
                [
                    'deletedFilter' => 'active',
                    'timeConstraints' => ['current', 'upcoming'],
                ],
            )
            ->willReturn([]);

        $controller = new BookingWizardController();
        $controller->setContainer(new Container());

        $response = $controller->fetchBrochures($request, $iprotoService, $logger);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('[]', $response->getContent());
    }
}
