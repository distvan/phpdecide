# PHPStan example assets

This directory contains a checked-in native PHPStan JSON report that already maps to an active sample decision in this repository.

Files:

- `no-orm-in-order-domain-report.json`: native `--error-format=json` style report using the decision token `phpstan.doctrine.orm`

Matching decision and fixture files:

- `.decisions/DEC-0005.no-orm-in-order-domain-via-phpstan.yaml`
- `examples/fixtures/phpstan/src/Order/OrderService.php`
- `examples/fixtures/phpstan/src/Infrastructure/Persistence/Doctrine/OrderRecord.php`

Try it locally:

```bash
php ./bin/phpdecide enforce --phpstan-report examples/phpstan/no-orm-in-order-domain-report.json
```

Or emit structured output:

```bash
php ./bin/phpdecide enforce --phpstan-report examples/phpstan/no-orm-in-order-domain-report.json --format json
```

The important contract is that the PHPStan message `identifier` field must equal a token listed in the matching decision's `rules.forbid` array.

## Emitting the identifier from a real PHPStan rule

In a real project, the native JSON report usually comes from a custom PHPStan rule or extension.
The key requirement for PHPDecide is simple: the rule must emit a stable identifier that matches the decision token.

For the checked-in example in this repository, that token is:

- `phpstan.doctrine.orm`

That means a real PHPStan rule should report errors using that same identifier.

Minimal sketch:

```php
<?php

declare(strict_types=1);

namespace App\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\IdentifierRuleErrorBuilder;
use PHPStan\Rules\Rule;

/** @implements Rule<Node\Stmt\Use_> */
final class NoOrmInOrderDomainRule implements Rule
{
	public function getNodeType(): string
	{
		return Node\Stmt\Use_::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$file = str_replace('\\', '/', $scope->getFile());

		if (!str_contains($file, '/src/Order/')) {
			return [];
		}

		foreach ($node->uses as $useUse) {
			$import = $useUse->name->toString();

			if (str_starts_with($import, 'Doctrine\\ORM\\')) {
				return [
					IdentifierRuleErrorBuilder::message('Doctrine ORM symbol detected in the Order domain.')
						->identifier('phpstan.doctrine.orm')
						->build(),
				];
			}
		}

		return [];
	}
}
```

When PHPStan is run with `--error-format=json`, that identifier appears in `files[*].messages[*].identifier`, which is exactly what `phpdecide enforce --phpstan-report ...` reads.

The mapping chain is:

- PHPStan rule emits `identifier: phpstan.doctrine.orm`
- native PHPStan JSON contains that identifier
- decision `rules.forbid` contains `phpstan.doctrine.orm`
- PHPDecide maps the finding back to `DEC-0005`