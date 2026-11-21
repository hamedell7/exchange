# GitHub Repository Access Model

## Repositories

- `exchange-platform`: Gateway, infrastructure, contracts and platform documentation.
- `exchange-identity-service`: Identity domain and application.
- `exchange-wallet-service`: Wallet domain and application.
- `exchange-trading-service`: Trading domain and application.

## Suggested Teams

- `platform-admins`: platform owners; administer the shared platform repository and organization settings.
- `platform-maintainers`: maintain Gateway, infrastructure and shared platform tooling.
- `identity-maintainers`: maintain Identity only.
- `wallet-maintainers`: maintain Wallet only.
- `trading-maintainers`: maintain Trading only.

## Suggested Permissions

| Repository | Team | Permission |
|---|---|---|
| `exchange-platform` | `platform-maintainers` | Write |
| `exchange-platform` | service teams, when needed | Read |
| `exchange-identity-service` | `identity-maintainers` | Write |
| `exchange-wallet-service` | `wallet-maintainers` | Write |
| `exchange-trading-service` | `trading-maintainers` | Write |
| service repositories | other service teams | No access by default |

A service developer with Read access to `exchange-platform` can see the shared Gateway, infrastructure and contract sources. They cannot see the source code of the other business services because those are separate repositories. Do not store production secrets in any Git repository.

## Branch Protection

For every repository's default branch:

- Require pull requests for changes.
- Require at least one approval from an authorized reviewer.
- Require successful CI checks before merge.
- Block force pushes and deletion of the default branch.
- Restrict administrative bypass to a small platform-admin group.

## Runtime Security Is Separate

GitHub permissions control source-code access only. Database roles, RabbitMQ virtual-host permissions, Redis ACLs, network policy, container registry access, deployment credentials and production secrets must be controlled separately with least privilege.
