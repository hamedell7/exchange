# Identity registration: Service/Repository vs pragmatic DDD

This document compares two ways to implement the same registration use case in
`identity-service`. It is an architectural learning example, not two production
implementations of the same endpoint. Only one implementation should own the
real HTTP route.

## Use case and assumptions

A client submits an email address and password. The service must:

1. reject malformed email addresses;
2. reject an email that is already registered;
3. hash the password using the configured password-hashing mechanism;
4. create the user;
5. return a safe response (never the password or password hash).

The database unique constraint on `users.email` remains mandatory. An
application-level existence check improves the error response but cannot
prevent a race between two concurrent requests.

The current Laravel skeleton already has an Eloquent `App\\Models\\User` and a
`users` migration. The DDD `Domain\\Entities\\User` introduced in the first
slice is a separate domain model; it is not an Eloquent model.

---

## Approach A — Controller + AuthService + UserRepository

This is a perfectly reasonable design for a small and mostly CRUD-oriented
identity service.

### Request/controller

The controller validates transport input and delegates the use case:

```php
final class RegisterController
{
    public function __invoke(
        RegisterRequest $request,
        AuthService $auth,
    ): JsonResponse {
        $user = $auth->register(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
        ], 201);
    }
}
```

### Service

```php
final class AuthService
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function register(string $email, string $plainPassword): User
    {
        $email = trim($email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        if ($this->users->existsByEmail($email)) {
            throw new EmailAlreadyRegistered();
        }

        return $this->users->create([
            'email' => $email,
            'password' => Hash::make($plainPassword),
        ]);
    }
}
```

### Repository

```php
final class EloquentUserRepository implements UserRepository
{
    public function existsByEmail(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
```

### What is good here?

- Few files and a short path from request to database.
- Easy to understand for a small use case.
- No need to introduce a domain model if there are no meaningful domain
  invariants yet.

### Where does it become harder?

As registration gains policies (normalization, invitation eligibility,
tenant restrictions, identity status, credential rules, etc.), the service
can become the place where every rule accumulates. The service may still be
well-designed, but it needs deliberate decomposition and tests to avoid
becoming a large procedural coordinator.

Importantly, this is not an inherent flaw of the pattern. A conventional
service can also delegate to focused policies/value objects. DDD does not
magically make code maintainable.

---

## Approach B — Controller + Application Use Case + Domain + Infrastructure

The HTTP controller still does very little. The difference is that application
orchestration and domain invariants have explicit boundaries.

### Application port: password hashing

The Domain must not choose Laravel's hashing facade or a concrete algorithm.
The application defines the capability it needs:

```php
interface PasswordHasher
{
    public function hash(string $plainPassword): PasswordHash;
}
```

An Infrastructure adapter can implement it with Laravel's `Hash` facade:

```php
final class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): PasswordHash
    {
        return PasswordHash::fromEncodedHash(
            Hash::make($plainPassword),
        );
    }
}
```

### Application port: user persistence

```php
interface UserRepository
{
    public function existsByEmail(string $email): bool;

    public function save(User $user): void;
}
```

The Infrastructure implementation performs Eloquent queries and maps between
the domain aggregate and the Eloquent record. The Domain never imports
Eloquent.

### Application use case

```php
final class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private UserIdGenerator $ids,
    ) {}

    public function execute(string $name, string $email, string $plainPassword): User
    {
        $emailAddress = new EmailAddress($email);

        if ($this->users->existsByEmail($emailAddress->value())) {
            throw EmailAlreadyRegistered::for($emailAddress->value());
        }

        $user = User::register(
            id: $this->ids->generate(),
            name: $name,
            email: $emailAddress,
            passwordHash: $this->passwordHasher->hash($plainPassword),
            registeredAt: new DateTimeImmutable(),
        );

        $this->users->save($user);

        return $user;
    }
}
```

The `UserIdGenerator` port isolates the ID-generation strategy from the
Domain. The current use case uses `DateTimeImmutable` directly for its
registration timestamp; a Clock port can be introduced later if deterministic
time control becomes a real testing or business requirement.

### Domain

The existing `EmailAddress`, `PasswordHash`, `UserId` and `User` are the
beginning of this layer:

- `EmailAddress` owns email syntax and its chosen normalization policy.
- `PasswordHash` represents an encoded hash, not a plain password.
- `UserId` represents a valid user identifier.
- `User` is the aggregate boundary for user state and future lifecycle rules.

The Domain does not know about HTTP requests, Laravel facades, SQL, Eloquent,
Redis, or queues.

### What changed in the responsibilities?

| Concern | Approach A | Approach B |
|---|---|---|
| HTTP parsing/response | Controller | Controller |
| Coordinate registration | AuthService | RegisterUser use case |
| Email format/normalization | Usually service/request | EmailAddress |
| Check email uniqueness | Repository via service | Repository via use case |
| Hash password | Service calls Hash | PasswordHasher port + adapter |
| User invariants/lifecycle | Often service/model | Domain aggregate |
| Database mapping | Repository/Eloquent | Infrastructure adapter |
| Test business rules without Laravel | Possible, but often coupled | Natural boundary |

---

## A realistic change: email normalization policy

Suppose the product decides that the domain portion of an email should be
stored lowercase, while the local portion should be preserved.

With Approach A, the rule is commonly added to the registration service:

```php
[$local, $domain] = explode('@', trim($email), 2);
$email = $local.'@'.strtolower($domain);
```

If other use cases accept email addresses (admin creation, invitation
acceptance, account recovery), each path must remember to apply the same rule
or call a shared helper.

With Approach B, construction of `EmailAddress` applies the policy once.
Every use case that requires an email address uses the same type, so it cannot
silently bypass that invariant by passing an arbitrary string.

This does not mean the DDD version has fewer lines. It means the rule has one
explicit owner and the type communicates what has already been validated.

A second example is a future lifecycle rule such as “a suspended user cannot
change their primary email.” That rule belongs with the User's state transition
once the lifecycle is modeled; it should not be copied into every controller.

---

## What DDD does NOT mean in this codebase

- It does not mean one Value Object for every database column.
- It does not mean every table gets a Repository interface.
- It does not mean every class needs an interface.
- It does not mean controllers, services, or repositories disappear.
- It does not mean all validation belongs in Domain. HTTP shape and required
  fields belong at the boundary; database uniqueness belongs in application
  orchestration plus a database constraint; domain invariants belong in Domain.
- It does not mean CQRS, event sourcing, factories, buses, or aggregates should
  be added without a concrete reason.

## Recommendation for Exchange

Use a pragmatic split:

- **Identity:** moderate DDD. Keep registration, credentials, sessions, MFA,
  and account lifecycle boundaries explicit, but avoid modeling simple
  profile fields as rich objects without rules.
- **Wallet:** stronger domain modeling around ledger invariants, holds,
  available balance, idempotency, and concurrent money movement.
- **Trading:** stronger domain modeling around order lifecycle, market rules,
  matching, and trade invariants.

The first live DDD registration path is now implemented in this branch:

- `POST /api/v1/identity/register` is registered through Laravel's API routing.
- The controller validates the HTTP payload and delegates to `RegisterUser`.
- The use case coordinates the `EmailAddress` and `User` domain objects with
  the password-hashing, ID-generation, and persistence ports.
- Infrastructure adapters use Laravel's Hash facade, ULIDs, and Eloquent.
- The users table uses ULID primary keys to keep the domain identifier strategy
  independent from database auto-increment behavior.
- Feature tests cover successful registration, duplicate email rejection, and
  password validation; domain tests cover the aggregate and value objects.

The current implementation deliberately does not add login, token issuance,
email verification, MFA, or cross-service events. Those are separate use cases
and should be designed against the Identity service's actual authentication
and session boundaries rather than being smuggled into registration.

The conventional version above remains the comparison baseline, not a second
live production endpoint. Tests have been authored but have not been executed
in this environment; no passing test or CI result is claimed.
