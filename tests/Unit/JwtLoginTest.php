<?php

declare(strict_types=1);

namespace PhPhD\ApiTest\Tests\Unit;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PhPhD\ApiTest\Authenticator\TestJwtAuthenticator;
use PhPhD\ApiTest\JwtLoginTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @covers \PhPhD\ApiTest\JwtLoginTrait
 * @covers \PhPhD\ApiTest\Authenticator\TestJwtAuthenticator
 *
 * @internal
 */
final class JwtLoginTest extends KernelTestCase
{
    use JwtLoginTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $adminUser = $this->createMock(UserInterface::class);
        $ordinaryUser = $this->createMock(UserInterface::class);

        $tokenManager = $this->createMock(JWTTokenManagerInterface::class);
        $tokenManager
            ->method('create')
            ->willReturnMap([
                [$adminUser, 'generated-admin-jwt-token'],
                [$ordinaryUser, 'ordinary-user-jwt-token'],
            ])
        ;

        $adminUserProvider = $this->createMock(UserProviderInterface::class);
        $adminUserProvider
            ->method('loadUserByIdentifier')
            ->willReturnMap([['admin@test.com', $adminUser]])
        ;

        $ordinaryUserProvider = $this->createMock(UserProviderInterface::class);
        $ordinaryUserProvider
            ->method('loadUserByIdentifier')
            ->willReturnMap([['username', $ordinaryUser]])
        ;

        $container = $this->createMock(ContainerInterface::class);

        $container
            ->method('get')
            ->willReturnMap([
                [
                    'phd_api_test.jwt_authenticator.admin',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    new TestJwtAuthenticator($adminUserProvider, $tokenManager),
                ],
                [
                    'phd_api_test.jwt_authenticator.default',
                    ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                    new TestJwtAuthenticator($ordinaryUserProvider, $tokenManager),
                ],
            ])
        ;

        KernelTestCase::setContainer($container);
    }

    protected function tearDown(): void
    {
        KernelTestCase::setContainer(null);
    }

    public function testAuthenticatesOrdinaryUser(): void
    {
        $token = $this->login('username');

        self::assertSame('ordinary-user-jwt-token', $token);
    }

    public function testAuthenticatesAdmin(): void
    {
        $token = $this->login('admin@test.com', 'admin');

        self::assertSame('generated-admin-jwt-token', $token);
    }
}
