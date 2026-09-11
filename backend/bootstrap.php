<?php

declare(strict_types=1);

use App\Application;
use App\Controllers\AnalyticsController;
use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\LeadController;
use App\Controllers\TicketController;
use App\Controllers\UserController;
use App\Database\AbstractDatabase;
use App\Database\Database;
use App\Http\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Repositories\AnalyticsRepository;
use App\Repositories\AuthRepository;
use App\Repositories\ClientRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\LeadRepository;
use App\Repositories\RateLimitRepository;
use App\Repositories\TicketRepository;
use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\ClientService;
use App\Services\LeadService;
use App\Services\RefreshCookieService;
use App\Services\TicketService;
use App\Services\TokenService;
use App\Services\UserService;
use App\Support\Container;
use App\Support\ExceptionHandler;
use App\Support\LoggerFactory;
use App\Utils\Validator;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

if (is_file(__DIR__ . '/.env')) {
    Dotenv::createUnsafeImmutable(__DIR__)->safeLoad();
}

$appConfig = require __DIR__ . '/config/app.php';
$databaseConfig = require __DIR__ . '/config/database.php';
date_default_timezone_set($appConfig['timezone']);
ini_set('display_errors', $appConfig['debug'] ? '1' : '0');
error_reporting(E_ALL);

$container = new Container();
$container->set('app.config', new ArrayObject($appConfig));
$container->singleton(AbstractDatabase::class, static fn (): Database => new Database($databaseConfig));
$container->singleton(Validator::class, static fn (): Validator => new Validator());
$container->singleton(TokenService::class, static fn (): TokenService => new TokenService($appConfig));
$container->singleton(RefreshCookieService::class, static fn (): RefreshCookieService => new RefreshCookieService($appConfig));
$container->singleton(AuthRepository::class, static fn (Container $c): AuthRepository => new AuthRepository($c->get(AbstractDatabase::class)));
$container->singleton(LeadRepository::class, static fn (Container $c): LeadRepository => new LeadRepository($c->get(AbstractDatabase::class)));
$container->singleton(ClientRepository::class, static fn (Container $c): ClientRepository => new ClientRepository($c->get(AbstractDatabase::class)));
$container->singleton(TicketRepository::class, static fn (Container $c): TicketRepository => new TicketRepository($c->get(AbstractDatabase::class)));
$container->singleton(EmployeeRepository::class, static fn (Container $c): EmployeeRepository => new EmployeeRepository($c->get(AbstractDatabase::class)));
$container->singleton(AnalyticsRepository::class, static fn (Container $c): AnalyticsRepository => new AnalyticsRepository($c->get(AbstractDatabase::class)));
$container->singleton(RateLimitRepository::class, static fn (Container $c): RateLimitRepository => new RateLimitRepository($c->get(AbstractDatabase::class)));
$container->singleton(AuthService::class, static fn (Container $c): AuthService => new AuthService($c->get(AuthRepository::class), $c->get(TokenService::class), $appConfig));
$container->singleton(LeadService::class, static fn (Container $c): LeadService => new LeadService($c->get(LeadRepository::class), $c->get(Validator::class)));
$container->singleton(ClientService::class, static fn (Container $c): ClientService => new ClientService($c->get(ClientRepository::class), $c->get(Validator::class)));
$container->singleton(TicketService::class, static fn (Container $c): TicketService => new TicketService($c->get(TicketRepository::class), $c->get(Validator::class)));
$container->singleton(UserService::class, static fn (Container $c): UserService => new UserService($c->get(EmployeeRepository::class), $c->get(Validator::class)));
$container->singleton(AnalyticsService::class, static fn (Container $c): AnalyticsService => new AnalyticsService($c->get(AnalyticsRepository::class)));

$authController = new AuthController(
    $container->get(AuthService::class),
    $container->get(UserService::class),
    $container->get(Validator::class),
    $container->get(RefreshCookieService::class),
);
$leadController = new LeadController($container->get(LeadService::class));
$clientController = new ClientController($container->get(ClientService::class));
$ticketController = new TicketController($container->get(TicketService::class));
$analyticsController = new AnalyticsController($container->get(AnalyticsService::class));
$userController = new UserController($container->get(UserService::class));

$auth = new AuthMiddleware($container->get(TokenService::class));
$rateLimit = new RateLimitMiddleware(
    $container->get(RateLimitRepository::class),
    $appConfig['rate_limit_requests'],
    $appConfig['rate_limit_window'],
);
$tickets = new PermissionMiddleware('tickets.manage');
$leads = new PermissionMiddleware('leads.manage');
$employees = new PermissionMiddleware('employees.manage');
$analytics = new PermissionMiddleware('analytics.view');

$router = new Router();
$router->add('GET', '/api/v1/health', static fn (): \App\Http\Response => \App\Http\Response::success(['status' => 'ok']));
$router->add('POST', '/api/v1/auth/login', [$authController, 'login'], [$rateLimit]);
$router->add('POST', '/api/v1/auth/pin-login', [$authController, 'pinLogin'], [$rateLimit]);
$router->add('POST', '/api/v1/auth/trusted-device', [$authController, 'enrollTrustedDevice'], [$auth, $rateLimit]);
$router->add('DELETE', '/api/v1/auth/trusted-device', [$authController, 'revokeTrustedDevice'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/auth/refresh', [$authController, 'refresh'], [$rateLimit]);
$router->add('POST', '/api/v1/auth/forgot-password', [$authController, 'forgotPassword'], [$rateLimit]);
$router->add('POST', '/api/v1/auth/reset-password', [$authController, 'resetPassword'], [$rateLimit]);
$router->add('POST', '/api/v1/auth/change-password', [$authController, 'changePassword'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/auth/logout', [$authController, 'logout'], [$rateLimit]);
$router->add('POST', '/api/v1/auth/register', [$authController, 'register'], [$auth, $rateLimit]);

$router->add('GET', '/api/v1/leads', [$leadController, 'index'], [$auth, $leads, $rateLimit]);
$router->add('GET', '/api/v1/leads/{id}', [$leadController, 'show'], [$auth, $leads, $rateLimit]);
$router->add('POST', '/api/v1/leads', [$leadController, 'store'], [$auth, $leads, $rateLimit]);
$router->add('PUT', '/api/v1/leads/{id}', [$leadController, 'update'], [$auth, $leads, $rateLimit]);
$router->add('DELETE', '/api/v1/leads/{id}', [$leadController, 'destroy'], [$auth, $leads, $rateLimit]);
$router->add('POST', '/api/v1/leads/bulk-status', [$leadController, 'bulkStatus'], [$auth, $leads, $rateLimit]);

$router->add('GET', '/api/v1/customers', [$clientController, 'index'], [$auth, $rateLimit]);
$router->add('GET', '/api/v1/customers/{id}', [$clientController, 'show'], [$auth, $rateLimit]);
$router->add('GET', '/api/v1/customers/{id}/service-history', [$clientController, 'serviceHistory'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/customers', [$clientController, 'store'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/customers/onboard', [$clientController, 'onboard'], [$auth, $rateLimit]);
$router->add('PUT', '/api/v1/customers/{id}', [$clientController, 'update'], [$auth, $rateLimit]);
$router->add('DELETE', '/api/v1/customers/{id}', [$clientController, 'destroy'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/customers/{id}/locations', [$clientController, 'createLocation'], [$auth, $rateLimit]);
$router->add('PUT', '/api/v1/customers/{id}/locations/{location_id}', [$clientController, 'updateLocation'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/customers/{id}/locations/{location_id}/{action}', [$clientController, 'locationStatus'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/customers/{id}/contacts', [$clientController, 'createContact'], [$auth, $rateLimit]);
$router->add('PUT', '/api/v1/customers/{id}/contacts/{contact_id}', [$clientController, 'updateContact'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/customers/{id}/contacts/{contact_id}/{action}', [$clientController, 'contactStatus'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/customers/{id}/{action}', [$clientController, 'status'], [$auth, $rateLimit]);

$router->add('GET', '/api/v1/tickets', [$ticketController, 'index'], [$auth, $rateLimit]);
$router->add('GET', '/api/v1/tickets/{id}', [$ticketController, 'show'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/tickets', [$ticketController, 'store'], [$auth, $rateLimit]);
$router->add('PUT', '/api/v1/tickets/{id}/priority', [$ticketController, 'priority'], [$auth, $tickets, $rateLimit]);
$router->add('PUT', '/api/v1/tickets/{id}/description', [$ticketController, 'updateDescription'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/tickets/{id}/messages', [$ticketController, 'addMessage'], [$auth, $rateLimit]);
$router->add('POST', '/api/v1/tickets/{id}/{action}', [$ticketController, 'transition'], [$auth, $tickets, $rateLimit]);
$router->add('DELETE', '/api/v1/tickets/{id}', [$ticketController, 'destroy'], [$auth, $tickets, $rateLimit]);

$router->add('GET', '/api/v1/analytics', [$analyticsController, 'dashboard'], [$auth, $analytics, $rateLimit]);
$router->add('GET', '/api/v1/reports/service-ledger', [$analyticsController, 'serviceLedger'], [$auth, $analytics, $rateLimit]);
$router->add('GET', '/api/v1/users', [$userController, 'index'], [$auth, $employees, $rateLimit]);
$router->add('POST', '/api/v1/users', [$userController, 'store'], [$auth, $employees, $rateLimit]);
$router->add('GET', '/api/v1/users/{id}', [$userController, 'show'], [$auth, $employees, $rateLimit]);
$router->add('PUT', '/api/v1/users/{id}', [$userController, 'update'], [$auth, $employees, $rateLimit]);
$router->add('POST', '/api/v1/users/{id}/suspend', [$userController, 'suspend'], [$auth, $employees, $rateLimit]);
$router->add('POST', '/api/v1/users/{id}/reactivate', [$userController, 'reactivate'], [$auth, $employees, $rateLimit]);
$router->add('PUT', '/api/v1/users/{id}/password', [$userController, 'setPassword'], [$auth, $employees, $rateLimit]);
$router->add('DELETE', '/api/v1/users/{id}', [$userController, 'destroy'], [$auth, $employees, $rateLimit]);
$router->add('POST', '/api/v1/teams', [$userController, 'createTeam'], [$auth, $employees, $rateLimit]);
$router->add('POST', '/api/v1/teams/{team_id}/members', [$userController, 'addTeamMember'], [$auth, $employees, $rateLimit]);

$logger = LoggerFactory::create(__DIR__ . '/logs');
return new Application(
    $router,
    new ExceptionHandler($logger, $appConfig['debug']),
    [new CorsMiddleware($appConfig['cors_allowed_origins']), new SecurityHeadersMiddleware()],
);
