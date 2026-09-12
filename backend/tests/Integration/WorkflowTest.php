<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\Database;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DatabaseException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Actor;
use App\Repositories\AnalyticsRepository;
use App\Repositories\AuthRepository;
use App\Repositories\ClientRepository;
use App\Repositories\EmployeeRepository;
use App\Repositories\LeadRepository;
use App\Repositories\TicketRepository;
use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\ClientService;
use App\Services\TicketService;
use App\Services\TokenService;
use App\Utils\Validator;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    private Database $db;
    private TicketRepository $tickets;
    private TicketService $service;
    private ClientRepository $clients;
    private AuthRepository $accounts;
    private EmployeeRepository $employees;
    private Actor $admin;
    private Actor $employee;
    private Actor $contact;
    private array $client;
    private int $secondLocation;
    private string $suffix;
    private const PASSWORD = ' <Exact&Password> 123 ';

    protected function setUp(): void
    {
        if (getenv('TEST_DB_NAME') === false) {
            self::markTestSkipped('Set TEST_DB_NAME to a migrated disposable MariaDB database.');
        }
        $name = (string) getenv('TEST_DB_NAME');
        if (!preg_match('/^onesalez_(audit|test)(_[a-z0-9]+)?$/', $name)) {
            throw new \RuntimeException('Integration tests require an explicitly named disposable database.');
        }
        date_default_timezone_set('Asia/Kolkata');
        $this->db = new Database([
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1', 'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'database' => $name, 'username' => getenv('TEST_DB_USER') ?: 'audit',
            'password' => getenv('TEST_DB_PASSWORD') ?: '', 'retries' => 1, 'persistent' => false,
        ]);
        $this->tickets = new TicketRepository($this->db);
        $this->service = new TicketService($this->tickets, new Validator());
        $this->clients = new ClientRepository($this->db);
        $this->accounts = new AuthRepository($this->db);
        $this->employees = new EmployeeRepository($this->db);
        $this->suffix = bin2hex(random_bytes(5));
        $system = new Actor(0, 'EMPLOYEE', 'fixture@example.invalid', null, ['SYSTEM_ADMIN'], []);
        $id = $this->employees->createEmployee(['employee_code' => 'ADM-' . $this->suffix, 'full_name' => 'Audit Admin', 'official_email' => 'admin-' . $this->suffix . '@example.invalid'], ['SYSTEM_ADMIN'], self::PASSWORD, $system);
        $this->admin = $this->accounts->actor('EMPLOYEE', $id);
        $id = $this->employees->createEmployee(['employee_code' => 'EMP-' . $this->suffix, 'full_name' => 'Audit Employee', 'official_email' => 'employee-' . $this->suffix . '@example.invalid'], ['SERVICE_EMPLOYEE'], self::PASSWORD, $system);
        $this->employee = $this->accounts->actor('EMPLOYEE', $id);
        $id = $this->clients->onboard(
            ['client_code' => 'AUD-' . $this->suffix, 'legal_name' => 'Audit ' . $this->suffix],
            ['location_code' => 'HQ', 'location_name' => 'Head office', 'location_type' => 'HEAD_OFFICE', 'address_line_1' => 'Test street', 'city' => 'Delhi', 'state_name' => 'Delhi', 'postal_code' => '110001'],
            ['full_name' => 'Audit Contact', 'email' => 'contact-' . $this->suffix . '@example.invalid', 'mobile_number' => '9999999999'],
            password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
            $this->admin->identifier(),
        );
        $this->client = $this->clients->find($id);
        $this->contact = $this->accounts->actor('CLIENT_CONTACT', (int) $this->client['contacts'][0]['contact_id']);
        $statement = $this->db->connection()->prepare("INSERT INTO client_locations (client_id,location_code,location_name,location_type,address_line_1,city,state_name,postal_code,created_by) VALUES (?, 'BRANCH', 'Branch office', 'BRANCH', 'Test road', 'Delhi','Delhi','110002','TEST')");
        $statement->execute([$id]);
        $this->secondLocation = (int) $this->db->connection()->lastInsertId();
    }

    private function createTicket(?int $location = null): array
    {
        return $this->service->create(['location_id' => $location ?? $this->client['locations'][0]['location_id'], 'subject' => 'Audit ' . $this->suffix, 'issue_description' => 'Test issue'], $this->contact);
    }

    private function auth(): array
    {
        $config = ['key' => str_repeat('integration-key-', 4), 'jwt_issuer' => 'audit', 'jwt_access_ttl' => 900, 'jwt_refresh_ttl' => 86400, 'trusted_device_ttl' => 86400, 'url' => 'https://example.invalid'];
        $tokens = new TokenService($config);
        return [new AuthService($this->accounts, $tokens, $config), $tokens];
    }

    private function http(Actor $actor): \Closure
    {
        foreach (['HOST', 'PORT', 'NAME', 'USER', 'PASSWORD'] as $key) {
            putenv('DB_' . $key . '=' . (getenv('TEST_DB_' . $key) ?: ($key === 'HOST' ? '127.0.0.1' : '')));
        }
        putenv('APP_KEY=' . str_repeat('integration-key-', 4));
        putenv('JWT_ISSUER=audit');
        putenv('APP_URL=https://example.invalid');
        putenv('APP_DEBUG=false');
        $app = require dirname(__DIR__, 2) . '/bootstrap.php';
        [$auth] = $this->auth();
        $session = $auth->login($actor->email, self::PASSWORD, $actor->type === 'EMPLOYEE' ? 'employee' : 'client', '127.0.0.1', 'test');
        return static function (string $method, string $path, array $body = [], array $query = [], int $status = 200) use ($app, $session): array {
            $response = $app->run(new Request($method, '/api/v1' . $path, $query, $body, ['authorization' => 'Bearer ' . $session['access_token']], '127.0.0.1'));
            self::assertSame($status, $response->status, $method . ' ' . $path . ' ' . json_encode($response->payload['error'] ?? null));
            return $response->payload['data'] ?? [];
        };
    }

    public function testHttpEmployeeAndTeamAdministrationRoundTrip(): void
    {
        $http = $this->http($this->admin);
        $input = ['employee_code' => 'CRUD-' . $this->suffix, 'full_name' => 'CRUD employee', 'official_email' => 'crud-' . $this->suffix . '@example.invalid', 'password' => self::PASSWORD, 'roles' => ['SERVICE_EMPLOYEE'], 'joining_date' => '2026-01-01'];
        $id = $http('POST', '/users', $input, [], 201)['employee_id'];
        $input['full_name'] = 'Updated employee';
        self::assertSame('Updated employee', $http('PUT', '/users/' . $id, $input)['full_name']);
        self::assertSame($id, $http('GET', '/users/' . $id)['employee_id']);
        self::assertSame(1, $http('GET', '/users', [], ['paginated' => '1', 'search' => $input['official_email']])['total']);
        $http('POST', '/users/' . $id . '/suspend', ['reason' => 'Audit']);
        self::assertNull($this->accounts->actor('EMPLOYEE', (int) $id));
        $http('POST', '/users/' . $id . '/reactivate');
        self::assertNotNull($this->accounts->actor('EMPLOYEE', (int) $id));
        $http('PUT', '/users/' . $id . '/password', ['password' => 'New exact password!']);
        [$auth] = $this->auth();
        self::assertSame($id, $auth->login($input['official_email'], 'New exact password!', 'employee', '127.0.0.1', 'test')['actor']->id);
        $team = $http('POST', '/teams', ['team_name' => 'Audit ' . $this->suffix], [], 201)['team_id'];
        $http('POST', '/teams/' . $team . '/members', ['employee_id' => $id, 'is_team_lead' => true], [], 201);
        $updated = $http('PUT', '/teams/' . $team, ['team_name' => 'Updated team ' . $this->suffix, 'description' => 'Audit members']);
        self::assertSame('Updated team ' . $this->suffix, $updated['team_name']);
        self::assertNotEmpty($http('GET', '/teams'));
        $http('DELETE', '/teams/' . $team . '/members/' . $id);
        $http('DELETE', '/teams/' . $team);
        $http('DELETE', '/users/' . $id);
        $http('GET', '/users/' . $id, [], [], 404);
        $http('POST', '/users/' . $this->admin->id . '/suspend', [], [], 403);
        $http('POST', '/users', array_replace($input, ['joining_date' => '2026-02-30']), [], 422);
    }

    public function testHttpClientMaintenancePreservesScopesAndHistory(): void
    {
        $http = $this->http($this->employee);
        $id = $this->client['client_id'];
        $base = '/customers/' . $id;
        self::assertSame('UPDATED BUSINESS', $http('PUT', $base, ['legal_name' => 'Updated business'])['legal_name']);
        self::assertSame(1, $http('GET', '/customers', [], ['paginated' => '1', 'search' => $this->suffix])['total']);
        $location = ['location_code' => 'CRUD', 'location_name' => 'Audit office', 'location_type' => 'BRANCH', 'address_line_1' => 'Test road', 'city' => 'Delhi', 'state_name' => 'Delhi', 'postal_code' => '110001'];
        $client = $http('POST', $base . '/locations', $location, [], 201);
        $site = array_values(array_filter($client['locations'], static fn (array $row): bool => $row['location_code'] === 'CRUD'))[0]['location_id'];
        $http('PUT', $base . '/locations/' . $site, array_replace($location, ['city' => 'Mumbai']));
        $http('POST', $base . '/locations/' . $site . '/suspend');
        $http('POST', $base . '/locations/' . $site . '/reactivate');
        $contact = ['full_name' => 'Extra contact', 'email' => 'extra-' . $this->suffix . '@example.invalid', 'mobile_number' => '9999999999', 'role_code' => 'CLIENT_ADMIN', 'location_ids' => [$site], 'has_all_locations' => false, 'portal_enabled' => true, 'password' => self::PASSWORD];
        $client = $http('POST', $base . '/contacts', $contact, [], 201);
        $person = array_values(array_filter($client['contacts'], static fn (array $row): bool => $row['email'] === $contact['email']))[0]['contact_id'];
        self::assertNotNull($this->accounts->actor('CLIENT_CONTACT', (int) $person));
        $http('PUT', $base . '/contacts/' . $person, array_replace($contact, ['full_name' => 'Edited contact', 'password' => '']));
        $http('POST', $base . '/contacts/' . $person . '/suspend');
        self::assertNull($this->accounts->actor('CLIENT_CONTACT', (int) $person));
        $http('POST', $base . '/contacts/' . $person . '/reactivate');
        $http('PUT', $base . '/contacts/' . $person, array_replace($contact, ['password' => str_repeat('a', 73)]), [], 422);
        $http('DELETE', $base . '/contacts/' . $person);
        $http('DELETE', $base . '/locations/' . $site);
        $http('GET', $base . '/service-history');
        $http('POST', $base . '/suspend');
        self::assertNull($this->accounts->actor('CLIENT_CONTACT', $this->contact->id));
        $http('POST', $base . '/reactivate');
        $http('POST', $base . '/unsupported', [], [], 400);
        $created = $http('POST', '/customers', ['client_code' => 'MIN-' . $this->suffix, 'legal_name' => 'Minimal client'], [], 201);
        $http('DELETE', '/customers/' . $created['client_id']);
        $http('GET', '/customers/' . $created['client_id'], [], [], 404);
    }

    public function testHttpLeadAndTicketLifecycleAndReportContracts(): void
    {
        $admin = $this->http($this->admin);
        $contact = $this->http($this->contact);
        $lead = $admin('POST', '/leads', ['business_name' => 'HTTP prospect', 'contact_name' => 'Prospect', 'email' => 'prospect-' . $this->suffix . '@example.invalid'], [], 201);
        $id = $lead['lead_id'];
        self::assertSame('CONTACTED', $admin('PUT', '/leads/' . $id, ['status' => 'CONTACTED'])['status']);
        self::assertSame(1, $admin('POST', '/leads/bulk-status', ['ids' => [$id], 'status' => 'QUALIFIED'])['updated']);
        self::assertSame('QUALIFIED', $admin('GET', '/leads/' . $id)['status']);
        self::assertSame(1, $admin('GET', '/leads', [], ['paginated' => '1', 'search' => $this->suffix])['total']);
        $admin('POST', '/leads/' . $id . '/convert', [], [], 422);
        $admin('DELETE', '/leads/' . $id);
        $admin('GET', '/leads/' . $id, [], [], 404);
        $ticket = $contact('POST', '/tickets', ['location_id' => $this->client['locations'][0]['location_id'], 'subject' => 'HTTP ticket', 'issue_description' => 'Initial issue'], [], 201);
        $path = '/tickets/' . $ticket['ticket_id'];
        self::assertSame('Edited issue', $contact('PUT', $path . '/description', ['issue_description' => 'Edited issue'])['issue_description']);
        $contact('POST', $path . '/messages', ['message' => 'Public client message'], [], 201);
        $admin('POST', $path . '/messages', ['message' => 'Internal service note', 'is_internal' => true], [], 201);
        self::assertCount(1, $contact('GET', $path)['messages']);
        self::assertCount(2, $admin('GET', $path)['messages']);
        self::assertSame('URGENT', $admin('PUT', $path . '/priority', ['priority' => 'URGENT'])['priority']);
        foreach (['accept', 'release', 'accept', 'complete', 'decline'] as $action) {
            $admin('POST', $path . '/' . $action, ['note' => 'Required audit note']);
        }
        $contact('GET', $path, [], [], 403);
        $admin('GET', '/analytics', [], ['from' => '2026-09-01', 'to' => '2026-09-30']);
        $admin('GET', '/analytics', [], ['from' => '2026-02-30'], 422);
        $ledger = $admin('GET', '/reports/service-ledger', [], ['client_id' => $this->client['client_id'], 'from' => '2026-01-01', 'to' => '2026-12-31']);
        self::assertSame(1, $ledger['total']);
        $admin('DELETE', $path);
        $admin('GET', $path, [], [], 404);
    }

    public function testHttpPinAndRecoveryRevokeOldCredentials(): void
    {
        $http = $this->http($this->admin);
        $http('POST', '/auth/trusted-device', ['pin' => '123', 'device_name' => 'Audit'], [], 422);
        $device = $http('POST', '/auth/trusted-device', ['pin' => '123456', 'device_name' => 'Audit browser'], [], 201);
        $pin = ['device_id' => $device['device_id'], 'device_token' => $device['device_token'], 'pin' => '123456'];
        $http('POST', '/auth/pin-login', array_replace($pin, ['pin' => '111111']), [], 401);
        self::assertSame($this->admin->id, $http('POST', '/auth/pin-login', $pin)['actor']->id);
        $http('DELETE', '/auth/trusted-device', ['device_id' => $device['device_id']]);
        $http('POST', '/auth/pin-login', $pin, [], 401);
        $known = $http('POST', '/auth/forgot-password', ['email' => $this->admin->email, 'realm' => 'employee']);
        $unknown = $http('POST', '/auth/forgot-password', ['email' => 'absent-' . $this->suffix . '@example.invalid', 'realm' => 'employee']);
        self::assertSame($known, $unknown);
        $statement = $this->db->connection()->prepare('SELECT body FROM email_jobs WHERE recipient_email=? ORDER BY email_job_id DESC LIMIT 1');
        $statement->execute([$this->admin->email]);
        preg_match('/https:\/\/\S+/', (string) $statement->fetchColumn(), $matches);
        parse_str((string) parse_url($matches[0], PHP_URL_QUERY), $query);
        $http('POST', '/auth/reset-password', ['realm' => 'employee', 'token' => $query['token'], 'password' => 'Recovered exact password!']);
        $http('GET', '/users', [], [], 401);
        $http('POST', '/auth/reset-password', ['realm' => 'employee', 'token' => $query['token'], 'password' => 'Another exact password!'], [], 401);
        $http('POST', '/auth/login', ['realm' => 'employee', 'email' => $this->admin->email, 'password' => self::PASSWORD], [], 401);
        self::assertSame($this->admin->id, $http('POST', '/auth/login', ['realm' => 'employee', 'email' => $this->admin->email, 'password' => 'Recovered exact password!'])['actor']->id);
        $http('POST', '/auth/refresh', [], [], 401);
        $http('POST', '/auth/logout');
    }

    public function testLocationScopeProtectsListDetailDescriptionAndClientProfile(): void
    {
        $allowed = $this->createTicket();
        $denied = $this->createTicket($this->secondLocation);
        $pdo = $this->db->connection();
        $pdo->prepare('UPDATE client_contacts SET has_all_locations=FALSE WHERE contact_id=?')->execute([$this->contact->id]);
        $pdo->prepare("INSERT INTO client_contact_locations (contact_id,location_id,created_by) VALUES (?,?,'TEST')")->execute([$this->contact->id, $allowed['location_id']]);
        self::assertSame(1, $this->service->list([], $this->contact)['total']);
        $profile = (new ClientService($this->clients, new Validator(), $this->tickets))->get((int) $this->client['client_id'], $this->contact);
        self::assertCount(1, $profile['locations']);
        self::assertCount(1, $profile['contacts']);
        try {
            $this->service->updateDescription((int) $denied['ticket_id'], ['issue_description' => 'Unauthorized'], $this->contact);
            self::fail('A contact edited an unassigned location.');
        } catch (AuthorizationException) {
            self::assertSame('Test issue', $this->tickets->find((int) $denied['ticket_id'])['issue_description']);
        }
        $this->expectException(AuthorizationException::class);
        $this->service->get((int) $denied['ticket_id'], $this->contact);
    }

    public function testNativeSearchAndCompleteTicketLifecycle(): void
    {
        $ticket = $this->createTicket();
        $id = (int) $ticket['ticket_id'];
        self::assertSame(1, $this->service->list(['search' => $this->suffix], $this->contact)['total']);
        self::assertSame(1, $this->clients->paginate(['search' => $this->suffix])['total']);
        $leads = new LeadRepository($this->db);
        $leads->create(['business_name' => 'Lead ' . $this->suffix, 'contact_name' => 'Contact', 'email' => 'lead@example.invalid'], $this->admin->identifier());
        self::assertSame(1, $leads->paginate(['search' => $this->suffix])['total']);
        self::assertTrue($this->tickets->accept($id, $this->employee->id, $this->employee->identifier()));
        self::assertFalse($this->tickets->accept($id, $this->admin->id, $this->admin->identifier()));
        self::assertFalse($this->tickets->release($id, $this->admin->id, 'Wrong employee', $this->admin->identifier()));
        self::assertTrue($this->tickets->release($id, $this->employee->id, 'Need follow-up', $this->employee->identifier()));
        self::assertTrue($this->tickets->accept($id, $this->admin->id, $this->admin->identifier()));
        self::assertTrue($this->tickets->complete($id, $this->admin->id, 'Resolved', $this->admin->identifier()));
        self::assertCount(2, $this->tickets->find($id)['attempts']);
        $reports = new AnalyticsService(new AnalyticsRepository($this->db));
        self::assertSame(1, $reports->serviceLedger(['search' => $this->suffix], $this->admin)['total']);
        self::assertTrue($this->tickets->decline($id, $this->admin->id, 'Administrative decline', $this->admin->identifier()));
        self::assertSame(0, $this->service->list([], $this->contact)['total']);
        self::assertSame(0, $reports->serviceLedger(['search' => $this->suffix], $this->employee)['total']);
        self::assertSame(1, $reports->serviceLedger(['search' => $this->suffix], $this->admin)['total']);
        self::assertNotEmpty($reports->dashboard(null, null, $this->admin)['ageing']);
    }

    public function testInternalNotesAreHiddenFromClients(): void
    {
        $ticket = $this->createTicket();
        $id = (int) $ticket['ticket_id'];
        $this->service->addMessage($id, ['message' => 'Private', 'is_internal' => true], $this->employee);
        $this->service->addMessage($id, ['message' => 'Public', 'is_internal' => false], $this->employee);
        self::assertCount(1, $this->service->get($id, $this->contact)['messages']);
        self::assertCount(2, $this->service->get($id, $this->employee)['messages']);
    }

    public function testTicketInsertRollsBackWhenInitialHistoryFails(): void
    {
        $pdo = $this->db->connection();
        $pdo->exec("CREATE TRIGGER audit_history_failure BEFORE INSERT ON service_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected test failure'");
        try {
            $this->createTicket();
            self::fail('Expected a transaction failure.');
        } catch (DatabaseException) {
            self::assertSame(0, $this->service->list([], $this->contact)['total']);
        } finally {
            $pdo->exec('DROP TRIGGER audit_history_failure');
        }
    }

    public function testPasswordResetInvalidatesAccessRefreshAndPinAndCannotBeReplayed(): void
    {
        [$auth, $tokens] = $this->auth();
        $session = $auth->login($this->admin->email, self::PASSWORD, 'employee', '127.0.0.1', 'test');
        $device = $auth->enrollTrustedDevice($tokens->decode($session['access_token']), '123456', 'test', '127.0.0.1', 'test');
        $decoded = $tokens->decode($session['access_token']);
        self::assertTrue($this->accounts->sessionActive($decoded));
        $reset = bin2hex(random_bytes(32));
        $this->accounts->createPasswordReset($this->admin->email, 'employee', hash('sha256', $reset), 'https://example.invalid/reset');
        $auth->resetPassword($reset, self::PASSWORD . 'new', 'employee');
        self::assertFalse($this->accounts->sessionActive($decoded));
        self::assertNull($this->accounts->consumeRefreshToken(hash('sha256', $session['refresh_token'])));
        self::assertNull($this->accounts->trustedDevice($device['device_id'], hash('sha256', $device['device_token'])));
        self::assertSame($this->admin->id, $auth->login($this->admin->email, self::PASSWORD . 'new', 'employee', '127.0.0.1', 'test')['actor']->id);
        $this->expectException(AuthenticationException::class);
        $auth->resetPassword($reset, 'Another password', 'employee');
    }

    public function testRefreshRotatesOnceAndMiddlewareRechecksPermissions(): void
    {
        [$auth, $tokens] = $this->auth();
        $session = $auth->login($this->employee->email, self::PASSWORD, 'employee', '127.0.0.1', 'test');
        $rotated = $auth->refresh($session['refresh_token'], '127.0.0.1', 'test');
        self::assertNotSame($session['refresh_token'], $rotated['refresh_token']);
        self::assertFalse($this->accounts->sessionActive($tokens->decode($session['access_token'])));
        $this->db->connection()->prepare("UPDATE employee_role_assignments SET is_deleted=TRUE,deleted_by='TEST',deleted_at=NOW(6) WHERE employee_id=?")->execute([$this->employee->id]);
        $request = new Request('GET', '/tickets', [], [], ['authorization' => 'Bearer ' . $rotated['access_token']], '127.0.0.1');
        (new AuthMiddleware($tokens, $this->accounts))->process($request, static function (Request $request): Response {
            self::assertFalse($request->attribute('actor')->can('tickets.manage'));
            return Response::success();
        });
        $this->expectException(AuthenticationException::class);
        $auth->refresh($session['refresh_token'], '127.0.0.1', 'test');
    }

    private function race(array $first, array $second): array
    {
        $start = microtime(true) + 1.0;
        $processes = [];
        foreach ([$first, $second] as $input) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, __DIR__ . '/race-worker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                self::fail('Unable to start concurrent database test.');
            }
            fwrite($pipes[0], json_encode($input + ['start' => $start], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
            $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['success'];
        }
        return $results;
    }

    public function testSimultaneousAcceptanceProducesExactlyOneAttempt(): void
    {
        $ticket = $this->createTicket();
        $results = $this->race(['operation' => 'accept', 'ticket' => (int) $ticket['ticket_id'], 'employee' => $this->employee->id], ['operation' => 'accept', 'ticket' => (int) $ticket['ticket_id'], 'employee' => $this->admin->id]);
        self::assertSame(1, count(array_filter($results)));
        self::assertCount(1, $this->tickets->find((int) $ticket['ticket_id'])['attempts']);
    }

    public function testSimultaneousRefreshCanOnlyRotateOnce(): void
    {
        [$auth] = $this->auth();
        $session = $auth->login($this->employee->email, self::PASSWORD, 'employee', '127.0.0.1', 'test');
        $input = ['operation' => 'refresh', 'token' => $session['refresh_token']];
        self::assertSame(1, count(array_filter($this->race($input, $input))));
    }

    public function testSimultaneousAdministratorSuspensionPreservesOneAdministrator(): void
    {
        $this->employees->updateEmployee($this->employee->id, ['full_name' => 'Second admin'], ['SYSTEM_ADMIN'], 'TEST');
        $this->db->connection()->prepare("UPDATE employee_user_accounts SET account_status='SUSPENDED' WHERE employee_id NOT IN (?,?)")->execute([$this->admin->id, $this->employee->id]);
        $results = $this->race(['operation' => 'suspend', 'employee' => $this->admin->id], ['operation' => 'suspend', 'employee' => $this->employee->id]);
        self::assertSame(1, count(array_filter($results)));
        self::assertSame(1, $this->employees->activeSystemAdministratorCount());
    }

    public function testTeamMembershipAndSoftRemovalPreserveServiceHistory(): void
    {
        $id = $this->employees->createTeam('Team ' . $this->suffix, 'Initial', 'TEST');
        $member = $this->employees->addTeamMember($id, $this->employee->id, false, 'TEST');
        self::assertSame($member, $this->employees->addTeamMember($id, $this->employee->id, true, 'TEST'));
        $this->employees->removeTeamMember($id, $this->employee->id, 'TEST');
        self::assertSame($member, $this->employees->addTeamMember($id, $this->employee->id, false, 'TEST'));
        $this->employees->updateTeam($id, 'Updated ' . $this->suffix, 'Changed', 'TEST');
        self::assertSame('Changed', $this->employees->team($id)['description']);
        $this->employees->removeTeam($id, 'TEST');
        $ticket = $this->createTicket();
        $this->clients->removeLocation((int) $this->client['client_id'], (int) $ticket['location_id'], 'TEST');
        $this->clients->removeContact((int) $this->client['client_id'], $this->contact->id, 'TEST');
        self::assertNotNull($this->tickets->find((int) $ticket['ticket_id']));
        self::assertNull($this->accounts->actor('CLIENT_CONTACT', $this->contact->id));
    }

    public function testLeadConversionRollsBackOnFailureAndCannotBeRepeated(): void
    {
        $leads = new LeadRepository($this->db);
        $id = $leads->create(['business_name' => 'Convert ' . $this->suffix, 'contact_name' => 'Contact', 'email' => 'convert@example.invalid'], 'TEST');
        try {
            $leads->convert($id, function (): array {
                $this->db->connection()->prepare('UPDATE clients SET notes=? WHERE client_id=?')->execute(['Should roll back', $this->client['client_id']]);
                throw new \RuntimeException('Injected failure');
            }, 'TEST');
            self::fail('Expected conversion rollback.');
        } catch (\RuntimeException) {
            self::assertNull($leads->find($id)['converted_client_id']);
            self::assertNotSame('Should roll back', $this->clients->find((int) $this->client['client_id'])['notes']);
        }
        $leads->convert($id, fn (): array => $this->client, 'TEST');
        self::assertSame('WON', $leads->find($id)['status']);
        $this->expectException(\App\Exceptions\BadRequestException::class);
        $leads->convert($id, fn (): array => $this->client, 'TEST');
    }

    public function testHttpPipelinePreservesPasswordAndEnforcesRealmPermissions(): void
    {
        foreach (['HOST', 'PORT', 'NAME', 'USER', 'PASSWORD'] as $key) {
            putenv('DB_' . $key . '=' . (getenv('TEST_DB_' . $key) ?: ($key === 'HOST' ? '127.0.0.1' : '')));
        }
        putenv('APP_KEY=' . str_repeat('integration-key-', 4));
        putenv('JWT_ISSUER=audit');
        putenv('APP_URL=https://example.invalid');
        putenv('APP_DEBUG=false');
        $app = require dirname(__DIR__, 2) . '/bootstrap.php';
        $login = $app->run(new Request('POST', '/api/v1/auth/login', [], ['email' => $this->contact->email, 'password' => self::PASSWORD, 'realm' => 'client'], [], '127.0.0.1'));
        self::assertSame(200, $login->status);
        self::assertArrayHasKey('Set-Cookie', $login->headers);
        $headers = ['authorization' => 'Bearer ' . $login->payload['data']['access_token']];
        $tickets = $app->run(new Request('GET', '/api/v1/tickets', ['paginated' => '1'], [], $headers, '127.0.0.1'));
        self::assertSame(200, $tickets->status);
        self::assertSame(0, $tickets->payload['data']['total']);
        $forbidden = $app->run(new Request('POST', '/api/v1/auth/register', [], ['full_name' => 'Unauthorized'], $headers, '127.0.0.1'));
        self::assertSame(403, $forbidden->status);
        $forbidden = $app->run(new Request('GET', '/api/v1/users', [], [], $headers, '127.0.0.1'));
        self::assertSame(403, $forbidden->status);
        $bad = $app->run(new Request('POST', '/api/v1/auth/login', [], ['email' => $this->contact->email, 'password' => ['invalid'], 'realm' => 'client'], [], '127.0.0.1'));
        self::assertSame(422, $bad->status);
    }

    public function testLeadConversionCreatesCompleteClientAndDuplicateIdentityIsRejected(): void
    {
        $leads = new LeadRepository($this->db);
        $clients = new ClientService($this->clients, new Validator(), $this->tickets);
        $service = new \App\Services\LeadService($leads, new Validator(), $clients);
        $lead = $service->create(['business_name' => 'Conversion', 'contact_name' => 'Owner', 'email' => 'prospect@example.invalid'], $this->admin);
        $input = [
            'client' => ['client_code' => 'CON-' . $this->suffix, 'legal_name' => 'Converted client'],
            'location' => ['location_code' => 'HQ', 'location_name' => 'Office', 'location_type' => 'HEAD_OFFICE', 'address_line_1' => 'Test street', 'city' => 'Delhi', 'state_name' => 'Delhi', 'postal_code' => '110001'],
            'administrator' => ['full_name' => 'Owner', 'email' => 'converted-' . $this->suffix . '@example.invalid', 'mobile_number' => '9999999999', 'password' => self::PASSWORD],
        ];
        $result = $service->convert((int) $lead['lead_id'], $input, $this->admin);
        $converted = $this->clients->find((int) $leads->find((int) $lead['lead_id'])['converted_client_id']);
        self::assertCount(1, $converted['locations']);
        self::assertCount(1, $converted['contacts']);
        [$auth] = $this->auth();
        self::assertSame('CLIENT_CONTACT', $auth->login($input['administrator']['email'], self::PASSWORD, 'client', '127.0.0.1', 'test')['actor']->type);
        $this->expectException(\App\Exceptions\ValidationException::class);
        $clients->onboard($input, $this->admin);
    }

    public function testExpiredResetTokenAndSuspendedSessionAreRejected(): void
    {
        [$auth, $tokens] = $this->auth();
        $session = $auth->login($this->employee->email, self::PASSWORD, 'employee', '127.0.0.1', 'test');
        $this->db->connection()->prepare("UPDATE employee_user_accounts SET password_reset_token_hash=?, password_reset_expires_at=DATE_SUB(NOW(6), INTERVAL 1 MINUTE) WHERE employee_id=?")->execute([hash('sha256', 'expired-token'), $this->employee->id]);
        self::assertFalse($this->accounts->resetPassword('employee', hash('sha256', 'expired-token'), password_hash('Replacement password!', PASSWORD_BCRYPT, ['cost' => 4])));
        $this->employees->suspend($this->employee->id, 'Test suspension', $this->admin->identifier());
        $middleware = new AuthMiddleware($tokens, $this->accounts);
        $this->expectException(AuthenticationException::class);
        $middleware->process(new Request('GET', '/api/v1/tickets', [], [], ['authorization' => 'Bearer ' . $session['access_token']], '127.0.0.1'), static fn (): Response => Response::success([]));
    }
}
