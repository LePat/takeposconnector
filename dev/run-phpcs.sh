#!/bin/sh
# Run PHP_CodeSniffer against this module using the Dolibarr coding standard,
# with the fix from dev/phpcs-ruleset.xml (see that file's header comment for why).
#
# Setup (one-time), matching the outer repo's dev/setup/codesniffer/README:
#   composer global require "squizlabs/php_codesniffer:^3.13"
#   export PATH="$HOME/.config/composer/vendor/bin:$PATH"
#
# Usage:
#   ./dev/run-phpcs.sh            # report violations
#   ./dev/run-phpcs.sh --fix      # auto-fix what phpcbf can fix

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
MODULE_DIR="$(dirname "$SCRIPT_DIR")"
RULESET="$SCRIPT_DIR/phpcs-ruleset.xml"

if ! command -v phpcs >/dev/null 2>&1; then
	echo "phpcs not found on PATH. Install it with:" >&2
	echo "  composer global require \"squizlabs/php_codesniffer:^3.13\"" >&2
	echo "  export PATH=\"\$HOME/.config/composer/vendor/bin:\$PATH\"" >&2
	exit 1
fi

if [ "$1" = "--fix" ]; then
	phpcbf --standard="$RULESET" --parallel=8 "$MODULE_DIR"
else
	phpcs --standard="$RULESET" --report=full --parallel=8 "$MODULE_DIR"
fi
