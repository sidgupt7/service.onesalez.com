# ADR 0001: Framework-free clean PHP core

## Status

Accepted.

## Context

The Hostinger target favors a small deployment artifact and conventional PHP/Apache hosting. The API still requires testable dependency injection, middleware, versioned routing, repositories, transactions, and structured error handling.

## Decision

Use a small PSR-4 application core instead of a full framework. Controllers translate HTTP, services enforce business rules and tenant boundaries, repositories own SQL, and infrastructure is injected through a small container. Firebase JWT, Monolog, Dotenv, PHPUnit, PHPStan, and PHPCS cover narrowly defined concerns.

## Consequences

The runtime has a small dependency surface and no framework lock-in. Application conventions are explicit and must be maintained deliberately. New modules must preserve the controller-service-repository boundary and register dependencies and routes in `bootstrap.php`.

